<?php

use App\Livewire\Admin\Logistica;
use App\Models\Direccion;
use App\Models\Entrega;
use App\Models\Notificacion;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\LogisticaHorarioService;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermisosSeeder::class, RolesSeeder::class]);

    $this->sucursal = Sucursal::create([
        'nombre' => 'Sucursal Central',
        'ciudad' => 'La Paz',
        'direccion' => 'Av. 6 de Agosto #2000',
        'telefono' => '22112233',
        'latitud' => -16.5050,
        'longitud' => -68.1300,
        'estado' => true,
    ]);

    $this->admin = User::factory()->create([
        'name' => 'Admin Logística',
        'email' => 'admin.logistica@munditoys.com',
        'estado' => true,
    ]);
    $this->admin->assignRole('Administrador');

    $this->chofer = User::factory()->create([
        'name' => 'Marcos Repartidor',
        'email' => 'marcos.chofer@munditoys.com',
        'estado' => true,
    ]);
    $this->chofer->assignRole('Logistica');

    $this->cliente = User::factory()->create([
        'name' => 'Lucía Mendoza',
        'email' => 'lucia.m@gmail.com',
        'ci_nit' => '4892019',
        'telefono' => '71234567',
        'estado' => true,
    ]);
    $this->cliente->assignRole('Cliente');

    $this->direccion = Direccion::create([
        'user_id' => $this->cliente->id,
        'nombre_referencia' => 'Casa Miraflores',
        'direccion_completa' => 'Av. Busch #1234, Edif. Rosales Piso 3',
        'zona' => 'Miraflores',
        'ciudad' => 'La Paz',
        'latitud' => -16.5020000,
        'longitud' => -68.1220000,
        'principal' => true,
    ]);

    $this->venta = Venta::create([
        'numero_venta' => 'VTA-20260910-0001',
        'cliente_id' => $this->cliente->id,
        'usuario_vendedor_id' => null,
        'tipo_venta' => 'online',
        'tipo_entrega' => 'delivery',
        'estado' => 'pagado',
        'subtotal' => 250.00,
        'descuento' => 0.00,
        'total' => 250.00,
        'fecha_venta' => now(),
    ]);

    $this->entrega = Entrega::create([
        'venta_id' => $this->venta->id,
        'direccion_id' => $this->direccion->id,
        'empleado_id' => null,
        'sucursal_origen_id' => $this->sucursal->id,
        'fecha_programada' => now()->toDateString(),
        'hora_inicio' => '12:00:00',
        'hora_fin' => '12:40:00',
        'estado' => 'pendiente',
    ]);
});

test('logistica puede ver el modulo y administrador tiene acceso denegado', function () {
    $this->actingAs($this->chofer)
        ->get(route('admin.logistica'))
        ->assertOk()
        ->assertSee('Logística');

    $this->actingAs($this->admin)
        ->get(route('admin.logistica'))
        ->assertForbidden();
});

test('un cliente o usuario sin permisos tiene acceso denegado a logistica', function () {
    $this->actingAs($this->cliente)
        ->get(route('admin.logistica'))
        ->assertForbidden();
});

test('el servicio matematico genera los 5 turnos diarios con intervalos de 90 minutos', function () {
    $servicio = app(LogisticaHorarioService::class);
    $turnos = $servicio->obtenerTurnosFijos();

    expect($turnos)->toHaveCount(5);
    expect($turnos[0]['inicio'])->toBe('12:00');
    expect($turnos[0]['fin'])->toBe('12:40'); // 40 minutos de entrega
    expect($turnos[1]['inicio'])->toBe('13:30'); // + 90 min
    expect($turnos[2]['inicio'])->toBe('15:00'); // + 90 min
    expect($turnos[3]['inicio'])->toBe('16:30'); // + 90 min
    expect($turnos[4]['inicio'])->toBe('18:00'); // + 90 min
});

test('detecta correctamente cuando un slot matematico ya esta ocupado', function () {
    $servicio = app(LogisticaHorarioService::class);

    // Turno de las 12:00 ya ocupado por $this->entrega
    $disponibilidad = $servicio->obtenerDisponibilidad(now()->toDateString());
    $slot12 = collect($disponibilidad)->firstWhere('inicio', '12:00');
    $slot1330 = collect($disponibilidad)->firstWhere('inicio', '13:30');

    expect($slot12['disponible'])->toBeFalse();
    expect($slot12['numero_venta'])->toBe('VTA-20260910-0001');
    expect($slot1330['disponible'])->toBeTrue();
});

test('rechaza solapar entregas en el mismo turno con validacion en espanol', function () {
    $servicio = app(LogisticaHorarioService::class);

    $resultado = $servicio->validarSlot(
        fecha: now()->toDateString(),
        horaInicio: '12:00'
    );

    expect($resultado['valido'])->toBeFalse();
    expect($resultado['mensaje'])->toContain('ya está reservado para VTA-20260910-0001');
});

test('permite asignar fecha, turno matematico y chofer a traves de Livewire', function () {
    $this->actingAs($this->chofer);

    Livewire::test(Logistica::class)
        ->call('abrirAsignar', $this->entrega->id)
        ->set('asignarFecha', now()->addDay()->toDateString())
        ->set('asignarHoraInicio', '13:30')
        ->set('asignarEmpleadoId', $this->chofer->id)
        ->set('asignarNotas', 'Dejar en recepción si no responde')
        ->call('guardarAsignacion');

    $this->entrega->refresh();

    expect($this->entrega->fecha_programada->toDateString())->toBe(now()->addDay()->toDateString());
    expect(substr((string) $this->entrega->hora_inicio, 0, 5))->toBe('13:30');
    expect(substr((string) $this->entrega->hora_fin, 0, 5))->toBe('14:10');
    expect($this->entrega->empleado_id)->toBe($this->chofer->id);
    expect($this->entrega->notas_despacho)->toBe('Dejar en recepción si no responde');
});

test('iniciar ruta cambia el estado a en_camino', function () {
    $this->actingAs($this->chofer);

    Livewire::test(Logistica::class)
        ->call('iniciarRuta', $this->entrega->id);

    expect($this->entrega->fresh()->estado)->toBe('en_camino');
});

test('confirmar entrega cambia el estado a entregado y registra marca de tiempo', function () {
    $this->actingAs($this->chofer);

    $this->entrega->update(['estado' => 'en_camino']);

    Livewire::test(Logistica::class)
        ->call('abrirConfirmarEntrega', $this->entrega->id)
        ->set('observacionesEntrega', 'Recibido conforme por el titular')
        ->call('guardarConfirmacionEntrega');

    $this->entrega->refresh();

    expect($this->entrega->estado)->toBe('entregado');
    expect($this->entrega->entregado_at)->not->toBeNull();
    expect($this->entrega->notas_despacho)->toContain('Recibido conforme por el titular');
});

test('cancelar entrega requiere minimo 10 caracteres de motivo y notifica al administrador', function () {
    $this->actingAs($this->chofer);

    // Intento con menos de 10 caracteres
    Livewire::test(Logistica::class)
        ->call('abrirCancelar', $this->entrega->id)
        ->set('motivoCancelacion', 'Llovió')
        ->call('guardarCancelacion')
        ->assertHasErrors(['motivoCancelacion']);

    // Cancelación válida
    Livewire::test(Logistica::class)
        ->call('abrirCancelar', $this->entrega->id)
        ->set('motivoCancelacion', 'Cliente solicitó reprogramar por viaje imprevisto fuera de la ciudad')
        ->call('guardarCancelacion')
        ->assertHasNoErrors();

    $this->entrega->refresh();

    expect($this->entrega->estado)->toBe('cancelado');
    expect($this->entrega->motivo_cancelacion)->toBe('Cliente solicitó reprogramar por viaje imprevisto fuera de la ciudad');
    expect($this->entrega->cancelado_at)->not->toBeNull();

    // Comprobar que el Administrador recibió la notificación
    $notificacion = Notificacion::where('usuario_id', $this->admin->id)
        ->where('titulo', 'like', '%Envío Cancelado: VTA-20260910-0001%')
        ->first();

    expect($notificacion)->not->toBeNull();
    expect($notificacion->mensaje)->toContain('Marcos Repartidor');
    expect($notificacion->mensaje)->toContain('Cliente solicitó reprogramar por viaje imprevisto fuera de la ciudad');
});

test('el mapa recibe los puntos con coordenadas para visualizacion en La Paz', function () {
    $this->actingAs($this->chofer);

    $componente = Livewire::test(Logistica::class);

    $puntos = $componente->viewData('puntosMapa');

    expect($puntos)->toHaveCount(1);
    expect($puntos[0]['numero_venta'])->toBe('VTA-20260910-0001');
    expect($puntos[0]['lat'])->toBe(-16.5020000);
    expect($puntos[0]['lng'])->toBe(-68.1220000);
    expect($puntos[0]['zona'])->toBe('Miraflores');
});

test('el usuario de logistica puede acceder a la pestaña de reportes y ver metricas operativas', function () {
    $this->actingAs($this->chofer);

    Livewire::test(Logistica::class)
        ->call('cambiarPestana', 'reportes')
        ->assertSet('pestanaActiva', 'reportes')
        ->assertSee('Reportes de logística')
        ->assertSee('Total Despachos')
        ->assertSee('Por turno')
        ->assertSee('Descargar PDF')
        ->assertSee('Descargar Excel');
});

test('el usuario de logistica puede exportar el reporte a pdf y excel desde su modulo', function () {
    $this->actingAs($this->chofer);

    $componente = Livewire::test(Logistica::class)
        ->call('cambiarPestana', 'reportes');

    $responsePdf = $componente->call('exportarReportePdf');
    $responsePdf->assertFileDownloaded();

    $responseExcel = $componente->call('exportarReporteExcel');
    $responseExcel->assertFileDownloaded();
});

test('los botones de logistica permiten recorrer mapa historial reportes y tablero', function (): void {
    $this->actingAs($this->chofer);
    $this->entrega->update([
        'estado' => 'entregado',
        'fecha_programada' => now()->subWeek()->toDateString(),
        'entregado_at' => now()->subWeek(),
    ]);

    Livewire::test(Logistica::class)
        ->call('cambiarPestana', 'mapa')
        ->assertSee('mundiToysLogisticaMap')
        ->call('cambiarPestana', 'completados')
        ->assertSee('Entregas completadas')
        ->assertSee($this->venta->numero_venta)
        ->call('cambiarPestana', 'reportes')
        ->assertSee('Reportes de logística')
        ->call('cambiarPestana', 'tablero')
        ->assertSee('Sin entregas')
        ->assertDontSee('Reportes de logística')
        ->assertDontSee('Entregas completadas');
});

test('logistica vuelve a comprobar la autorizacion antes de ejecutar acciones', function (): void {
    $this->actingAs($this->chofer);
    $componente = Livewire::test(Logistica::class);
    $this->chofer->removeRole('Logistica');

    $componente->call('iniciarRuta', $this->entrega->id)->assertForbidden();

    expect($this->entrega->fresh()->estado)->toBe('pendiente');
});
