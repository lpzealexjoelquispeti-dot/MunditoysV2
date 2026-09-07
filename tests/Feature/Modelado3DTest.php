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

function usuarioConPermiso3D(): User
{
    test()->seed([PermisosSeeder::class, RolesSeeder::class]);

    $usuario = User::factory()->create();
    $usuario->assignRole('Administrador');

    return $usuario;
}

test('usuario no autenticado o sin permiso no puede acceder al módulo de modelados 3d', function () {
    $this->get(route('admin.modelos-3d'))->assertRedirect(route('login'));

    $cliente = User::factory()->create();
    $this->actingAs($cliente)->get(route('admin.modelos-3d'))->assertForbidden();
});

test('administrador con permisos puede cargar la pantalla de modelados 3d', function () {
    $admin = usuarioConPermiso3D();

    $this->actingAs($admin)
        ->get(route('admin.modelos-3d'))
        ->assertOk()
        ->assertSee('Generador de Modelados 3D')
        ->assertSee('Tripo AI');
});

test('valida que se suban entre 2 y 4 fotos para generar el modelado 3d', function () {
    $admin = usuarioConPermiso3D();

    // Intentar con solo 1 foto
    Livewire::actingAs($admin)
        ->test(Modelados3D::class)
        ->set('fotos', [
            UploadedFile::fake()->image('frente.jpg'),
        ])
        ->call('procesarModelado')
        ->assertHasErrors(['fotos'])
        ->assertDispatched('alerta-producto');

    // Intentar con 5 fotos
    Livewire::actingAs($admin)
        ->test(Modelados3D::class)
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

    $admin = usuarioConPermiso3D();
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

    Livewire::actingAs($admin)
        ->test(Modelados3D::class)
        ->call('seleccionarProducto', $producto->id)
        ->assertSet('productoId', $producto->id)
        ->set('fotos', $fotos)
        ->call('procesarModelado')
        ->assertHasNoErrors()
        ->assertDispatched('alerta-producto');

    $this->assertDatabaseHas('modelados_3d', [
        'producto_id' => $producto->id,
        'task_id' => 'tripo_task_999',
        'status' => 'queued',
        'tipo_generacion' => 'tripo_ai',
    ]);
});

test('puede sincronizar el estado y descargar el modelo glb cuando tripo finaliza con exito', function () {
    Storage::fake('public');
    Config::set('services.tripo.api_key', 'test_tripo_key');
    Config::set('services.tripo.api_url', 'https://api.tripo3d.ai/v2/openapi');

    $admin = usuarioConPermiso3D();
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

    Livewire::actingAs($admin)
        ->test(Modelados3D::class)
        ->call('actualizarEstado', $modelado->id)
        ->assertDispatched('alerta-producto');

    $modelado->refresh();
    expect($modelado->status)->toBe('completed');
    expect($modelado->progreso)->toBe(100);
    expect($modelado->modelo_glb_path)->toBe('modelos-3d/model_tripo_task_ready_1.glb');

    Storage::disk('public')->assertExists('modelos-3d/model_tripo_task_ready_1.glb');
});

test('puede desenlazar un modelo 3d de un producto', function () {
    $admin = usuarioConPermiso3D();
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

    Livewire::actingAs($admin)
        ->test(Modelados3D::class)
        ->call('desenlazarProducto', $modelado->id)
        ->assertDispatched('alerta-producto');

    $modelado->refresh();
    $producto->refresh();

    expect($modelado->producto_id)->toBeNull();
    expect($producto->modelado_3d_id)->toBeNull();
});

test('puede eliminar un modelado 3d mediante soft delete', function () {
    $admin = usuarioConPermiso3D();

    $modelado = Modelado3D::create([
        'task_id' => 'tripo_task_to_delete',
        'status' => 'failed',
        'progreso' => 0,
        'tipo_generacion' => 'tripo_ai',
    ]);

    Livewire::actingAs($admin)
        ->test(Modelados3D::class)
        ->call('eliminar', $modelado->id)
        ->assertDispatched('alerta-producto');

    expect(Modelado3D::find($modelado->id))->toBeNull();
    expect(Modelado3D::withTrashed()->find($modelado->id))->not->toBeNull();
});
