<?php

use App\Livewire\Admin\Logistica;
use App\Livewire\Admin\RecojoSucursal;
use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([PermisosSeeder::class, RolesSeeder::class]);
});

test('los permisos operativos corresponden al rol y al estado de la cuenta', function (array $roles, bool $activo, bool $pendiente, bool $logistica, bool $recojos): void {
    $usuario = User::factory()->create(['estado' => $activo, 'invitacion_pendiente' => $pendiente]);
    $usuario->assignRole($roles);

    expect(Gate::forUser($usuario)->allows('acceder logistica'))->toBe($logistica)
        ->and(Gate::forUser($usuario)->allows('gestionar recojos'))->toBe($recojos);
})->with([
    [['Administrador'], true, false, false, false],
    [['Vendedor'], true, false, false, true],
    [['Logistica'], true, false, true, false],
    [['Inventario'], true, false, false, false],
    [['Cliente'], true, false, false, false],
    [['Administrador', 'Logistica', 'Vendedor'], true, false, false, false],
    [['Logistica'], false, false, false, false],
    [['Logistica'], true, true, false, false],
    [['Vendedor'], false, false, false, false],
    [['Vendedor'], true, true, false, false],
]);

test('el administrador muestra sus reportes sin accesos operativos en el sidebar', function (): void {
    $usuario = User::factory()->create(['estado' => true]);
    $usuario->assignRole('Administrador');

    $this->actingAs($usuario)->get(route('admin.dashboard'))
        ->assertSee('Usuarios y accesos')
        ->assertDontSee('wire:key="nav-logistica"', false)
        ->assertDontSee('wire:key="nav-recojo"', false)
        ->assertDontSee('wire:key="nav-recojo-sucursal"', false)
        ->assertSee('wire:key="nav-reportes"', false)
        ->assertSee('href="'.route('admin.reportes').'"', false)
        ->assertDontSee('wire:key="nav-reportes-logistica"', false);
});

test('logistica encuentra sus reportes y las vistas de despacho en su panel', function (): void {
    $usuario = User::factory()->create(['estado' => true]);
    $usuario->assignRole('Logistica');

    $this->actingAs($usuario)->get(route('admin.dashboard'))
        ->assertSee('Reportes de logística')
        ->assertSee(route('admin.logistica', ['vista' => 'reportes']))
        ->assertSee(route('admin.logistica', ['vista' => 'completados']))
        ->assertSee('Completados')
        ->assertDontSee(route('admin.logistica', ['vista' => 'mapa']))
        ->assertDontSee(route('admin.productos'))
        ->assertDontSee(route('admin.modelos-3d'))
        ->assertDontSee(route('admin.inventario'))
        ->assertDontSee('wire:key="nav-recojo"', false)
        ->assertDontSee('wire:key="nav-reportes"', false);
});

test('el vendedor conserva recojos y sus reportes de ventas', function (): void {
    $usuario = User::factory()->create(['estado' => true]);
    $usuario->assignRole('Vendedor');

    $this->actingAs($usuario)->get(route('admin.dashboard'))
        ->assertSee(route('admin.recojo-sucursal'))
        ->assertSee(route('admin.mis-reportes'))
        ->assertDontSee('wire:key="nav-logistica"', false)
        ->assertDontSee('wire:key="nav-reportes-logistica"', false);
});

test('los modulos operativos rechazan visitantes', function (string $ruta): void {
    $this->get(route($ruta))->assertRedirect(route('login'));
})->with(['admin.logistica', 'admin.recojo-sucursal']);

test('los modulos operativos rechazan roles ajenos por url y por livewire', function (string $rol, string $ruta, string $componente): void {
    $usuario = User::factory()->create(['estado' => true]);
    $usuario->assignRole($rol);

    $this->actingAs($usuario)->get(route($ruta))->assertForbidden();
    Livewire::test($componente)->assertForbidden();
})->with([
    ['Inventario', 'admin.logistica', Logistica::class],
    ['Vendedor', 'admin.logistica', Logistica::class],
    ['Administrador', 'admin.recojo-sucursal', RecojoSucursal::class],
    ['Logistica', 'admin.recojo-sucursal', RecojoSucursal::class],
]);

test('un enlace de logistica abre la vista solicitada', function (string $vista, string $contenido): void {
    $usuario = User::factory()->create(['estado' => true]);
    $usuario->assignRole('Logistica');

    $this->actingAs($usuario)->get(route('admin.logistica', ['vista' => $vista]))
        ->assertSee($contenido)
        ->assertDontSee('wire:click="cambiarPestana(', false)
        ->assertDontSee(route('admin.productos'))
        ->assertDontSee(route('admin.modelos-3d'))
        ->assertDontSee(route('admin.inventario'));
})->with([
    ['reportes', 'Reportes de logística'],
    ['completados', 'Entregas completadas'],
    ['mapa', 'mundiToysLogisticaMap'],
    ['invalida', 'Sin entregas'],
]);
