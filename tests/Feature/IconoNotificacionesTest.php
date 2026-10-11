<?php

use App\Livewire\Cliente\IconoNotificaciones;
use App\Livewire\Cliente\SeguimientoRegistro;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function crearNotificacion(User $usuario, bool $leido = false): Notificacion
{
    return Notificacion::create([
        'usuario_id' => $usuario->id,
        'titulo' => '¡Compra confirmada!',
        'mensaje' => 'Ya puedes recoger tus juguetes en la sucursal.',
        'leido' => $leido,
    ]);
}

test('la campana del navbar muestra solo las notificaciones sin leer del usuario', function (): void {
    $cliente = User::factory()->create();
    crearNotificacion($cliente);
    crearNotificacion($cliente);
    crearNotificacion($cliente, leido: true);
    crearNotificacion(User::factory()->create());

    $this->actingAs($cliente);

    Livewire::test(IconoNotificaciones::class)
        ->assertSet('noLeidas', 2)
        ->assertSee('2 sin leer');
});

test('la campana se actualiza cuando llega una notificacion nueva', function (): void {
    $cliente = User::factory()->create();
    $this->actingAs($cliente);

    $campana = Livewire::test(IconoNotificaciones::class)->assertSet('noLeidas', 0);

    crearNotificacion($cliente);

    $campana->call('actualizarConteo')->assertSet('noLeidas', 1);
});

test('marcar notificaciones como leidas avisa a la campana del navbar', function (): void {
    $cliente = User::factory()->create();
    crearNotificacion($cliente);
    $this->actingAs($cliente);

    Livewire::test(SeguimientoRegistro::class)
        ->call('marcarTodasLeidas')
        ->assertDispatched('notificaciones-actualizadas');

    Livewire::test(IconoNotificaciones::class)->assertSet('noLeidas', 0);
});

test('un invitado no ve contador de notificaciones', function (): void {
    Livewire::test(IconoNotificaciones::class)->assertSet('noLeidas', 0);
});
