<?php

use App\Livewire\Admin\Productos;
use App\Livewire\Cliente\Catalogo;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\User;
use App\Services\ProductoService;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function administradorDeProductos(): User
{
    test()->seed([PermisosSeeder::class, RolesSeeder::class]);

    $usuario = User::factory()->create();
    $usuario->assignRole('Administrador');

    return $usuario;
}

test('el administrador puede crear un producto con datos válidos', function () {
    Storage::fake('public');
    $administrador = administradorDeProductos();
    $categoria = Categoria::factory()->create();
    $marca = Marca::factory()->create();
    $imagenes = [
        UploadedFile::fake()->image('frente.jpg'),
        UploadedFile::fake()->image('lateral.jpg'),
        UploadedFile::fake()->image('detalle.jpg'),
    ];

    $componente = Livewire::actingAs($administrador)
        ->test(Productos::class)
        ->call('abrirCrear')
        ->set('nombre', 'Tren transformable')
        ->set('categoriaId', (string) $categoria->id)
        ->set('marcaId', (string) $marca->id)
        ->set('descripcion', 'Juguete educativo elaborado con materiales resistentes.')
        ->set('edadRecomendada', '6-8')
        ->set('dimensiones', '30 x 20 x 15 cm')
        ->call('siguientePaso')
        ->assertHasNoErrors()
        ->assertSet('paso', 2)
        ->set('imagenes', $imagenes)
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertSet('modalAbierto', false)
        ->assertDispatched('alerta-producto');

    $this->assertDatabaseHas('productos', [
        'nombre' => 'Tren transformable',
        'edad_recomendada' => '6-8',
        'estado' => 'activo',
        'precio_venta' => null,
        'precio_compra_referencia' => null,
    ]);

    $producto = Producto::query()->where('nombre', 'Tren transformable')->firstOrFail();
    expect($producto->codigo_producto)->toStartWith('MT-TRENTRANSFOR-')
        ->and($producto->codigo_qr)->toStartWith('MTQR-')
        ->and($producto->imagenes)->toHaveCount(3);
    foreach ($producto->imagenes as $imagen) {
        Storage::disk('public')->assertExists($imagen->ruta_archivo);
    }
});

test('el formulario muestra errores para datos inválidos', function () {
    $administrador = administradorDeProductos();

    Livewire::actingAs($administrador)
        ->test(Productos::class)
        ->call('abrirCrear')
        ->set('nombre', 'Auto 4x4')
        ->call('siguientePaso')
        ->assertHasErrors([
            'nombre' => 'regex',
            'categoriaId' => 'required',
            'marcaId' => 'required',
            'descripcion' => 'required',
            'edadRecomendada' => 'required',
            'dimensiones' => 'required',
        ])
        ->assertSet('paso', 1);
});

test('puede buscar filtrar ordenar eliminar y restaurar productos', function () {
    $this->freezeTime();
    $administrador = administradorDeProductos();
    $categoria = Categoria::factory()->create();
    $antiguo = Producto::factory()->for($categoria)->create([
        'nombre' => 'Bloques clásicos',
        'codigo_producto' => 'BLOQ-001',
        'edad_recomendada' => '3-5',
        'precio_venta' => 80,
        'created_at' => now()->subWeek(),
    ]);
    $reciente = Producto::factory()->for($categoria)->create([
        'nombre' => 'Robot explorador',
        'codigo_producto' => 'ROBOT-001',
        'edad_recomendada' => '9-12',
        'precio_venta' => 250,
        'created_at' => now(),
    ]);

    $componente = Livewire::actingAs($administrador)
        ->test(Productos::class)
        ->assertSeeInOrder(['Robot explorador', 'Bloques clásicos'])
        ->set('buscar', 'robot')
        ->assertSee('Robot explorador')
        ->assertDontSee('Bloques clásicos')
        ->set('buscar', '')
        ->set('filtroEdad', '3-5')
        ->assertSee('Bloques clásicos')
        ->assertDontSee('Robot explorador')
        ->set('filtroEdad', '')
        ->set('orden', 'antiguos')
        ->assertSeeHtmlInOrder(['Bloques clásicos', 'Robot explorador']);

    $componente->call('eliminar', $reciente->id);
    $this->assertSoftDeleted($reciente);

    $componente
        ->set('filtroEstado', 'eliminados')
        ->assertSee('Robot explorador')
        ->call('restaurar', $reciente->id);

    $this->assertNotSoftDeleted($reciente);
    expect($antiguo->fresh())->not->toBeNull();
});

test('dimensiones inválidas impiden avanzar y también guardar tras volver a modificarlas', function () {
    Storage::fake('public');
    $administrador = administradorDeProductos();
    $categoria = Categoria::factory()->create();
    $marca = Marca::factory()->create();

    $componente = Livewire::actingAs($administrador)->test(Productos::class)
        ->call('abrirCrear')
        ->assertDontSeeHtml('wire:model.blur="estado"')
        ->assertDontSeeHtml('wire:model.blur="precioVenta"')
        ->assertDontSeeHtml('wire:model.blur="precioCompraReferencia"')
        ->set('nombre', 'Tren de madera')
        ->set('categoriaId', (string) $categoria->id)
        ->set('marcaId', (string) $marca->id)
        ->set('descripcion', 'Tren de madera para aprender jugando.')
        ->set('edadRecomendada', '3-5')
        ->set('dimensiones', '30 xxx30')
        ->assertHasErrors('dimensiones')
        ->assertSee('Escribe largo x ancho x alto en centímetros.')
        ->call('siguientePaso')->assertSet('paso', 1)
        ->set('dimensiones', '30 x 20 x 15')
        ->assertHasNoErrors('dimensiones')
        ->call('siguientePaso')->assertSet('paso', 2)
        ->set('dimensiones', 'xx')->call('guardar')->assertHasErrors('dimensiones');

    $this->assertDatabaseCount('productos', 0);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('permite cargar y reemplazar GLB conservando su extensión y los precios históricos', function () {
    Storage::fake('public');
    $administrador = administradorDeProductos();
    $this->actingAs($administrador);
    $categoria = Categoria::factory()->create();
    $marca = Marca::factory()->create();
    $imagenes = [UploadedFile::fake()->image('uno.jpg'), UploadedFile::fake()->image('dos.jpg'), UploadedFile::fake()->image('tres.jpg')];
    $datos = [
        'nombre' => 'Tren de madera', 'descripcion' => 'Tren de madera para aprender jugando.',
        'categoria_id' => $categoria->id, 'marca_id' => $marca->id,
        'edad_recomendada' => '3-5', 'dimensiones' => '30 x 20 x 15',
        'estado' => 'inactivo', 'precio_venta' => 20, 'precio_compra_referencia' => 10,
    ];
    $producto = app(ProductoService::class)->crear($datos, $imagenes);
    expect($producto->fresh()->estado)->toBe('activo');
    expect($producto->fresh()->precio_venta)->toBeNull();
    expect($producto->fresh()->precio_compra_referencia)->toBeNull();

    $producto->update(['precio_venta' => 95, 'precio_compra_referencia' => 40, 'estado' => 'inactivo']);
    $json = str_pad('{"asset":{"version":"2.0"}}', 28, ' ');
    $glb = 'glTF'.pack('VV', 2, 48).pack('VV', 28, 0x4E4F534A).$json;

    foreach (['primero.glb', 'reemplazo.glb'] as $nombreArchivo) {
        Livewire::actingAs($administrador)->test(Productos::class)
            ->call('editar', $producto->id)
            ->call('siguientePaso')
            ->set('modeloGlb', UploadedFile::fake()->createWithContent($nombreArchivo, $glb))
            ->assertHasNoErrors('modeloGlb')
            ->call('guardar')->assertHasNoErrors()->assertSet('modalAbierto', false);

        $producto->refresh();
        $ruta = $producto->modelos3D->sole()->modelo_glb_path;
        expect($ruta)->toEndWith('.glb');
        Storage::disk('public')->assertExists($ruta);
        expect(Storage::disk('public')->get($ruta))->toBe($glb);
    }

    $this->assertDatabaseHas('productos', [
        'id' => $producto->id, 'estado' => 'inactivo', 'precio_venta' => 95, 'precio_compra_referencia' => 40,
    ]);
});

test('el catálogo no presenta como gratuito un producto sin precio asignado', function () {
    Producto::factory()->create(['nombre' => 'Tren sin precio', 'precio_venta' => null]);

    Livewire::test(Catalogo::class)
        ->assertSee('Tren sin precio')
        ->assertSee('Precio por confirmar')
        ->assertDontSee('0.00');
});
