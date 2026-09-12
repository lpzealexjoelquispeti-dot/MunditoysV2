<?php

use App\Livewire\Cliente\Catalogo;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Modelado3D;
use App\Models\Producto;
use App\Models\Proveedor;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermisosSeeder::class, RolesSeeder::class]);

    $this->categoriaBloques = Categoria::create(['nombre' => 'Bloques y Construcción', 'slug' => 'bloques-construccion']);
    $this->categoriaEducativos = Categoria::create(['nombre' => 'Educativos', 'slug' => 'educativos']);

    $this->marcaLego = Marca::create(['nombre' => 'Lego', 'slug' => 'lego']);
    $this->marcaPlaymobil = Marca::create(['nombre' => 'Playmobil', 'slug' => 'playmobil']);

    $this->proveedor = Proveedor::create([
        'empresa' => 'Distribuidora Central',
        'nombre_contacto' => 'Carlos',
        'nit' => '10293847',
        'telefono' => '71122334',
        'email' => 'ventas@distro.com',
        'direccion' => 'Av. Principal',
    ]);

    $this->producto1 = Producto::create([
        'categoria_id' => $this->categoriaBloques->id,
        'marca_id' => $this->marcaLego->id,
        'codigo_producto' => 'LEGO-001',
        'nombre' => 'Castillo Espacial Star',
        'slug' => 'castillo-espacial-star',
        'descripcion' => 'Bloques para armar nave estelar',
        'precio_base' => 100.00,
        'precio_venta' => 120.00,
        'edad_recomendada' => 8,
        'estado' => 'activo',
    ]);

    $this->producto2 = Producto::create([
        'categoria_id' => $this->categoriaEducativos->id,
        'marca_id' => $this->marcaPlaymobil->id,
        'codigo_producto' => 'PLAY-002',
        'nombre' => 'Laboratorio Químico Infantil',
        'slug' => 'laboratorio-quimico-infantil',
        'descripcion' => 'Experimentos seguros de ciencia',
        'precio_base' => 200.00,
        'precio_venta' => 250.00,
        'edad_recomendada' => 10,
        'estado' => 'activo',
    ]);

    // Asociar modelado 3D al producto 1
    Modelado3D::create([
        'producto_id' => $this->producto1->id,
        'task_id' => 'task-test-3d-001',
        'status' => 'success',
        'modelo_glb_path' => 'modelos/lego_star.glb',
    ]);
});

test('la pagina principal welcome se carga correctamente con productos recientes y buscador', function () {
    $response = $this->get(route('welcome'));

    $response->assertOk();
    $response->assertSee('MundiToys');
    $response->assertSee('¿Qué juguete estás buscando?');
    $response->assertSee('Castillo Espacial Star');
    $response->assertSee('Sobre Nosotros');
});

test('el catalogo de juguetes muestra los productos activos para clientes e invitados', function () {
    Livewire::test(Catalogo::class)
        ->assertOk()
        ->assertSee('Castillo Espacial Star')
        ->assertSee('Laboratorio Químico Infantil')
        ->assertSee('Bs. 120.00')
        ->assertSee('Bs. 250.00');
});

test('el catalogo filtra correctamente por busqueda de texto', function () {
    Livewire::test(Catalogo::class)
        ->set('buscar', 'Castillo')
        ->assertSee('Castillo Espacial Star')
        ->assertDontSee('Laboratorio Químico Infantil');
});

test('el catalogo filtra correctamente por categoria', function () {
    Livewire::test(Catalogo::class)
        ->set('categoriaId', $this->categoriaEducativos->id)
        ->assertSee('Laboratorio Químico Infantil')
        ->assertDontSee('Castillo Espacial Star');
});

test('el catalogo filtra correctamente por marca', function () {
    Livewire::test(Catalogo::class)
        ->set('marcaId', $this->marcaLego->id)
        ->assertSee('Castillo Espacial Star')
        ->assertDontSee('Laboratorio Químico Infantil');
});

test('el catalogo filtra correctamente por rango de precios', function () {
    Livewire::test(Catalogo::class)
        ->set('precioMin', '150')
        ->set('precioMax', '300')
        ->assertSee('Laboratorio Químico Infantil')
        ->assertDontSee('Castillo Espacial Star');
});

test('el catalogo filtra solo los productos que disponen de modelo 3D', function () {
    Livewire::test(Catalogo::class)
        ->set('solo3D', true)
        ->assertSee('Castillo Espacial Star')
        ->assertDontSee('Laboratorio Químico Infantil');
});

test('limpiar filtros restablece todos los parametros de consulta', function () {
    Livewire::test(Catalogo::class)
        ->set('buscar', 'prueba')
        ->set('categoriaId', '1')
        ->set('solo3D', true)
        ->call('limpiarFiltros')
        ->assertSet('buscar', '')
        ->assertSet('categoriaId', '')
        ->assertSet('solo3D', false);
});
