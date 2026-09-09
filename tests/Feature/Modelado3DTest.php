<?php

use App\Livewire\Admin\Modelados3D;
use App\Models\Modelado3D;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
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
        ->assertSee('Generador de Modelados 3D')
        ->assertSee('Tripo AI');
});

test('valida que sea obligatorio seleccionar un juguete antes de enviar las fotos a tripo', function () {
    $fotos = [
        UploadedFile::fake()->image('frente.jpg'),
        UploadedFile::fake()->image('lado.jpg'),
    ];

    Livewire::test(Modelados3D::class)
        ->set('fotos', $fotos)
        ->call('procesarModelado')
        ->assertHasErrors(['productoId' => 'required']);
});

test('valida que se suban entre 2 y 4 fotos para generar el modelado 3d', function () {
    $producto = Producto::factory()->create();

    // Intentar con solo 1 foto
    Livewire::test(Modelados3D::class)
        ->call('seleccionarProducto', $producto->id)
        ->set('fotos', [
            UploadedFile::fake()->image('frente.jpg'),
        ])
        ->call('procesarModelado')
        ->assertHasErrors(['fotos']);

    // Intentar con 5 fotos
    Livewire::test(Modelados3D::class)
        ->call('seleccionarProducto', $producto->id)
        ->set('fotos', [
            UploadedFile::fake()->image('frente.jpg'),
            UploadedFile::fake()->image('lado1.jpg'),
            UploadedFile::fake()->image('lado2.jpg'),
            UploadedFile::fake()->image('trasera.jpg'),
            UploadedFile::fake()->image('extra.jpg'),
        ])
        ->call('procesarModelado')
        ->assertHasErrors(['fotos']);
});

test('puede iniciar una tarea de generacion 3d con fotos validas y mock de tripo api', function () {
    Storage::fake('public');
    Config::set('services.tripo.api_key', 'test_tripo_key');
    Config::set('services.tripo.api_url', 'https://api.tripo3d.ai/v2/openapi');

    $producto = Producto::factory()->create(['nombre' => 'Dinosaurio T-Rex']);

    Http::fake([
        'https://api.tripo3d.ai/v2/openapi/upload' => Http::response([
            'code' => 0,
            'data' => ['image_token' => 'img_token_xyz123'],
        ], 200),
        'https://api.tripo3d.ai/v2/openapi/task' => Http::response([
            'code' => 0,
            'data' => ['task_id' => 'tripo_task_999'],
        ], 200),
    ]);

    $fotos = [
        UploadedFile::fake()->image('frente.png'),
        UploadedFile::fake()->image('lateral.png'),
        UploadedFile::fake()->image('atras.png'),
    ];

    Livewire::test(Modelados3D::class)
        ->call('seleccionarProducto', $producto->id)
        ->assertSet('productoId', $producto->id)
        ->set('fotos', $fotos)
        ->call('procesarModelado')
        ->assertHasNoErrors()
        ->assertDispatched('alerta-producto');

    $this->assertDatabaseHas('modelados_3d', [
        'producto_id' => $producto->id,
        'task_id' => 'tripo_task_999',
        'status' => 'pending',
        'tipo_generacion' => 'multi_image_to_3d',
    ]);
});

test('puede sincronizar el estado y descargar el modelo glb cuando tripo finaliza con exito', function () {
    Storage::fake('public');
    Config::set('services.tripo.api_key', 'test_tripo_key');
    Config::set('services.tripo.api_url', 'https://api.tripo3d.ai/v2/openapi');

    $producto = Producto::factory()->create();

    $modelado = Modelado3D::create([
        'producto_id' => $producto->id,
        'task_id' => 'tripo_task_ready_1',
        'status' => 'running',
        'progreso' => 50,
        'tipo_generacion' => 'tripo_ai',
    ]);

    Http::fake([
        'https://api.tripo3d.ai/v2/openapi/task/tripo_task_ready_1' => Http::response([
            'code' => 0,
            'data' => [
                'status' => 'success',
                'progress' => 100,
                'output' => [
                    'model' => 'https://tripo-assets.test/models/robot.glb',
                ],
            ],
        ], 200),
        'https://tripo-assets.test/models/robot.glb' => Http::response('FAKE_GLB_BINARY_DATA', 200),
    ]);

    Livewire::test(Modelados3D::class)
        ->call('actualizarEstado', $modelado->id)
        ->assertDispatched('alerta-producto');

    $modelado->refresh();
    expect($modelado->status)->toBe('success');
    expect($modelado->es_completado)->toBeTrue();
    expect($modelado->progreso)->toBe(100);
    expect($modelado->modelo_glb_path)->toBe('modelos-3d/model_tripo_task_ready_1.glb');

    Storage::disk('public')->assertExists('modelos-3d/model_tripo_task_ready_1.glb');
});

test('puede desenlazar un modelo 3d de un producto', function () {
    $producto = Producto::factory()->create();

    $modelado = Modelado3D::create([
        'producto_id' => $producto->id,
        'task_id' => 'tripo_task_unlink',
        'status' => 'completed',
        'progreso' => 100,
        'modelo_glb_path' => 'modelos-3d/test.glb',
        'tipo_generacion' => 'tripo_ai',
    ]);

    $producto->update(['modelado_3d_id' => $modelado->id]);

    Livewire::test(Modelados3D::class)
        ->call('desenlazarProducto', $modelado->id)
        ->assertDispatched('alerta-producto');

    $modelado->refresh();
    $producto->refresh();

    expect($modelado->producto_id)->toBeNull();
    expect($producto->modelado_3d_id)->toBeNull();
});

test('puede eliminar un modelado 3d mediante soft delete', function () {
    $modelado = Modelado3D::create([
        'task_id' => 'tripo_task_to_delete',
        'status' => 'failed',
        'progreso' => 0,
        'tipo_generacion' => 'tripo_ai',
    ]);

    Livewire::test(Modelados3D::class)
        ->call('eliminar', $modelado->id)
        ->assertDispatched('alerta-producto');

    expect(Modelado3D::find($modelado->id))->toBeNull();
    expect(Modelado3D::withTrashed()->find($modelado->id))->not->toBeNull();
});

test('alerta y advierte cuando se selecciona un juguete que ya tiene un modelo 3d activo', function () {
    $producto = Producto::factory()->create(['nombre' => 'Auto de Carreras 3D']);

    Modelado3D::create([
        'producto_id' => $producto->id,
        'task_id' => 'tripo_task_existing_3d',
        'status' => 'completed',
        'progreso' => 100,
        'modelo_glb_path' => 'modelos-3d/auto.glb',
        'tipo_generacion' => 'tripo_ai',
    ]);

    Livewire::test(Modelados3D::class)
        ->call('seleccionarProducto', $producto->id)
        ->assertDispatched('alerta-producto', titulo: 'Juguete con modelo 3D existente')
        ->assertSee('Este juguete ya cuenta con un modelado 3D enlazado.');
});

test('permite filtrar por papelera y restaurar un modelo 3d eliminado', function () {
    $modelado = Modelado3D::create([
        'task_id' => 'tripo_task_trashed_1',
        'status' => 'completed',
        'progreso' => 100,
        'modelo_glb_path' => 'modelos-3d/trashed.glb',
        'tipo_generacion' => 'tripo_ai',
    ]);

    // Eliminar a la papelera
    $modelado->delete();
    expect(Modelado3D::find($modelado->id))->toBeNull();

    // Ver en papelera
    Livewire::test(Modelados3D::class)
        ->set('filtroEstado', 'papelera')
        ->assertSee('En Papelera')
        ->assertSee('Restaurar')
        ->call('restaurar', $modelado->id)
        ->assertDispatched('alerta-producto', titulo: 'Modelo restaurado');

    expect(Modelado3D::find($modelado->id))->not->toBeNull();
    expect(Modelado3D::find($modelado->id)->deleted_at)->toBeNull();
});
