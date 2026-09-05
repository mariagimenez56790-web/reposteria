<?php

namespace Tests\Feature;

use App\Models\ProductoVariante;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductoService;
use App\Services\ProductoVarianteService;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductoVarianteTest extends TestCase
{
    use RefreshDatabase;

    private ProductoVarianteService $variantes;

    private ProductoService $productos;

    private ReposteriaEstadoService $estados;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->variantes = app(ProductoVarianteService::class);
        $this->productos = app(ProductoService::class);
        $this->estados = app(ReposteriaEstadoService::class);
    }

    public function test_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('producto_variantes', ['id', 'producto_id', 'nombre', 'precio', 'stock', 'activo', 'created_at', 'updated_at', 'deleted_at']));
    }

    public function test_admin_creates_active_variants_with_relationships_and_decimal_price(): void
    {
        [$producto, $admin] = $this->productoValido();
        $pequena = $this->variantes->crear($admin, $producto, $this->datos(['nombre' => 'Pequeña', 'precio' => '80.00']));
        $grande = $this->variantes->crear($admin, $producto, $this->datos(['nombre' => 'Grande', 'precio' => '180.50']));

        $this->assertTrue($pequena->activo);
        $this->assertSame('80.00', $pequena->precio);
        $this->assertTrue($pequena->producto->is($producto));
        $this->assertCount(2, $producto->variantes);
        $this->assertTrue($producto->variantes->contains($grande));
    }

    public function test_name_is_unique_per_product_including_soft_deleted_but_reusable_elsewhere(): void
    {
        [$a, $adminA] = $this->productoValido();
        [$b, $adminB] = $this->productoValido();
        $variante = $this->variantes->crear($adminA, $a, $this->datos(['nombre' => 'Grande']));
        $this->variantes->crear($adminB, $b, $this->datos(['nombre' => 'Grande']));
        $this->variantes->eliminar($adminA, $variante);
        $this->assertDatabaseCount('producto_variantes', 2);

        $this->expectException(ValidationException::class);
        $this->variantes->crear($adminA, $a, $this->datos(['nombre' => 'Grande']));
    }

    public function test_negative_stock_and_invalid_price_are_rejected(): void
    {
        [$producto, $admin] = $this->productoValido();
        foreach ([['stock' => -1], ['precio' => -1]] as $cambio) {
            try {
                $this->variantes->crear($admin, $producto, $this->datos($cambio));
                $this->fail('El valor inválido no debió aceptarse.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_variant_can_be_updated_deactivated_and_reactivated_without_moving_product(): void
    {
        [$producto, $admin] = $this->productoValido();
        [$otroProducto] = $this->productoValido();
        $variante = $this->variantes->crear($admin, $producto, $this->datos());
        $variante = $this->variantes->actualizar($admin, $variante, $this->datos(['nombre' => 'Familiar', 'producto_id' => $otroProducto->id]));

        $this->assertSame($producto->id, $variante->producto_id);
        $this->assertFalse($this->variantes->establecerActiva($admin, $variante, false)->activo);
        $this->assertTrue($this->variantes->establecerActiva($admin, $variante, true)->activo);
    }

    public function test_soft_delete_preserves_variant_history(): void
    {
        [$producto, $admin] = $this->productoValido();
        $variante = $this->variantes->crear($admin, $producto, $this->datos());
        $this->variantes->eliminar($admin, $variante);

        $this->assertSoftDeleted('producto_variantes', ['id' => $variante->id]);
        $this->assertNull(ProductoVariante::find($variante->id));
        $this->assertNotNull(ProductoVariante::withTrashed()->find($variante->id));
    }

    public function test_admin_from_another_reposteria_and_internal_non_admin_roles_are_denied(): void
    {
        [$producto] = $this->productoValido();
        [, $otroAdmin] = $this->productoValido();
        foreach ([$otroAdmin, $this->miembro($producto->reposteria, 'vendedor'), $this->miembro($producto->reposteria, 'produccion'), $this->usuario('cliente')] as $actor) {
            try {
                $this->variantes->crear($actor, $producto, $this->datos(['nombre' => fake()->unique()->word()]));
                $this->fail('El usuario no debió administrar variantes.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_inactive_admin_is_denied_and_superadmin_is_allowed(): void
    {
        [$producto, $admin] = $this->productoValido();
        $admin->update(['activo' => false]);
        try {
            $this->variantes->crear($admin, $producto, $this->datos());
            $this->fail('El admin inactivo no debió operar.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
        $variante = $this->variantes->crear($this->usuario('superadmin'), $producto, $this->datos());
        $this->assertNotNull($variante->id);
    }

    public function test_deleted_or_inactive_product_blocks_variant_administration_without_deleting_variants(): void
    {
        [$producto, $admin] = $this->productoValido();
        $variante = $this->variantes->crear($admin, $producto, $this->datos());
        $this->productos->establecerActivo($admin, $producto, false);
        try {
            $this->variantes->actualizar($admin, $variante, $this->datos());
            $this->fail('El producto inactivo debió bloquear la variante.');
        } catch (DomainException) {
            $this->assertNotNull($variante->fresh());
        }
        $producto->forceFill(['activo' => true])->save();
        $this->productos->eliminar($admin, $producto);
        $this->expectException(DomainException::class);
        $this->variantes->actualizar($admin, $variante, $this->datos());
    }

    private function productoValido(): array
    {
        $admin = $this->usuario('admin');
        $reposteria = Reposteria::factory()->for($admin, 'propietario')->create();
        $reposteria = $this->estados->aprobar($reposteria, $this->usuario('superadmin'));
        $producto = $this->productos->crear($admin, $reposteria, ['nombre' => 'Torta', 'descripcion' => null, 'precio' => '100.00', 'imagen' => null, 'personalizable' => false, 'maneja_stock' => true, 'stock' => 10, 'categoria_id' => null]);

        return [$producto, $admin];
    }

    private function miembro(Reposteria $reposteria, string $rol): User
    {
        $user = $this->usuario($rol);
        $reposteria->usuarios()->attach($user);

        return $user;
    }

    private function usuario(string $rol): User
    {
        return User::factory()->for(Role::where('nombre', $rol)->firstOrFail())->create();
    }

    private function datos(array $cambios = []): array
    {
        return array_merge(['nombre' => 'Mediana', 'precio' => '120.00', 'stock' => 5], $cambios);
    }
}
