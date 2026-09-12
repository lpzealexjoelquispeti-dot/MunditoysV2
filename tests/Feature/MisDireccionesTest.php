<?php

use App\Livewire\Cliente\MisDirecciones;
use App\Models\Direccion;
use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([PermisosSeeder::class, RolesSeeder::class]);
});

test('un invitado es redirigido al login si intenta ingresar a mis direcciones', function (): void {
    $this->get(route('tienda.direcciones'))
        ->assertRedirect(route('login'));
});

test('un cliente autenticado puede ver la vista de mis direcciones y el contador de cupos', function (): void {
    $cliente = User::factory()->create();

    $this->actingAs($cliente)
        ->get(route('tienda.direcciones'))
        ->assertOk()
        ->assertSee('Lugares de Entrega')
        ->assertSee('0 de 3 direcciones registradas');
});

test('el cliente puede registrar hasta 3 direcciones con coordenadas validas en La Paz', function (): void {
    $cliente = User::factory()->create();

    Livewire::actingAs($cliente)
        ->test(MisDirecciones::class)
        ->call('abrirModalCrear')
        ->set('nombreReferencia', 'Mi Casa Central')
        ->set('zona', 'Sopocachi')
        ->set('direccionCompleta', 'Av. 20 de Octubre #1820')
        ->set('latitud', '-16.5050000')
        ->set('longitud', '-68.1300000')
        ->set('ciudad', 'La Paz')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Direccion::where('user_id', $cliente->id)->count())->toBe(1);
    $primera = Direccion::where('user_id', $cliente->id)->first();
    expect($primera->principal)->toBeTrue(); // La primera es principal por defecto

    // Registrar 2da dirección
    Livewire::actingAs($cliente)
        ->test(MisDirecciones::class)
        ->call('abrirModalCrear')
        ->set('nombreReferencia', 'Oficina Sopocachi')
        ->set('zona', 'San Jorge')
        ->set('direccionCompleta', 'Av. Arce #2230')
        ->set('latitud', '-16.5120000')
        ->set('longitud', '-68.1220000')
        ->set('ciudad', 'La Paz')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Direccion::where('user_id', $cliente->id)->count())->toBe(2);

    // Registrar 3ra dirección
    Livewire::actingAs($cliente)
        ->test(MisDirecciones::class)
        ->call('abrirModalCrear')
        ->set('nombreReferencia', 'Casa de mis papás')
        ->set('zona', 'Calacoto')
        ->set('direccionCompleta', 'Calle 15 de Calacoto #300')
        ->set('latitud', '-16.5400000')
        ->set('longitud', '-68.0850000')
        ->set('ciudad', 'La Paz')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Direccion::where('user_id', $cliente->id)->count())->toBe(3);
});

test('no permite registrar una cuarta direccion si no se elimina una previa', function (): void {
    $cliente = User::factory()->create();

    // Crear 3 direcciones previas
    Direccion::factory()->count(3)->create([
        'user_id' => $cliente->id,
        'ciudad' => 'La Paz',
        'latitud' => -16.4950000,
        'longitud' => -68.1330000,
    ]);

    expect(Direccion::where('user_id', $cliente->id)->count())->toBe(3);

    Livewire::actingAs($cliente)
        ->test(MisDirecciones::class)
        ->call('abrirModalCrear')
        ->assertSet('tipoFeedback', 'error')
        ->assertSee('Has alcanzado el límite máximo de 3 direcciones')
        ->set('nombreReferencia', 'Cuarta Dirección')
        ->set('zona', 'Miraflores')
        ->set('direccionCompleta', 'Calle Brasil #123')
        ->set('latitud', '-16.5000000')
        ->set('longitud', '-68.1250000')
        ->set('ciudad', 'La Paz')
        ->call('guardar')
        ->assertHasErrors(['limite']);

    expect(Direccion::where('user_id', $cliente->id)->count())->toBe(3);
});

test('rechaza direcciones con coordenadas fuera de los limites de La Paz', function (): void {
    $cliente = User::factory()->create();

    // Intentar guardar con coordenadas de El Alto (-16.504, -68.163)
    Livewire::actingAs($cliente)
        ->test(MisDirecciones::class)
        ->call('abrirModalCrear')
        ->set('nombreReferencia', 'Ubicación en El Alto')
        ->set('zona', 'Ciudad Satélite')
        ->set('direccionCompleta', 'Av. del Policía #50')
        ->set('latitud', '-16.5040000')
        ->set('longitud', '-68.1630000')
        ->set('ciudad', 'La Paz')
        ->call('guardar')
        ->assertHasErrors(['latitud']);

    expect(Direccion::where('user_id', $cliente->id)->count())->toBe(0);
});

test('el cliente puede editar una direccion existente', function (): void {
    $cliente = User::factory()->create();
    $direccion = Direccion::factory()->create([
        'user_id' => $cliente->id,
        'nombre_referencia' => 'Mi Casita Vieja',
        'latitud' => -16.4950000,
        'longitud' => -68.1330000,
        'ciudad' => 'La Paz',
    ]);

    Livewire::actingAs($cliente)
        ->test(MisDirecciones::class)
        ->call('abrirModalEditar', $direccion->id)
        ->set('nombreReferencia', 'Mi Casita Renovada')
        ->call('guardar')
        ->assertHasNoErrors();

    expect($direccion->fresh()->nombre_referencia)->toBe('Mi Casita Renovada');
});

test('el cliente puede cambiar su direccion principal', function (): void {
    $cliente = User::factory()->create();
    $dir1 = Direccion::factory()->create([
        'user_id' => $cliente->id,
        'principal' => true,
        'latitud' => -16.4950000,
        'longitud' => -68.1330000,
        'ciudad' => 'La Paz',
    ]);
    $dir2 = Direccion::factory()->create([
        'user_id' => $cliente->id,
        'principal' => false,
        'latitud' => -16.5050000,
        'longitud' => -68.1300000,
        'ciudad' => 'La Paz',
    ]);

    Livewire::actingAs($cliente)
        ->test(MisDirecciones::class)
        ->call('marcarPrincipal', $dir2->id);

    expect($dir1->fresh()->principal)->toBeFalse();
    expect($dir2->fresh()->principal)->toBeTrue();
});

test('el cliente puede eliminar una direccion y liberar espacio para registrar otra', function (): void {
    $cliente = User::factory()->create();
    $direcciones = Direccion::factory()->count(3)->create([
        'user_id' => $cliente->id,
        'latitud' => -16.4950000,
        'longitud' => -68.1330000,
        'ciudad' => 'La Paz',
    ]);

    $dirAEliminar = $direcciones->first();

    Livewire::actingAs($cliente)
        ->test(MisDirecciones::class)
        ->call('confirmarEliminar', $dirAEliminar->id)
        ->call('eliminar')
        ->assertSet('mostrarModalEliminar', false);

    expect(Direccion::where('user_id', $cliente->id)->count())->toBe(2);

    // Ahora sí puede agregar una nueva
    Livewire::actingAs($cliente)
        ->test(MisDirecciones::class)
        ->call('abrirModalCrear')
        ->set('nombreReferencia', 'Nueva Dirección Reemplazo')
        ->set('zona', 'Miraflores')
        ->set('direccionCompleta', 'Av. Busch #1100')
        ->set('latitud', '-16.4980000')
        ->set('longitud', '-68.1200000')
        ->set('ciudad', 'La Paz')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Direccion::where('user_id', $cliente->id)->count())->toBe(3);
});
