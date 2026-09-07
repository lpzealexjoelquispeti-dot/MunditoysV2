<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = Permission::query()->pluck('name')->all();

        $roles = [
            'Administrador' => $permissions,
            'Vendedor' => ['ver productos', 'crear ventas'],
            'Inventario' => ['ver productos', 'crear productos', 'editar productos', 'gestionar inventario'],
            'Logistica' => ['ver productos', 'gestionar inventario'],
            'Cliente' => ['ver productos', 'comprar productos'],
        ];

        foreach ($roles as $name => $rolePermissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($rolePermissions);
        }
    }
}
