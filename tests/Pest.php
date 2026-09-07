<?php

use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

function administradorAccesos(): User
{
    test()->seed([PermisosSeeder::class, RolesSeeder::class]);
    $usuario = User::factory()->create(['password' => 'AdminSegura123!', 'estado' => true]);
    $usuario->assignRole('Administrador');
    test()->actingAs($usuario);

    return $usuario;
}

function datosEmpleadoAccesos(array $cambios = []): array
{
    return array_replace([
        'name' => 'Ana María', 'apellido_paterno' => 'Flores', 'apellido_materno' => '',
        'telefono' => '+591 71234567', 'email' => 'ana@example.com', 'rolUsuario' => 'Inventario',
    ], $cambios);
}
