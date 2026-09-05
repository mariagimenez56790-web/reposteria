<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Promocion;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\PromocionService;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromocionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_promotion_routes_require_authentication_and_active_user(): void
    {
        $this->getJson('/api/v1/reposterias/1/promociones')->assertUnauthorized();
        [$reposteria] = $this->contexto();
        Sanctum::actingAs($this->usuario('admin', false));
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/promociones")->assertForbidden();
    }

    public function test_only_admin_and_superadmin_can_list_and_manage_promotions(): void
    {
        [$reposteria, $admin] = $this->contexto();
        Promocion::factory()->for($reposteria)->create();
        foreach ([$admin, $this->usuario('superadmin')] as $actor) {
            Sanctum::actingAs($actor);
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/promociones")->assertOk()->assertJsonPath('meta.total', 1);
        }
        foreach ([$this->miembro($reposteria, 'produccion'), $this->miembro($reposteria, 'vendedor'), $this->usuario('cliente')] as $actor) {
            Sanctum::actingAs($actor);
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/promociones")->assertForbidden();
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/promociones", $this->datos())->assertForbidden();
        }
    }

    public function test_listing_filters_paginates_and_is_tenant_scoped(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create();
        $variante = ProductoVariante::factory()->for($producto)->create();
        $vigente = Promocion::factory()->for($reposteria)->porcentaje('15.00')->create();
        $vigente->productos()->attach($producto);
        $vigente->variantes()->attach($variante);
        Promocion::factory()->for($reposteria)->inactiva()->create();
        Promocion::factory()->for($this->contexto()[0])->create();
        Sanctum::actingAs($admin);
        $url = "/api/v1/reposterias/{$reposteria->id}/promociones?activo=1&tipo_descuento=porcentaje&vigente=1&producto_id={$producto->id}&variante_id={$variante->id}&fecha_desde=".now()->subDays(2)->toDateString().'&fecha_hasta='.now()->addDays(2)->toDateString().'&per_page=1';
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $vigente->id)->assertJsonPath('data.0.vigente', true)->assertJsonMissingPath('data.0.reposteria_id');
    }

    public function test_foreign_product_and_variant_filters_are_rejected(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $productoB = Producto::factory()->for($b)->create();
        $varianteB = ProductoVariante::factory()->for($productoB)->create();
        Sanctum::actingAs($adminA);
        $this->getJson("/api/v1/reposterias/{$a->id}/promociones?producto_id={$productoB->id}")->assertUnprocessable();
        $this->getJson("/api/v1/reposterias/{$a->id}/promociones?variante_id={$varianteB->id}")->assertUnprocessable();
    }

    public function test_detail_loads_safe_products_and_variants_and_hides_foreign_promotion(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $producto = Producto::factory()->for($a)->create(['precio' => '100.00']);
        $variante = ProductoVariante::factory()->for($producto)->create(['precio' => '120.00']);
        $promocion = Promocion::factory()->for($a)->create();
        $promocion->productos()->attach($producto);
        $promocion->variantes()->attach($variante);
        $ajena = Promocion::factory()->for($b)->create();
        Sanctum::actingAs($adminA);
        $this->getJson("/api/v1/reposterias/{$a->id}/promociones/{$promocion->id}")->assertOk()->assertJsonPath('data.valor_descuento', '10.00')->assertJsonPath('data.productos.0.id', $producto->id)->assertJsonPath('data.variantes.0.id', $variante->id)->assertJsonPath('data.productos.0.precio', '100.00');
        $this->getJson("/api/v1/reposterias/{$a->id}/promociones/{$ajena->id}")->assertNotFound();
    }

    public function test_admin_creates_percentage_fixed_and_superadmin_creates_globally(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '100.00']);
        $variante = ProductoVariante::factory()->for($producto)->create(['precio' => '120.00']);
        foreach ([[$admin, 'porcentaje', '15.00'], [$admin, 'monto_fijo', '20.00'], [$this->usuario('superadmin'), 'porcentaje', '5.00']] as [$actor, $tipo, $valor]) {
            Sanctum::actingAs($actor);
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/promociones", $this->datos(['tipo_descuento' => $tipo, 'valor_descuento' => $valor, 'producto_ids' => [$producto->id], 'variante_ids' => [$variante->id]]))
                ->assertCreated()->assertJsonPath('data.tipo_descuento', $tipo)->assertJsonPath('data.valor_descuento', $valor)->assertJsonCount(1, 'data.productos')->assertJsonCount(1, 'data.variantes');
        }
    }

    public function test_invalid_values_dates_and_fixed_discount_roll_back_creation(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '10.00']);
        Sanctum::actingAs($admin);
        foreach ([
            ['valor_descuento' => '0.00'],
            ['valor_descuento' => '-1.00'],
            ['valor_descuento' => '100.01'],
            ['tipo_descuento' => 'monto_fijo', 'valor_descuento' => '0.00'],
            ['tipo_descuento' => 'monto_fijo', 'valor_descuento' => '-1.00'],
            ['tipo_descuento' => 'monto_fijo', 'valor_descuento' => '10.01', 'producto_ids' => [$producto->id]],
            ['fecha_inicio' => now()->addDay()->toISOString(), 'fecha_fin' => now()->toISOString()],
        ] as $cambios) {
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/promociones", $this->datos($cambios))->assertUnprocessable();
        }
        $this->assertDatabaseCount('promociones', 0);
    }

    public function test_creation_rejects_foreign_inactive_or_wrong_tenant_associations(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $productoA = Producto::factory()->for($a)->create();
        $productoB = Producto::factory()->for($b)->create();
        $inactivo = Producto::factory()->for($a)->create(['activo' => false]);
        $varianteB = ProductoVariante::factory()->for($productoB)->create();
        $varianteInactiva = ProductoVariante::factory()->for($productoA)->create(['activo' => false]);
        Sanctum::actingAs($adminA);
        foreach ([['producto_ids' => [$productoB->id]], ['producto_ids' => [$inactivo->id]], ['variante_ids' => [$varianteB->id]], ['variante_ids' => [$varianteInactiva->id]]] as $asociaciones) {
            $this->postJson("/api/v1/reposterias/{$a->id}/promociones", $this->datos($asociaciones))->assertUnprocessable();
        }
        $this->assertDatabaseCount('promociones', 0);
    }

    public function test_patch_is_partial_updates_active_dates_value_and_associations(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '100.00']);
        $variante = ProductoVariante::factory()->for($producto)->create(['precio' => '120.00']);
        $promocion = Promocion::factory()->for($reposteria)->create(['nombre' => 'Anterior']);
        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/promociones/{$promocion->id}", ['nombre' => 'Nueva'])->assertOk()->assertJsonPath('data.nombre', 'Nueva')->assertJsonPath('data.valor_descuento', '10.00');
        $inicio = now()->subDays(2)->startOfSecond();
        $fin = now()->addDays(2)->startOfSecond();
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/promociones/{$promocion->id}", ['activo' => false, 'valor_descuento' => '20.00', 'fecha_inicio' => $inicio->toISOString(), 'fecha_fin' => $fin->toISOString(), 'producto_ids' => [$producto->id], 'variante_ids' => [$variante->id]])
            ->assertOk()->assertJsonPath('data.activo', false)->assertJsonPath('data.valor_descuento', '20.00')->assertJsonCount(1, 'data.productos')->assertJsonCount(1, 'data.variantes');
    }

    public function test_patch_rejects_foreign_associations_without_losing_existing_ones(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $productoA = Producto::factory()->for($a)->create();
        $productoB = Producto::factory()->for($b)->create();
        $varianteB = ProductoVariante::factory()->for($productoB)->create();
        $promocion = Promocion::factory()->for($a)->create();
        $promocion->productos()->attach($productoA);
        Sanctum::actingAs($adminA);
        $this->patchJson("/api/v1/reposterias/{$a->id}/promociones/{$promocion->id}", ['producto_ids' => [$productoB->id]])->assertUnprocessable();
        $this->patchJson("/api/v1/reposterias/{$a->id}/promociones/{$promocion->id}", ['variante_ids' => [$varianteB->id]])->assertUnprocessable();
        $this->assertTrue($promocion->productos()->whereKey($productoA->id)->exists());
    }

    public function test_priority_fallback_non_accumulation_best_price_and_dynamic_catalog_price_remain_unchanged(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '100.00']);
        $variante = ProductoVariante::factory()->for($producto)->create(['precio' => '120.00']);
        $producto10 = Promocion::factory()->for($reposteria)->porcentaje('10.00')->create();
        $producto20 = Promocion::factory()->for($reposteria)->montoFijo('20.00')->create();
        $producto10->productos()->attach($producto);
        $producto20->productos()->attach($producto);
        $servicio = app(PromocionService::class);
        $this->assertSame('100.00', $servicio->calcularPrecioPromocional($admin, $producto, $variante)['precio_final']);
        $variante5 = Promocion::factory()->for($reposteria)->porcentaje('5.00')->create();
        $variante5->variantes()->attach($variante);
        $this->assertSame('114.00', $servicio->calcularPrecioPromocional($admin, $producto, $variante)['precio_final']);
        $this->assertSame('100.00', $producto->fresh()->precio);
        $this->assertSame('120.00', $variante->fresh()->precio);
    }

    public function test_inactive_future_expired_and_deleted_promotions_do_not_apply(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '100.00']);
        foreach ([Promocion::factory()->for($reposteria)->inactiva()->create(), Promocion::factory()->for($reposteria)->futura()->create(), Promocion::factory()->for($reposteria)->vencida()->create()] as $promocion) {
            $promocion->productos()->attach($producto);
        }
        $eliminada = Promocion::factory()->for($reposteria)->create();
        $eliminada->productos()->attach($producto);
        $eliminada->delete();
        $this->assertNull(app(PromocionService::class)->calcularPrecioPromocional($admin, $producto)['promocion_id']);
    }

    public function test_admin_and_superadmin_soft_delete_preserves_relations_and_other_roles_are_blocked(): void
    {
        foreach (['admin', 'superadmin'] as $rol) {
            [$reposteria, $admin] = $this->contexto();
            $actor = $rol === 'admin' ? $admin : $this->usuario('superadmin');
            $producto = Producto::factory()->for($reposteria)->create();
            $promocion = Promocion::factory()->for($reposteria)->create();
            $promocion->productos()->attach($producto);
            Sanctum::actingAs($actor);
            $this->deleteJson("/api/v1/reposterias/{$reposteria->id}/promociones/{$promocion->id}")->assertNoContent();
            $this->assertSoftDeleted('promociones', ['id' => $promocion->id]);
            $this->assertDatabaseHas('producto_promocion', ['promocion_id' => $promocion->id, 'producto_id' => $producto->id]);
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/promociones")->assertJsonPath('meta.total', 0);
        }

        [$reposteria] = $this->contexto();
        $promocion = Promocion::factory()->for($reposteria)->create();
        foreach ([$this->miembro($reposteria, 'vendedor'), $this->miembro($reposteria, 'produccion'), $this->usuario('cliente')] as $actor) {
            Sanctum::actingAs($actor);
            $this->deleteJson("/api/v1/reposterias/{$reposteria->id}/promociones/{$promocion->id}")->assertForbidden();
        }
    }

    public function test_promotion_changes_neither_stock_nor_inventory_history(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['stock' => 8]);
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/reposterias/{$reposteria->id}/promociones", $this->datos(['producto_ids' => [$producto->id]]))->assertCreated();
        $this->assertSame(8, $producto->fresh()->stock);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    private function datos(array $cambios = []): array
    {
        return array_replace([
            'nombre' => fake()->words(2, true),
            'descripcion' => null,
            'tipo_descuento' => 'porcentaje',
            'valor_descuento' => '10.00',
            'fecha_inicio' => now()->subHour()->toISOString(),
            'fecha_fin' => now()->addHour()->toISOString(),
        ], $cambios);
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
