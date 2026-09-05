<?php

namespace Tests\Feature;

use App\Enums\PromocionTipoDescuento;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Promocion;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\PromocionService;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PromocionManagementTest extends TestCase
{
    use RefreshDatabase;

    private PromocionService $promociones;

    private ReposteriaEstadoService $estados;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->promociones = app(PromocionService::class);
        $this->estados = app(ReposteriaEstadoService::class);
    }

    public function test_promotion_tables_and_relationships_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('promociones', ['reposteria_id', 'nombre', 'descripcion', 'tipo_descuento', 'valor_descuento', 'fecha_inicio', 'fecha_fin', 'activo', 'deleted_at']));
        $this->assertTrue(Schema::hasColumns('producto_promocion', ['id', 'promocion_id', 'producto_id']));
        $this->assertTrue(Schema::hasColumns('producto_variante_promocion', ['id', 'promocion_id', 'producto_variante_id']));
    }

    public function test_creation_validates_name_type_value_and_dates(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $promocion = $this->crear($admin, $reposteria);
        $this->assertTrue($promocion->reposteria->is($reposteria));
        $this->assertSame(PromocionTipoDescuento::Porcentaje, $promocion->tipo_descuento);
        $this->assertTrue($promocion->activo);
        foreach ([['nombre' => ''], ['tipo_descuento' => 'inventado'], ['valor_descuento' => 0], ['valor_descuento' => 101], ['fecha_fin' => now()->subDays(2)]] as $cambio) {
            try {
                $this->promociones->crear($admin, $reposteria, $cambio + $this->datos());
                $this->fail();
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_percentage_and_fixed_amount_use_safe_monetary_calculation(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '99.90']);
        $porcentaje = $this->crear($admin, $reposteria, ['valor_descuento' => '15.00']);
        $this->promociones->asociarProducto($admin, $porcentaje, $producto);
        $resultado = $this->promociones->calcularPrecioPromocional($admin, $producto);
        $this->assertSame('99.90', $resultado['precio_base']);
        $this->assertSame('14.99', $resultado['descuento']);
        $this->assertSame('84.91', $resultado['precio_final']);

        $fija = $this->crear($admin, $reposteria, ['tipo_descuento' => 'monto_fijo', 'valor_descuento' => '20.00']);
        $this->promociones->asociarProducto($admin, $fija, $producto);
        $this->assertSame('79.90', $this->promociones->calcularPrecioPromocional($admin, $producto)['precio_final']);
    }

    public function test_fixed_discount_cannot_exceed_target_price(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '10.00']);
        $promocion = $this->crear($admin, $reposteria, ['tipo_descuento' => 'monto_fijo', 'valor_descuento' => '10.01']);
        $this->expectException(ValidationException::class);
        $this->promociones->asociarProducto($admin, $promocion, $producto);
    }

    public function test_vigency_inactive_and_soft_deleted_promotions_do_not_apply(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '100.00']);
        foreach ([Promocion::factory()->for($reposteria)->futura()->create(), Promocion::factory()->for($reposteria)->vencida()->create(), Promocion::factory()->for($reposteria)->inactiva()->create()] as $promocion) {
            $promocion->productos()->attach($producto);
        }
        $eliminada = Promocion::factory()->for($reposteria)->create();
        $eliminada->productos()->attach($producto);
        $this->promociones->eliminar($admin, $eliminada);
        $this->assertSame(0, Promocion::vigentes()->count());
        $this->assertNull($this->promociones->calcularPrecioPromocional($admin, $producto)['promocion_id']);
    }

    public function test_variant_priority_product_fallback_and_best_same_level(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '100.00']);
        $variante = ProductoVariante::factory()->for($producto)->create(['precio' => '120.00']);
        $producto10 = $this->crear($admin, $reposteria, ['valor_descuento' => '10.00']);
        $producto20 = $this->crear($admin, $reposteria, ['tipo_descuento' => 'monto_fijo', 'valor_descuento' => '20.00']);
        $this->promociones->asociarProducto($admin, $producto10, $producto);
        $this->promociones->asociarProducto($admin, $producto20, $producto);
        $this->assertSame('100.00', $this->promociones->calcularPrecioPromocional($admin, $producto, $variante)['precio_final']);
        $variante5 = $this->crear($admin, $reposteria, ['valor_descuento' => '5.00']);
        $this->promociones->asociarVariante($admin, $variante5, $variante);
        $resultado = $this->promociones->calcularPrecioPromocional($admin, $producto, $variante);
        $this->assertSame($variante5->id, $resultado['promocion_id']);
        $this->assertSame('114.00', $resultado['precio_final']);
    }

    public function test_cross_tenant_and_duplicate_associations_are_rejected(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $promocion = $this->crear($adminA, $a);
        $productoA = Producto::factory()->for($a)->create();
        $varianteA = ProductoVariante::factory()->for($productoA)->create();
        $productoB = Producto::factory()->for($b)->create();
        $varianteB = ProductoVariante::factory()->for($productoB)->create();
        $this->promociones->asociarProducto($adminA, $promocion, $productoA);
        $this->promociones->asociarVariante($adminA, $promocion, $varianteA);
        foreach ([fn () => $this->promociones->asociarProducto($adminA, $promocion, $productoA), fn () => $this->promociones->asociarVariante($adminA, $promocion, $varianteA), fn () => $this->promociones->asociarProducto($adminA, $promocion, $productoB), fn () => $this->promociones->asociarVariante($adminA, $promocion, $varianteB)] as $accion) {
            try {
                $accion();
                $this->fail();
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_inactive_or_deleted_catalog_entities_get_no_promotion(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '100.00']);
        $variante = ProductoVariante::factory()->for($producto)->create(['precio' => '120.00']);
        $promocion = $this->crear($admin, $reposteria);
        $this->promociones->asociarProducto($admin, $promocion, $producto);
        $this->promociones->asociarVariante($admin, $promocion, $variante);
        $variante->forceFill(['activo' => false])->save();
        $this->assertNull($this->promociones->calcularPrecioPromocional($admin, $producto, $variante)['promocion_id']);
        $variante->forceFill(['activo' => true])->save();
        $variante->delete();
        $this->assertNull($this->promociones->calcularPrecioPromocional($admin, $producto, $variante)['promocion_id']);
        $producto->forceFill(['activo' => false])->save();
        $this->assertNull($this->promociones->calcularPrecioPromocional($admin, $producto)['promocion_id']);
        $producto->forceFill(['activo' => true])->save();
        $producto->delete();
        $this->assertNull($this->promociones->calcularPrecioPromocional($admin, $producto)['promocion_id']);
    }

    public function test_roles_activation_and_tenant_authorization(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b, $adminB] = $this->contexto();
        $promocionB = $this->crear($adminB, $b);
        $productoB = Producto::factory()->for($b)->create();
        $vendedorB = $this->miembro($b, 'vendedor');
        $this->assertNull($this->promociones->calcularPrecioPromocional($vendedorB, $productoB)['promocion_id']);
        foreach ([$vendedorB, $this->miembro($b, 'produccion'), $this->usuario('cliente'), $this->usuario('admin', false), $adminA] as $actor) {
            try {
                $this->promociones->establecerActiva($actor, $promocionB, false);
                $this->fail();
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertFalse($this->promociones->establecerActiva($this->usuario('superadmin'), $promocionB, false)->activo);
    }

    public function test_associations_can_be_removed_without_deleting_entities(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create();
        $variante = ProductoVariante::factory()->for($producto)->create();
        $promocion = $this->crear($admin, $reposteria);
        $this->promociones->asociarProducto($admin, $promocion, $producto);
        $this->promociones->asociarVariante($admin, $promocion, $variante);
        $this->promociones->quitarProducto($admin, $promocion, $producto);
        $this->promociones->quitarVariante($admin, $promocion, $variante);
        $this->assertSame(0, $promocion->productos()->count());
        $this->assertSame(0, $promocion->variantes()->count());
        $this->assertNotNull($producto->fresh());
        $this->assertNotNull($variante->fresh());
    }

    private function crear(User $actor, Reposteria $reposteria, array $cambios = []): Promocion
    {
        return $this->promociones->crear($actor, $reposteria, $cambios + $this->datos());
    }

    private function datos(): array
    {
        return ['nombre' => fake()->words(2, true), 'descripcion' => null, 'tipo_descuento' => 'porcentaje', 'valor_descuento' => '10.00', 'fecha_inicio' => now()->subHour(), 'fecha_fin' => now()->addHour()];
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
