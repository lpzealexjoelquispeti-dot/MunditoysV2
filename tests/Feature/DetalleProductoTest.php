<?php

use App\Livewire\Cliente\DetalleProducto;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Modelado3D;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\Resena;
use App\Models\User;
use App\Services\CarritoService;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([PermisosSeeder::class, RolesSeeder::class]);
});

test('el cliente puede ver la página de detalle del juguete con sus fotos y especificaciones', function (): void {
    $categoria = Categoria::factory()->create(['nombre' => 'Robótica Educativa']);
    $marca = Marca::factory()->create(['nombre' => 'TechPlay']);

    $producto = Producto::factory()->create([
        'categoria_id' => $categoria->id,
        'marca_id' => $marca->id,
        'nombre' => 'Robot Explorador Solar',
        'codigo_producto' => 'MT-ROBOTEXPL-123456',
        'precio_venta' => 320.50,
        'edad_recomendada' => '6-8',
        'dimensiones' => '25 x 18 x 12',
        'descripcion' => 'Robot solar programable con sensores de proximidad.',
        'estado' => 'activo',
    ]);

    ProductoImagen::factory()->create([
        'producto_id' => $producto->id,
        'ruta_archivo' => 'productos/robot_1.jpg',
        'orden' => 1,
    ]);

    Modelado3D::create([
        'producto_id' => $producto->id,
        'task_id' => 'task-test-1234',
        'modelo_glb_path' => 'modelos/robot.glb',
        'status' => 'completed',
    ]);

    $this->get(route('tienda.producto.detalle', ['codigo' => $producto->codigo_producto]))
        ->assertOk()
        ->assertSee('Robot Explorador Solar')
        ->assertSee('Robótica Educativa')
        ->assertSee('TechPlay')
        ->assertSee('MT-ROBOTEXPL-123456')
        ->assertSee('Bs. 320.50')
        ->assertSee('Modelo 3D Interactivo')
        ->assertSee('Añadir al Carrito')
        ->assertSee('Comprar Ahora');
});

test('la vista de detalle despliega las reseñas de los clientes con estrellas', function (): void {
    $producto = Producto::factory()->create([
        'nombre' => 'Pista de Autos Magnética',
        'codigo_producto' => 'MT-PISTAAUTOS-888999',
        'precio_venta' => 190.00,
    ]);

    $usuario = User::factory()->create(['name' => 'Carlos Mamani']);

    Resena::create([
        'producto_id' => $producto->id,
        'user_id' => $usuario->id,
        'calificacion' => 5,
        'comentario' => 'Excelente material y acabados muy seguros.',
        'estado' => 'activo',
    ]);

    $this->get(route('tienda.producto.detalle', ['codigo' => $producto->codigo_producto]))
        ->assertOk()
        ->assertSee('Carlos Mamani')
        ->assertSee('Excelente material y acabados muy seguros.')
        ->assertSee('Reseñas de este Juguete');
});

test('muestra productos relacionados en la sección podría gustarte', function (): void {
    $categoria = Categoria::factory()->create(['nombre' => 'Juegos de Mesa']);

    $productoPrincipal = Producto::factory()->create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Ajedrez Medieval de Madera',
        'codigo_producto' => 'MT-AJEDREZMED-111111',
        'precio_venta' => 150.00,
    ]);

    $productoRelacionado = Producto::factory()->create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Damas Chinas Deluxe',
        'codigo_producto' => 'MT-DAMASDELUX-222222',
        'precio_venta' => 95.00,
    ]);

    $this->get(route('tienda.producto.detalle', ['codigo' => $productoPrincipal->codigo_producto]))
        ->assertOk()
        ->assertSee('Podría gustarte')
        ->assertSee('Damas Chinas Deluxe')
        ->assertSee('Bs. 95.00');
});

test('el cliente puede agregar el producto al carrito e interactuar con la cantidad', function (): void {
    $producto = Producto::factory()->create([
        'nombre' => 'Set de Arte y Acuarelas',
        'codigo_producto' => 'MT-SETACUAREL-333444',
        'precio_venta' => 75.00,
    ]);

    Livewire::test(DetalleProducto::class, ['codigo' => $producto->codigo_producto])
        ->assertSet('cantidad', 1)
        ->call('incrementar')
        ->assertSet('cantidad', 2)
        ->call('decrementar')
        ->assertSet('cantidad', 1)
        ->call('agregarAlCarrito')
        ->assertDispatched('carrito-actualizado')
        ->assertDispatched('animar-al-carrito');

    expect(app(CarritoService::class)->totalArticulos())->toBe(1);
});

test('el botón comprar ahora agrega al carrito y redirige al checkout', function (): void {
    $producto = Producto::factory()->create([
        'nombre' => 'Rompecabezas 3D Castillo',
        'codigo_producto' => 'MT-CASTILLO3D-555666',
        'precio_venta' => 110.00,
    ]);

    Livewire::test(DetalleProducto::class, ['codigo' => $producto->codigo_producto])
        ->call('comprarAhora')
        ->assertRedirect(route('tienda.checkout'));

    expect(app(CarritoService::class)->totalArticulos())->toBe(1);
});
