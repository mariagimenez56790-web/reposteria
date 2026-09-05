<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RolesAndUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_and_users_tables_have_the_expected_structure(): void
    {
        $this->assertTrue(Schema::hasColumns('roles', [
            'id', 'nombre', 'descripcion', 'ambito', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('users', [
            'id', 'role_id', 'name', 'email', 'email_verified_at', 'password',
            'activo', 'remember_token', 'created_at', 'updated_at',
        ]));
    }

    public function test_role_seeder_creates_the_five_roles_and_is_idempotent(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertDatabaseCount('roles', 5);
        $this->assertSame(
            ['admin', 'cliente', 'produccion', 'superadmin', 'vendedor'],
            Role::query()->orderBy('nombre')->pluck('nombre')->all(),
        );
        $this->assertDatabaseHas('roles', ['nombre' => 'superadmin', 'ambito' => 'sistema']);
        $this->assertSame(4, Role::query()->where('ambito', 'reposteria')->count());
    }

    public function test_user_and_role_relationships_and_activo_cast_work(): void
    {
        $this->seed(RoleSeeder::class);
        $role = Role::query()->where('nombre', 'vendedor')->firstOrFail();
        $user = User::factory()->for($role)->create(['activo' => false]);

        $this->assertTrue($user->role->is($role));
        $this->assertTrue($role->users()->firstOrFail()->is($user));
        $this->assertFalse($user->activo);

        $user->password = 'valor-temporal-de-prueba';
        $user->save();
        $this->assertTrue(Hash::check('valor-temporal-de-prueba', $user->password));
    }

    public function test_role_id_is_required_and_enforced_as_a_foreign_key(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'role_id' => 999999,
            'name' => 'Usuario inválido',
            'email' => 'invalid@example.com',
            'password' => Hash::make('valor-invalido-de-prueba'),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_superadmin_is_created_only_from_configured_credentials(): void
    {
        $this->seed(RoleSeeder::class);
        config()->set('superadmin.email', 'superadmin@example.test');
        config()->set('superadmin.password', 'credencial-temporal-de-prueba');
        config()->set('superadmin.name', 'Superadmin de prueba');

        $this->seed(SuperadminSeeder::class);

        $user = User::query()->where('email', 'superadmin@example.test')->firstOrFail();
        $this->assertSame('superadmin', $user->role->nombre);
        $this->assertTrue($user->activo);
        $this->assertTrue(Hash::check('credencial-temporal-de-prueba', $user->password));
    }
}
