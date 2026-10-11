<?php

use App\Actions\Modelado3D\ValidarFotografiasJob;
use App\Livewire\Admin\Modelados3D;
use App\Models\FotografiaModelado;
use App\Models\Modelado3D;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermisosSeeder::class, RolesSeeder::class]);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrador');
    $this->actingAs($this->admin);
});

test('usuario no autenticado o sin permiso no puede acceder al módulo de modelados 3d', function () {
    auth()->logout();
    $this->get(route('admin.modelos-3d'))->assertRedirect(route('login'));

    $cliente = User::factory()->create();
    $this->actingAs($cliente)->get(route('admin.modelos-3d'))->assertForbidden();
});

test('administrador con permisos puede cargar la pantalla de modelados 3d', function () {
    $this->get(route('admin.modelos-3d'))
        ->assertOk()
        ->assertSee('48')
        ->assertDontSee('Tripo AI');
});

test('exige seleccionar un juguete antes de crear una colección local', function () {
    Livewire::test(Modelados3D::class)
        ->call('crearSesion')
        ->assertHasErrors(['productoId' => 'required']);

    $this->assertDatabaseCount('modelados_3d', 0);
});

test('crea una colección local asociada al juguete sin solicitar servicios externos', function () {
    Http::preventStrayRequests();
    Http::fake();
    $producto = Producto::factory()->create();

    Livewire::test(Modelados3D::class)
        ->call('seleccionarProducto', $producto->id)
        ->call('crearSesion')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('modelados_3d', [
        'producto_id' => $producto->id,
        'tipo_generacion' => 'fotogrametria_local',
        'status' => 'borrador',
    ]);
    Http::assertNothingSent();
});

test('guarda una fotografía desde la pantalla en almacenamiento privado', function () {
    Storage::fake('local');
    $sesion = Modelado3D::factory()->create();

    Livewire::test(Modelados3D::class)
        ->call('abrirSesion', $sesion->id)
        ->set('foto', UploadedFile::fake()->image('frente.jpg', 800, 600))
        ->call('guardarFoto')
        ->assertHasNoErrors();

    $foto = $sesion->fotografias()->sole();
    expect($foto->nombre_original)->toBe('frente.jpg');
    expect($foto->estado)->toBe('pendiente');
    Storage::disk('local')->assertExists([$foto->ruta, $foto->miniatura]);
});

test('encola el análisis local de la revisión actual al elegir una referencia', function () {
    Queue::fake([ValidarFotografiasJob::class]);
    Config::set('fotogrametria.python', PHP_BINARY);
    $sesion = Modelado3D::factory()->create(['revision' => 3]);
    $fotos = FotografiaModelado::factory()->count(3)->sequence(fn ($secuencia): array => ['ranura' => $secuencia->index + 1])
        ->create(['modelado_3d_id' => $sesion->id]);

    Livewire::test(Modelados3D::class)
        ->call('abrirSesion', $sesion->id)
        ->call('seleccionarReferencia', $fotos->first()->id)
        ->call('analizarColeccion')
        ->assertHasNoErrors();

    expect($sesion->fresh()->status)->toBe('en_cola');
    expect($sesion->fresh()->validacion_token)->not->toBeNull();
    Queue::assertPushed(ValidarFotografiasJob::class, fn (ValidarFotografiasJob $job): bool => $job->modeladoId === $sesion->id && $job->revision === 4
        && $job->token === $sesion->fresh()->validacion_token);
});

test('puede desenlazar un modelo 3d de un producto', function () {
    $producto = Producto::factory()->create();

    $modelado = Modelado3D::factory()->create([
        'producto_id' => $producto->id,
        'task_id' => 'manual-unlink',
        'status' => 'completed',
        'progreso' => 100,
        'modelo_glb_path' => 'modelos-3d/test.glb',
        'tipo_generacion' => 'manual',
    ]);

    Livewire::test(Modelados3D::class)
        ->call('desenlazarProducto', $modelado->id)
        ->assertDispatched('alerta-producto');

    $modelado->refresh();
    $producto->refresh();

    expect($modelado->producto_id)->toBeNull();
    expect($producto->modelos3D()->exists())->toBeFalse();
});

test('puede eliminar un modelado 3d mediante soft delete', function () {
    $modelado = Modelado3D::factory()->create([
        'task_id' => 'local-to-delete',
        'status' => 'failed',
        'progreso' => 0,
        'tipo_generacion' => 'fotogrametria_local',
    ]);

    Livewire::test(Modelados3D::class)
        ->call('eliminar', $modelado->id)
        ->assertDispatched('alerta-producto');

    $this->assertSoftDeleted($modelado);
});

test('alerta y advierte cuando se selecciona un juguete que ya tiene un modelo 3d activo', function () {
    $producto = Producto::factory()->create(['nombre' => 'Auto de Carreras 3D']);

    Modelado3D::factory()->create([
        'producto_id' => $producto->id,
        'task_id' => 'manual-existing-3d',
        'status' => 'completed',
        'progreso' => 100,
        'modelo_glb_path' => 'modelos-3d/auto.glb',
        'tipo_generacion' => 'manual',
    ]);

    Livewire::test(Modelados3D::class)
        ->call('seleccionarProducto', $producto->id)
        ->assertDispatched('alerta-producto', titulo: 'Juguete con modelo 3D existente')
        ->assertSet('productoSeleccionado.tiene_3d', true);
});

test('permite filtrar por papelera y restaurar un modelo 3d eliminado', function () {
    $modelado = Modelado3D::factory()->create([
        'task_id' => 'manual-trashed',
        'status' => 'completed',
        'progreso' => 100,
        'modelo_glb_path' => 'modelos-3d/trashed.glb',
        'tipo_generacion' => 'manual',
    ]);

    $modelado->delete();
    expect(Modelado3D::find($modelado->id))->toBeNull();

    Livewire::test(Modelados3D::class)
        ->set('filtroEstado', 'papelera')
        ->assertSee('Restaurar')
        ->call('restaurar', $modelado->id)
        ->assertDispatched('alerta-producto', titulo: 'Modelo restaurado');

    expect(Modelado3D::find($modelado->id))->not->toBeNull();
    expect(Modelado3D::find($modelado->id)->deleted_at)->toBeNull();
});
