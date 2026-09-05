<?php

namespace Tests\Feature;

use App\Models\Ingrediente;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventarioApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_inventory_routes_require_authentication_and_active_user(): void
    {
        foreach (['ingredientes', 'recetas', 'inventario/movimientos'] as $ruta) {
            $this->getJson("/api/v1/reposterias/1/{$ruta}")->assertUnauthorized();
        }
        [$reposteria] = $this->contexto();
        Sanctum::actingAs($this->usuario('admin', false));
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/ingredientes")->assertForbidden();
    }

    public function test_admin_production_and_superadmin_can_read_but_vendor_and_client_are_blocked(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $ingrediente = Ingrediente::factory()->for($reposteria)->create();
        foreach ([$admin, $this->miembro($reposteria, 'produccion'), $this->usuario('superadmin')] as $actor) {
            Sanctum::actingAs($actor);
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/ingredientes/{$ingrediente->id}")->assertOk();
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/recetas")->assertOk();
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/inventario/movimientos")->assertOk();
        }
        foreach ([$this->miembro($reposteria, 'vendedor'), $this->usuario('cliente')] as $actor) {
            Sanctum::actingAs($actor);
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/ingredientes")->assertForbidden();
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/recetas")->assertForbidden();
        }
    }

    public function test_ingredient_listing_searches_filters_paginates_and_is_tenant_scoped(): void
    {
        [$reposteria, $admin] = $this->contexto();
        Ingrediente::factory()->for($reposteria)->create(['nombre' => 'Harina fina', 'activo' => true, 'unidad_medida' => 'kilogramo']);
        Ingrediente::factory()->for($reposteria)->create(['nombre' => 'Harina vieja', 'activo' => false, 'unidad_medida' => 'kilogramo']);
        Ingrediente::factory()->for($this->contexto()[0])->create(['nombre' => 'Harina ajena', 'activo' => true, 'unidad_medida' => 'kilogramo']);
        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/ingredientes?search=Harina&activo=1&unidad_medida=kilogramo&per_page=1")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.nombre', 'Harina fina')->assertJsonMissingPath('data.0.reposteria_id');
    }

    public function test_ingredient_detail_is_hidden_across_tenants(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $ajeno = Ingrediente::factory()->for($b)->create();
        Sanctum::actingAs($adminA);
        $this->getJson("/api/v1/reposterias/{$a->id}/ingredientes/{$ajeno->id}")->assertNotFound();
        $this->patchJson("/api/v1/reposterias/{$a->id}/ingredientes/{$ajeno->id}", ['nombre' => 'Hack'])->assertNotFound();
    }

    public function test_only_admin_and_superadmin_create_ingredient_with_protected_zero_stock(): void
    {
        [$reposteria, $admin] = $this->contexto();
        foreach ([$admin, $this->usuario('superadmin')] as $actor) {
            Sanctum::actingAs($actor);
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/ingredientes", [
                'nombre' => 'Ingrediente '.fake()->unique()->word(),
                'unidad_medida' => 'gramo',
                'stock_minimo' => '1.250',
                'costo_unitario' => '2.50',
                'stock_actual' => '999.000',
                'reposteria_id' => 999,
            ])->assertCreated()->assertJsonPath('data.stock_actual', '0.000')->assertJsonPath('data.stock_minimo', '1.250')->assertJsonPath('data.costo_unitario', '2.50');
        }
        foreach ([$this->miembro($reposteria, 'produccion'), $this->miembro($reposteria, 'vendedor'), $this->usuario('cliente')] as $actor) {
            Sanctum::actingAs($actor);
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/ingredientes", ['nombre' => 'No', 'unidad_medida' => 'litro', 'stock_minimo' => 0])->assertForbidden();
        }
    }

    public function test_ingredient_patch_is_partial_protects_stock_and_validates_unit(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $ingrediente = Ingrediente::factory()->for($reposteria)->create(['nombre' => 'Leche', 'unidad_medida' => 'litro', 'stock_actual' => '5.000']);
        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/ingredientes/{$ingrediente->id}", ['nombre' => 'Leche fresca', 'stock_actual' => '99.000', 'reposteria_id' => 999])
            ->assertOk()->assertJsonPath('data.nombre', 'Leche fresca')->assertJsonPath('data.stock_actual', '5.000');
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/ingredientes/{$ingrediente->id}", ['unidad_medida' => 'caja'])->assertUnprocessable();
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/ingredientes/{$ingrediente->id}", ['unidad_medida' => 'mililitro'])->assertOk();
    }

    public function test_unit_change_is_blocked_after_movement_or_recipe_use(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $conMovimiento = Ingrediente::factory()->for($reposteria)->create(['unidad_medida' => 'kilogramo']);
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/reposterias/{$reposteria->id}/inventario/movimientos", ['ingrediente_id' => $conMovimiento->id, 'tipo' => 'entrada', 'cantidad' => '1.000'])->assertCreated();
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/ingredientes/{$conMovimiento->id}", ['unidad_medida' => 'gramo'])->assertUnprocessable();

        $enReceta = Ingrediente::factory()->for($reposteria)->create(['unidad_medida' => 'kilogramo']);
        $producto = Producto::factory()->for($reposteria)->create();
        $this->postJson("/api/v1/reposterias/{$reposteria->id}/recetas", ['producto_id' => $producto->id, 'nombre' => 'Base', 'rendimiento' => '1.000', 'ingredientes' => [['ingrediente_id' => $enReceta->id, 'cantidad' => '0.250']]])->assertCreated();
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/ingredientes/{$enReceta->id}", ['unidad_medida' => 'gramo'])->assertUnprocessable();
    }

    public function test_recipe_create_list_detail_and_partial_update_use_three_decimal_quantities_without_stock_changes(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create();
        $harina = Ingrediente::factory()->for($reposteria)->create(['nombre' => 'Harina', 'stock_actual' => '10.000', 'unidad_medida' => 'kilogramo']);
        Sanctum::actingAs($admin);
        $receta = $this->postJson("/api/v1/reposterias/{$reposteria->id}/recetas", [
            'producto_id' => $producto->id,
            'nombre' => 'Torta base',
            'rendimiento' => '12.000',
            'ingredientes' => [['ingrediente_id' => $harina->id, 'cantidad' => '0.250']],
        ])->assertCreated()->assertJsonPath('data.rendimiento', '12.000')->assertJsonPath('data.ingredientes.0.cantidad', '0.250')->json('data');
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/recetas?search=Torta&producto_id={$producto->id}&per_page=1")->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/recetas/{$receta['id']}")->assertOk()->assertJsonPath('data.producto.id', $producto->id);
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/recetas/{$receta['id']}", ['nombre' => 'Torta actualizada'])->assertOk()->assertJsonPath('data.nombre', 'Torta actualizada')->assertJsonCount(1, 'data.ingredientes');
        $this->assertSame('10.000', $harina->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_recipe_rejects_foreign_product_ingredient_and_non_positive_quantity_transactionally(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $productoA = Producto::factory()->for($a)->create();
        $productoB = Producto::factory()->for($b)->create();
        $ingredienteB = Ingrediente::factory()->for($b)->create();
        Sanctum::actingAs($adminA);
        foreach ([
            ['producto_id' => $productoB->id, 'nombre' => 'Ajena', 'rendimiento' => 1],
            ['producto_id' => $productoA->id, 'nombre' => 'Ingrediente ajeno', 'rendimiento' => 1, 'ingredientes' => [['ingrediente_id' => $ingredienteB->id, 'cantidad' => 1]]],
            ['producto_id' => $productoA->id, 'nombre' => 'Cero', 'rendimiento' => 1, 'ingredientes' => [['ingrediente_id' => $ingredienteB->id, 'cantidad' => 0]]],
            ['producto_id' => $productoA->id, 'nombre' => 'Negativa', 'rendimiento' => -1],
        ] as $datos) {
            $this->postJson("/api/v1/reposterias/{$a->id}/recetas", $datos)->assertUnprocessable();
        }
        $this->assertDatabaseCount('recetas', 0);
        $this->assertDatabaseCount('ingrediente_receta', 0);
    }

    public function test_recipe_is_tenant_scoped_and_non_admin_roles_cannot_write(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $recetaB = Receta::factory()->for($b)->for(Producto::factory()->for($b))->create();
        Sanctum::actingAs($adminA);
        $this->getJson("/api/v1/reposterias/{$a->id}/recetas/{$recetaB->id}")->assertNotFound();
        foreach ([$this->miembro($a, 'produccion'), $this->miembro($a, 'vendedor'), $this->usuario('cliente')] as $actor) {
            Sanctum::actingAs($actor);
            $this->postJson("/api/v1/reposterias/{$a->id}/recetas", ['producto_id' => 1, 'nombre' => 'No', 'rendimiento' => 1])->assertForbidden();
        }
    }

    public function test_movement_listing_filters_paginates_and_hides_foreign_data(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $ingrediente = Ingrediente::factory()->for($reposteria)->create();
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/reposterias/{$reposteria->id}/inventario/movimientos", ['ingrediente_id' => $ingrediente->id, 'tipo' => 'entrada', 'cantidad' => '3.000'])->assertCreated();
        $this->postJson("/api/v1/reposterias/{$reposteria->id}/inventario/movimientos", ['ingrediente_id' => $ingrediente->id, 'tipo' => 'salida', 'cantidad' => '1.000'])->assertCreated();
        MovimientoInventario::factory()->for($this->contexto()[0])->create();
        $url = "/api/v1/reposterias/{$reposteria->id}/inventario/movimientos?ingrediente_id={$ingrediente->id}&tipo=entrada&fecha_desde=".now()->subDay()->toDateString().'&fecha_hasta='.now()->toDateString().'&per_page=1';
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.tipo', 'entrada')->assertJsonPath('data.0.cantidad', '3.000')->assertJsonMissingPath('data.0.reposteria_id');
    }

    public function test_all_movement_types_change_stock_and_create_immutable_history(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $ingrediente = Ingrediente::factory()->for($reposteria)->create();
        Sanctum::actingAs($admin);
        foreach ([['entrada', '10.500', '10.500'], ['salida', '2.250', '8.250'], ['ajuste_positivo', '1.000', '9.250'], ['ajuste_negativo', '0.250', '9.000']] as [$tipo, $cantidad, $stock]) {
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/inventario/movimientos", ['ingrediente_id' => $ingrediente->id, 'tipo' => $tipo, 'cantidad' => $cantidad, 'motivo' => 'Manual'])
                ->assertCreated()->assertJsonPath('data.stock_nuevo', $stock)->assertJsonPath('data.fecha_movimiento', fn ($fecha) => str_ends_with($fecha, 'Z'));
        }
        $this->assertSame('9.000', $ingrediente->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 4);
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/inventario/movimientos/1", [])->assertNotFound();
        $this->deleteJson("/api/v1/reposterias/{$reposteria->id}/inventario/movimientos/1")->assertNotFound();
    }

    public function test_production_only_registers_output_and_vendor_client_are_blocked(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $ingrediente = Ingrediente::factory()->for($reposteria)->create(['stock_actual' => '5.000']);
        $produccion = $this->miembro($reposteria, 'produccion');
        Sanctum::actingAs($produccion);
        $this->postJson("/api/v1/reposterias/{$reposteria->id}/inventario/movimientos", ['ingrediente_id' => $ingrediente->id, 'tipo' => 'salida', 'cantidad' => '1.000'])->assertCreated();
        foreach (['entrada', 'ajuste_positivo', 'ajuste_negativo'] as $tipo) {
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/inventario/movimientos", ['ingrediente_id' => $ingrediente->id, 'tipo' => $tipo, 'cantidad' => '1.000'])->assertForbidden();
        }
        foreach ([$this->miembro($reposteria, 'vendedor'), $this->usuario('cliente')] as $actor) {
            Sanctum::actingAs($actor);
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/inventario/movimientos", ['ingrediente_id' => $ingrediente->id, 'tipo' => 'salida', 'cantidad' => '1.000'])->assertForbidden();
        }
        $this->assertSame('4.000', $ingrediente->fresh()->stock_actual);
        $this->assertTrue($admin->perteneceAReposteria($reposteria));
    }

    public function test_invalid_or_excessive_output_rolls_back_and_foreign_ingredient_is_hidden(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $ingredienteA = Ingrediente::factory()->for($a)->create(['stock_actual' => '1.000']);
        $ingredienteB = Ingrediente::factory()->for($b)->create(['stock_actual' => '5.000']);
        Sanctum::actingAs($adminA);
        foreach (['0.000', '-1.000', '1.001'] as $cantidad) {
            $this->postJson("/api/v1/reposterias/{$a->id}/inventario/movimientos", ['ingrediente_id' => $ingredienteA->id, 'tipo' => 'salida', 'cantidad' => $cantidad])->assertUnprocessable();
        }
        $this->postJson("/api/v1/reposterias/{$a->id}/inventario/movimientos", ['ingrediente_id' => $ingredienteB->id, 'tipo' => 'entrada', 'cantidad' => '1.000'])->assertNotFound();
        $this->getJson("/api/v1/reposterias/{$a->id}/inventario/movimientos?ingrediente_id={$ingredienteB->id}")->assertUnprocessable();
        $this->assertSame('1.000', $ingredienteA->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    private function contexto(): array
    {
        $admin = $this->usuario('admin');
        $reposteria = Reposteria::factory()->for($admin, 'propietario')->create();

        return [app(ReposteriaEstadoService::class)->aprobar($reposteria, $this->usuario('superadmin')), $admin];
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
