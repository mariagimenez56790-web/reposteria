<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\CategoriaService;
use App\Services\ProductoService;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private CategoriaService $categorias;

    private ProductoService $productos;

    private ReposteriaEstadoService $estados;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->categorias = app(CategoriaService::class);
        $this->productos = app(ProductoService::class);
        $this->estados = app(ReposteriaEstadoService::class);
    }

    public function test_catalog_tables_have_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('categorias', ['id', 'reposteria_id', 'nombre', 'descripcion', 'activo', 'deleted_at']));
        $this->assertTrue(Schema::hasColumns('productos', ['id', 'reposteria_id', 'categoria_id', 'nombre', 'descripcion', 'precio', 'imagen', 'personalizable', 'maneja_stock', 'stock', 'activo', 'deleted_at']));
    }

    public function test_admin_creates_active_category_and_relationships_work(): void
    {
        [$reposteria, $admin] = $this->reposteriaAprobada();
        $categoria = $this->categorias->crear($admin, $reposteria, ['nombre' => 'Tortas', 'descripcion' => null]);

        $this->assertTrue($categoria->activo);
        $this->assertTrue($categoria->reposteria->is($reposteria));
        $this->assertTrue($reposteria->categorias->first()->is($categoria));
    }

    public function test_category_name_is_unique_per_reposteria_but_reusable_in_another(): void
    {
        [$a, $adminA] = $this->reposteriaAprobada();
        [$b, $adminB] = $this->reposteriaAprobada();
        $this->categorias->crear($adminA, $a, ['nombre' => 'Tortas']);
        $this->categorias->crear($adminB, $b, ['nombre' => 'Tortas']);
        $this->assertDatabaseCount('categorias', 2);

        $this->expectException(ValidationException::class);
        $this->categorias->crear($adminA, $a, ['nombre' => 'Tortas']);
    }

    public function test_non_admin_internal_roles_and_inactive_admin_cannot_manage_categories(): void
    {
        [$reposteria] = $this->reposteriaAprobada();
        foreach (['vendedor', 'produccion'] as $rol) {
            try {
                $this->categorias->crear($this->miembro($reposteria, $rol), $reposteria, ['nombre' => "Categoría {$rol}"]);
                $this->fail("{$rol} no debió administrar categorías.");
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
        $inactivo = $this->miembro($reposteria, 'admin');
        $inactivo->update(['activo' => false]);
        $this->expectException(AuthorizationException::class);
        $this->categorias->crear($inactivo, $reposteria, ['nombre' => 'Bloqueada']);
    }

    public function test_admin_cannot_manage_category_of_another_reposteria(): void
    {
        [$a, $adminA] = $this->reposteriaAprobada();
        [$b] = $this->reposteriaAprobada();
        $this->expectException(AuthorizationException::class);
        $this->categorias->crear($adminA, $b, ['nombre' => 'Ajena']);
    }

    public function test_category_can_be_updated_toggled_and_soft_deleted_without_products(): void
    {
        [$r, $admin] = $this->reposteriaAprobada();
        $categoria = $this->categorias->crear($admin, $r, ['nombre' => 'Tortas']);
        $categoria = $this->categorias->actualizar($admin, $categoria, ['nombre' => 'Pasteles', 'descripcion' => 'Nuevos']);
        $this->assertSame('Pasteles', $categoria->nombre);
        $this->assertFalse($this->categorias->establecerActiva($admin, $categoria, false)->activo);
        $this->categorias->eliminar($admin, $categoria);
        $this->assertSoftDeleted('categorias', ['id' => $categoria->id]);
    }

    public function test_valid_product_can_have_no_category_and_uses_expected_casts(): void
    {
        [$r, $admin] = $this->reposteriaAprobada();
        $producto = $this->productos->crear($admin, $r, $this->datosProducto(['precio' => '125.50', 'personalizable' => true]));

        $this->assertSame($r->id, $producto->reposteria_id);
        $this->assertNull($producto->categoria_id);
        $this->assertSame('125.50', $producto->precio);
        $this->assertTrue($producto->personalizable);
        $this->assertTrue($producto->activo);
        $this->assertTrue($producto->reposteria->is($r));
    }

    public function test_product_can_use_category_only_from_same_reposteria(): void
    {
        [$a, $adminA] = $this->reposteriaAprobada();
        [$b, $adminB] = $this->reposteriaAprobada();
        $categoriaA = $this->categorias->crear($adminA, $a, ['nombre' => 'Tortas']);
        $categoriaB = $this->categorias->crear($adminB, $b, ['nombre' => 'Galletas']);
        $producto = $this->productos->crear($adminA, $a, $this->datosProducto(['categoria_id' => $categoriaA->id]));
        $this->assertTrue($producto->categoria->is($categoriaA));
        $this->assertTrue($categoriaA->productos->first()->is($producto));

        $this->expectException(ValidationException::class);
        $this->productos->crear($adminA, $a, $this->datosProducto(['categoria_id' => $categoriaB->id]));
    }

    public function test_negative_stock_and_invalid_price_are_rejected(): void
    {
        [$r, $admin] = $this->reposteriaAprobada();
        foreach ([['stock' => -1], ['precio' => -0.01]] as $cambio) {
            try {
                $this->productos->crear($admin, $r, $this->datosProducto($cambio));
                $this->fail('El valor inválido no debió aceptarse.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_no_stock_control_allows_zero_stock(): void
    {
        [$r, $admin] = $this->reposteriaAprobada();
        $producto = $this->productos->crear($admin, $r, $this->datosProducto(['maneja_stock' => false, 'stock' => 0]));
        $this->assertFalse($producto->maneja_stock);
        $this->assertSame(0, $producto->stock);
    }

    public function test_vendor_production_and_inactive_admin_cannot_create_products(): void
    {
        [$r] = $this->reposteriaAprobada();
        foreach (['vendedor', 'produccion'] as $rol) {
            try {
                $this->productos->crear($this->miembro($r, $rol), $r, $this->datosProducto());
                $this->fail("{$rol} no debió crear productos.");
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
        $admin = $this->miembro($r, 'admin');
        $admin->update(['activo' => false]);
        $this->expectException(AuthorizationException::class);
        $this->productos->crear($admin, $r, $this->datosProducto());
    }

    public function test_admin_cannot_update_product_of_another_reposteria_or_move_ids_by_mass_input(): void
    {
        [$a, $adminA] = $this->reposteriaAprobada();
        [$b, $adminB] = $this->reposteriaAprobada();
        $productoB = $this->productos->crear($adminB, $b, $this->datosProducto());
        $this->expectException(AuthorizationException::class);
        $this->productos->actualizar($adminA, $productoB, $this->datosProducto(['reposteria_id' => $a->id]));
    }

    public function test_category_with_product_is_not_deleted_and_product_uses_soft_delete(): void
    {
        [$r, $admin] = $this->reposteriaAprobada();
        $categoria = $this->categorias->crear($admin, $r, ['nombre' => 'Tortas']);
        $producto = $this->productos->crear($admin, $r, $this->datosProducto(['categoria_id' => $categoria->id]));
        try {
            $this->categorias->eliminar($admin, $categoria);
            $this->fail('La categoría con productos no debió eliminarse.');
        } catch (DomainException) {
            $this->assertNotNull($categoria->fresh());
        }
        $this->productos->eliminar($admin, $producto);
        $this->assertSoftDeleted('productos', ['id' => $producto->id]);
        $this->assertNotNull(Producto::withTrashed()->find($producto->id));
        $this->categorias->eliminar($admin, $categoria);
        $this->assertSoftDeleted('categorias', ['id' => $categoria->id]);
    }

    private function reposteriaAprobada(): array
    {
        $admin = $this->usuario('admin');
        $r = Reposteria::factory()->for($admin, 'propietario')->create();

        return [$this->estados->aprobar($r, $this->usuario('superadmin')), $admin];
    }

    private function miembro(Reposteria $r, string $rol): User
    {
        $user = $this->usuario($rol);
        $r->usuarios()->attach($user);

        return $user;
    }

    private function usuario(string $rol): User
    {
        return User::factory()->for(Role::where('nombre', $rol)->firstOrFail())->create();
    }

    private function datosProducto(array $cambios = []): array
    {
        return array_merge(['nombre' => 'Torta de chocolate', 'descripcion' => null, 'precio' => '100.00', 'imagen' => null, 'personalizable' => false, 'maneja_stock' => true, 'stock' => 5, 'categoria_id' => null], $cambios);
    }
}
