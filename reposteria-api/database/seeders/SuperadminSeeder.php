<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('superadmin.email');
        $password = config('superadmin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn(
                'Superadmin no creado: configura SUPERADMIN_EMAIL y SUPERADMIN_PASSWORD en .env.',
            );

            return;
        }

        $role = Role::query()->where('nombre', 'superadmin')->firstOrFail();

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'role_id' => $role->id,
            'name' => config('superadmin.name'),
            'password' => Hash::make($password),
            'activo' => true,
        ])->save();
    }
}
