<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Promocion;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_catalog_routes_require_authentication_and_active_user(): void
    {
        foreach (['/api/v1/reposterias', '/api/v1/reposterias/1/categorias', '/api/v1/reposterias/1/productos', '/api/v1/reposterias/1/productos/1'] as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
        }
        $inactivo = $this->usuario('admin', false);
        Sanctum::actingAs($inactivo);
        $this->getJson('/api/v1/reposterias')->assertForbidden();
    }

    public function test_employees_only_receive_approved_associated_bakeries(): void
    {
        foreach (['admin', 'vendedor', 'produccion'] as $rol) {
            $usuario = $this->usuario($rol);
            $propia = $this->reposteria($usuario, true);
            $this->reposteria($usuario, false);
            $ajena = $this->reposteria($this->usuario('admin'), true);
            Sanctum::actingAs($usuario);
            $this->getJson('/api/v1/reposterias')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $propia->id)->assertJsonMissing(['id' => $ajena->id]);
        }
    }

    public function test_superadmin_gets_paginated_approved_bakeries_without_membership(): void
    {
        $super = $this->usuario('superadmin');
        $this->reposteria($this->usuario('admin'), true);
        $this->reposteria($this->usuario('admin'), true);
        $this->reposteria($this->usuario('admin'), false);
        Sanctum::actingAs($super);
        $this->getJson('/api/v1/reposterias?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.per_page', 1)->assertJsonPath('meta.total', 2);
    }

    public function test_categories_only_return_active_non_deleted_own_records(): void
    {
        [$reposteria, $admin] = $this->contexto();
        Categoria::factory()->for($reposteria)->create(['nombre' => 'Tortas', 'activo' => true]);
        Categoria::factory()->for($reposteria)->create(['nombre' => 'Oculta', 'activo' => false]);
        $eliminada = Categoria::factory()->for($reposteria)->create(['activo' => true]);
        $eliminada->delete();
        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/categorias")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Tortas')->assertJsonMissingPath('data.0.reposteria_id');
    }

    public function test_an_employee_cannot_read_another_bakery_catalog_and_client_is_blocked(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        foreach ([$adminA, $this->miembro($a, 'vendedor'), $this->miembro($a, 'produccion'), $this->usuario('cliente')] as $actor) {
            Sanctum::actingAs($actor);
            $this->getJson("/api/v1/reposterias/{$b->id}/productos")->assertForbidden();
        }
    }

    public function test_products_are_filtered_searched_sorted_and_paginated_safely(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $tortas = Categoria::factory()->for($reposteria)->create();
        $panes = Categoria::factory()->for($reposteria)->create();
        Producto::factory()->for($reposteria)->conCategoria($tortas)->create(['nombre' => 'Torta Chocolate']);
        Producto::factory()->for($reposteria)->conCategoria($tortas)->create(['nombre' => 'Torta Vainilla']);
        Producto::factory()->for($reposteria)->conCategoria($panes)->create(['nombre' => 'Pan']);
        Producto::factory()->for($reposteria)->create(['nombre' => 'Inactivo', 'activo' => false]);
        $eliminado = Producto::factory()->for($reposteria)->create(['activo' => true]);
        $eliminado->delete();
        Sanctum::actingAs($admin);
        $respuesta = $this->getJson("/api/v1/reposterias/{$reposteria->id}/productos?categoria_id={$tortas->id}&search=Torta&per_page=1");
        $respuesta->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.nombre', 'Torta Chocolate');
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/productos?per_page=101")->assertUnprocessable();
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/productos?search=".str_repeat('a', 101))->assertUnprocessable();
    }

    public function test_foreign_category_filter_is_rejected_without_exposing_data(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $categoriaB = Categoria::factory()->for($b)->create();
        Sanctum::actingAs($adminA);
        $this->getJson("/api/v1/reposterias/{$a->id}/productos?categoria_id={$categoriaB->id}")->assertUnprocessable()->assertJsonValidationErrors('categoria_id');
    }

    public function test_product_detail_is_tenant_scoped_and_hides_unavailable_variants(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $producto = Producto::factory()->for($a)->create(['precio' => '99.90', 'maneja_stock' => true, 'stock' => 7]);
        ProductoVariante::factory()->for($producto)->create(['nombre' => 'Grande', 'precio' => '120.00', 'stock' => 3]);
        ProductoVariante::factory()->for($producto)->create(['nombre' => 'Mediana', 'activo' => false]);
        $eliminada = ProductoVariante::factory()->for($producto)->create(['nombre' => 'Pequeña', 'activo' => true]);
        $eliminada->delete();
        $ajeno = Producto::factory()->for($b)->create();
        Sanctum::actingAs($adminA);
        $this->getJson("/api/v1/reposterias/{$a->id}/productos/{$producto->id}")->assertOk()->assertJsonPath('data.precio', '99.90')->assertJsonPath('data.maneja_stock', true)->assertJsonPath('data.stock', 7)->assertJsonCount(1, 'data.variantes')->assertJsonPath('data.variantes.0.precio', '120.00')->assertJsonPath('data.variantes.0.stock', 3);
        $this->getJson("/api/v1/reposterias/{$a->id}/productos/{$ajeno->id}")->assertNotFound();
    }

    public function test_product_and_variant_promotions_are_serialized_with_priority_without_accumulation(): void
    {
        [$reposteria, $vendedor] = $this->contexto('vendedor');
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '100.00']);
        $variante = ProductoVariante::factory()->for($producto)->create(['precio' => '120.00']);
        $productoPromo = Promocion::factory()->for($reposteria)->montoFijo('20.00')->create(['nombre' => 'Producto']);
        $variantePromo = Promocion::factory()->for($reposteria)->porcentaje('5.00')->create(['nombre' => 'Variante']);
        $productoPromo->productos()->attach($producto);
        $variantePromo->variantes()->attach($variante);
        Sanctum::actingAs($vendedor);
        $respuesta = $this->getJson("/api/v1/reposterias/{$reposteria->id}/productos/{$producto->id}");
        $respuesta->assertOk()->assertJsonPath('data.precio_final', '80.00')->assertJsonPath('data.promocion.nombre', 'Producto')->assertJsonPath('data.variantes.0.precio_final', '114.00')->assertJsonPath('data.variantes.0.promocion.nombre', 'Variante');
    }

    public function test_no_promotion_returns_same_string_price_and_no_mutation_occurs(): void
    {
        [$reposteria, $produccion] = $this->contexto('produccion');
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '25.50', 'stock' => 9]);
        Sanctum::actingAs($produccion);
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/productos")->assertOk()->assertJsonPath('data.0.precio', '25.50')->assertJsonPath('data.0.precio_final', '25.50')->assertJsonPath('data.0.promocion', null);
        $this->assertSame('25.50', $producto->fresh()->precio);
        $this->assertSame(9, $producto->fresh()->stock);
    }

    private function contexto(string $rol = 'admin'): array
    {
        $usuario = $this->usuario($rol);

        return [$this->reposteria($usuario, true), $usuario];
    }

    private function usuario(string $rol, bool $activo = true): User
    {
        return User::factory()->for(Role::query()->where('nombre', $rol)->firstOrFail())->create(['activo' => $activo]);
    }

    private function reposteria(User $usuario, bool $aprobar): Reposteria
    {
        $reposteria = Reposteria::factory()->for($usuario, 'propietario')->create();

        return $aprobar ? app(ReposteriaEstadoService::class)->aprobar($reposteria, $this->usuario('superadmin')) : $reposteria;
    }

    private function miembro(Reposteria $reposteria, string $rol): User
    {
        $usuario = $this->usuario($rol);
        $reposteria->usuarios()->attach($usuario);

        return $usuario;
    }
}
