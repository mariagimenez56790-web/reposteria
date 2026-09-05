<?php

namespace Tests\Feature;

use App\Enums\MovimientoInventarioTipo;
use App\Enums\UnidadMedida;
use App\Models\Ingrediente;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\IngredienteService;
use App\Services\InventarioService;
use App\Services\RecetaService;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventarioManagementTest extends TestCase
{
    use RefreshDatabase;

    private IngredienteService $ingredientes;

    private InventarioService $inventario;

    private RecetaService $recetas;

    private ReposteriaEstadoService $estados;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->ingredientes = app(IngredienteService::class);
        $this->inventario = app(InventarioService::class);
        $this->recetas = app(RecetaService::class);
        $this->estados = app(ReposteriaEstadoService::class);
    }

    public function test_inventory_tables_and_relationships_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('ingredientes', ['reposteria_id', 'nombre', 'unidad_medida', 'stock_actual', 'stock_minimo', 'costo_unitario', 'activo', 'deleted_at']));
        $this->assertTrue(Schema::hasColumns('recetas', ['reposteria_id', 'producto_id', 'nombre', 'rendimiento', 'activo', 'deleted_at']));
        $this->assertTrue(Schema::hasColumns('ingrediente_receta', ['id', 'receta_id', 'ingrediente_id', 'cantidad']));
        $this->assertTrue(Schema::hasColumns('movimientos_inventario', ['reposteria_id', 'ingrediente_id', 'tipo', 'cantidad', 'stock_anterior', 'stock_nuevo', 'creado_por', 'fecha_movimiento']));
    }

    public function test_admin_creates_valid_ingredient_with_protected_zero_stock(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $ingrediente = $this->ingredientes->crear($admin, $reposteria, ['nombre' => 'Harina', 'unidad_medida' => 'kilogramo', 'stock_minimo' => '2.500', 'costo_unitario' => '8.20', 'stock_actual' => '99.000']);
        $this->assertSame('0.000', $ingrediente->stock_actual);
        $this->assertSame(UnidadMedida::Kilogramo, $ingrediente->unidad_medida);
        $this->assertTrue($ingrediente->reposteria->is($reposteria));
        $ingrediente->fill(['stock_actual' => '50.000']);
        $this->assertSame('0.000', $ingrediente->stock_actual);
    }

    public function test_ingredient_validation_and_unique_name_per_tenant(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b, $adminB] = $this->contexto();
        $datos = ['nombre' => 'Azúcar', 'unidad_medida' => 'kilogramo', 'stock_minimo' => '0.000', 'costo_unitario' => null];
        $this->ingredientes->crear($adminA, $a, $datos);
        $this->ingredientes->crear($adminB, $b, $datos);
        $this->expectException(ValidationException::class);
        $this->ingredientes->crear($adminA, $a, $datos);
    }

    public function test_invalid_unit_minimum_and_cost_are_rejected(): void
    {
        [$reposteria, $admin] = $this->contexto();
        foreach ([['unidad_medida' => 'caja', 'stock_minimo' => 0, 'costo_unitario' => 1], ['unidad_medida' => 'gramo', 'stock_minimo' => -1, 'costo_unitario' => 1], ['unidad_medida' => 'gramo', 'stock_minimo' => 0, 'costo_unitario' => -1]] as $caso) {
            try {
                $this->ingredientes->crear($admin, $reposteria, ['nombre' => fake()->unique()->word()] + $caso);
                $this->fail();
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_recipe_validates_tenant_and_updates_one_pivot_row(): void
    {
        [$reposteria, $admin] = $this->contexto();
        [$otra, $otroAdmin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create();
        $harina = $this->ingredientes->crear($admin, $reposteria, ['nombre' => 'Harina', 'unidad_medida' => 'kilogramo', 'stock_minimo' => 0]);
        $azucar = $this->ingredientes->crear($admin, $reposteria, ['nombre' => 'Azúcar', 'unidad_medida' => 'kilogramo', 'stock_minimo' => 0]);
        $receta = $this->recetas->crear($admin, $reposteria, ['producto_id' => $producto->id, 'nombre' => 'Estándar', 'rendimiento' => '12.000']);
        $this->recetas->guardarIngrediente($admin, $receta, $harina, '0.500');
        $this->recetas->guardarIngrediente($admin, $receta, $harina, '0.750');
        $this->recetas->guardarIngrediente($admin, $receta, $azucar, '0.250');
        $this->assertCount(2, $receta->fresh()->ingredientes);
        $this->assertEquals(0.750, $receta->fresh()->ingredientes->find($harina->id)->pivot->cantidad);
        $ajeno = Ingrediente::factory()->for($otra)->create();
        $this->expectException(ValidationException::class);
        $this->recetas->guardarIngrediente($admin, $receta, $ajeno, 1);
    }

    public function test_cross_tenant_product_and_non_positive_recipe_amount_are_rejected(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $productoB = Producto::factory()->for($b)->create();
        try {
            $this->recetas->crear($adminA, $a, ['producto_id' => $productoB->id, 'nombre' => 'Ajena', 'rendimiento' => 1]);
            $this->fail();
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $productoA = Producto::factory()->for($a)->create();
        $receta = $this->recetas->crear($adminA, $a, ['producto_id' => $productoA->id, 'nombre' => 'Base', 'rendimiento' => 1]);
        $ingrediente = Ingrediente::factory()->for($a)->create();
        $this->expectException(ValidationException::class);
        $this->recetas->guardarIngrediente($adminA, $receta, $ingrediente, 0);
    }

    public function test_all_inventory_movements_recalculate_stock_and_preserve_history(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $ingrediente = Ingrediente::factory()->for($reposteria)->create();
        $entrada = $this->inventario->entrada($admin, $ingrediente, ['cantidad' => '10.500', 'referencia_tipo' => 'compra', 'referencia_id' => 8]);
        $this->assertSame('0.000', $entrada->stock_anterior);
        $this->assertSame('10.500', $entrada->stock_nuevo);
        $this->assertSame(MovimientoInventarioTipo::Entrada, $entrada->tipo);
        $this->assertTrue($entrada->creadoPor->is($admin));
        $this->inventario->salida($admin, $ingrediente, ['cantidad' => '2.250']);
        $this->inventario->ajustePositivo($admin, $ingrediente, ['cantidad' => '1.000']);
        $this->inventario->ajusteNegativo($admin, $ingrediente, ['cantidad' => '0.250']);
        $this->assertSame('9.000', $ingrediente->fresh()->stock_actual);
        $this->assertCount(4, $ingrediente->movimientos);
    }

    public function test_insufficient_or_invalid_movement_rolls_back(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $ingrediente = Ingrediente::factory()->for($reposteria)->create();
        foreach (['0.000', '-1.000', '1.001'] as $cantidad) {
            try {
                $this->inventario->salida($admin, $ingrediente, ['cantidad' => $cantidad]);
                $this->fail();
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame('0.000', $ingrediente->fresh()->stock_actual);
        $this->assertSame(0, MovimientoInventario::count());
    }

    public function test_roles_and_tenant_isolation_are_enforced(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b, $adminB] = $this->contexto();
        $ingredienteB = Ingrediente::factory()->for($b)->create(['stock_actual' => '5.000']);
        $produccionB = $this->miembro($b, 'produccion');
        $this->inventario->salida($produccionB, $ingredienteB, ['cantidad' => 1]);
        foreach ([$adminA, $this->miembro($a, 'produccion'), $this->miembro($b, 'vendedor'), $this->usuario('cliente'), $this->usuario('admin', false)] as $actor) {
            try {
                $this->inventario->ajustePositivo($actor, $ingredienteB, ['cantidad' => 1]);
                $this->fail();
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame('4.000', $ingredienteB->fresh()->stock_actual);
        $this->assertTrue($adminB->perteneceAReposteria($b));
    }

    public function test_superadmin_unit_change_and_deletion_policies(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $super = $this->usuario('superadmin');
        $ingrediente = $this->ingredientes->crear($super, $reposteria, ['nombre' => 'Leche', 'unidad_medida' => 'litro', 'stock_minimo' => 0]);
        $this->inventario->entrada($super, $ingrediente, ['cantidad' => 1]);
        try {
            $this->ingredientes->actualizar($super, $ingrediente, ['nombre' => 'Leche', 'unidad_medida' => 'mililitro', 'stock_minimo' => 0]);
            $this->fail();
        } catch (DomainException) {
            $this->assertTrue(true);
        }
        try {
            $this->ingredientes->eliminar($admin, $ingrediente);
            $this->fail();
        } catch (DomainException) {
            $this->assertTrue(true);
        }
        $sinHistorial = Ingrediente::factory()->for($reposteria)->create();
        $this->ingredientes->eliminar($admin, $sinHistorial);
        $this->assertSoftDeleted($sinHistorial);
        $this->assertSame(1, MovimientoInventario::count());
    }

    private function contexto(): array
    {
        $admin = $this->usuario('admin');
        $reposteria = Reposteria::factory()->for($admin, 'propietario')->create();

        return [$this->estados->aprobar($reposteria, $this->usuario('superadmin')), $admin];
    }

    private function miembro(Reposteria $reposteria, string $rol): User
    {
        $usuario = $this->usuario($rol);
        $reposteria->usuarios()->attach($usuario);

        return $usuario;
    }

    private function usuario(string $rol, bool $activo = true): User
    {
        return User::factory()->for(Role::query()->where('nombre', $rol)->firstOrFail())->create(['activo' => $activo]);
    }
}
