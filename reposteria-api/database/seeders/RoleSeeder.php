<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['nombre' => 'superadmin', 'descripcion' => 'Administrador principal de la plataforma', 'ambito' => 'sistema'],
            ['nombre' => 'admin', 'descripcion' => 'Administrador de una repostería', 'ambito' => 'reposteria'],
            ['nombre' => 'vendedor', 'descripcion' => 'Responsable de ventas', 'ambito' => 'reposteria'],
            ['nombre' => 'produccion', 'descripcion' => 'Responsable de producción', 'ambito' => 'reposteria'],
            ['nombre' => 'cliente', 'descripcion' => 'Cliente de una repostería', 'ambito' => 'reposteria'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['nombre' => $role['nombre']],
                $role,
            );
        }
    }
}
