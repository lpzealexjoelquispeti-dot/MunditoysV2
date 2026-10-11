<?php

use App\Actions\Ventas\ProcesarVentaPosAction;
use App\Actions\Ventas\RegistrarClienteExpressAction;
use App\Livewire\Admin\Home;
use App\Livewire\Admin\Ventas;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\LoteInventario;
use App\Models\Marca;
use App\Models\Modelado3D;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\VentaPosService;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermisosSeeder::class, RolesSeeder::class]);

    // Crear sucursales y almacenes
    $this->sucursal1 = Sucursal::create([
        'nombre' => 'Sucursal Central La Paz',
        'ciudad' => 'La Paz',
        'direccion' => 'Av. 16 de Julio #1234',
        'telefono' => '22441100',
        'estado' => true,
    ]);
    $this->almacen1 = Almacen::create([
        'sucursal_id' => $this->sucursal1->id,
        'nombre' => 'Almacén Mostrador Central',
        'tipo' => 'tienda',
        'estado' => true,
    ]);

    $this->sucursal2 = Sucursal::create([
        'nombre' => 'Sucursal Calacoto',
        'ciudad' => 'La Paz',
        'direccion' => 'Calle 21 de Calacoto',
        'telefono' => '22770011',
        'estado' => true,
    ]);
    $this->almacen2 = Almacen::create([
        'sucursal_id' => $this->sucursal2->id,
        'nombre' => 'Almacén Calacoto',
        'tipo' => 'tienda',
        'estado' => true,
    ]);

    // Proveedor, Categoría y Marca
    $this->proveedor = Proveedor::create([
        'empresa' => 'ToyDistributor Bolivia',
        'contacto_nombre' => 'Carlos Distribuidor',
        'email' => 'ventas@toydist.bo',
        'telefono' => '70123456',
        'estado' => true,
    ]);

    $this->categoria = Categoria::create([
        'nombre' => 'Juegos de Mesa',
        'descripcion' => 'Juegos familiares y de estrategia',
        'estado' => true,
    ]);

    $this->marca = Marca::create([
        'nombre' => 'Hasbro',
        'estado' => true,
    ]);

    // Usuario Vendedor
    $this->vendedor = User::factory()->create([
        'name' => 'Marcos',
        'apellido_paterno' => 'Vendedor',
        'email' => 'marcos.vendedor@munditoys.bo',
    ]);
    $this->vendedor->assignRole('Vendedor');

    // Usuario Administrador
    $this->admin = User::factory()->create([
        'name' => 'Admin Boss',
        'email' => 'admin@munditoys.bo',
    ]);
    $this->admin->assignRole('Administrador');
});

test('vendedor y administrador pueden acceder a ventas POS pero cliente o invitado no', function () {
    // Invitado redirige
    $this->get(route('admin.ventas'))->assertRedirect(route('login'));

    // Cliente es forbidden
    $cliente = User::factory()->create();
    $cliente->assignRole('Cliente');
    $this->actingAs($cliente)->get(route('admin.ventas'))->assertForbidden();

    // Vendedor accede correctamente
    $this->actingAs($this->vendedor)->get(route('admin.ventas'))->assertOk()
        ->assertSee('Punto de venta')
        ->assertSee('Mostrador');

    // Administrador accede correctamente
    $this->actingAs($this->admin)->get(route('admin.ventas'))->assertOk();
});

test('dashboard del vendedor muestra estilo idéntico pero solo opciones de ventas y botones pronto', function () {
    $this->actingAs($this->vendedor);

    Livewire::test(Home::class)
        ->assertSee('Registrar venta')
        ->assertSee('Catálogo')
        ->assertSee('Recoger en sucursal')
        ->assertSee('Mis reportes')
        ->assertDontSee('Usuarios y accesos')
        ->assertDontSee('Proveedores')
        ->assertDontSee('Fotogrametría 3D');
});

test('busqueda pos muestra stock en sucursal actual y existencia en otras sucursales', function () {
    $producto = Producto::create([
        'nombre' => 'Monopoly Clásico Bolivia',
        'codigo_producto' => 'JUG-MNP-01',
        'precio_venta' => 180.00,
        'categoria_id' => $this->categoria->id,
        'marca_id' => $this->marca->id,
        'estado' => 'activo',
    ]);

    // Crear inventario y lotes en Sucursal 1 (15 unidades)
    $inv1 = Inventario::create([
        'producto_id' => $producto->id,
        'almacen_id' => $this->almacen1->id,
        'stock_minimo' => 5,
    ]);
    LoteInventario::create([
        'inventario_id' => $inv1->id,
        'proveedor_id' => $this->proveedor->id,
        'codigo_lote' => 'LOTE-1-A',
        'cantidad_inicial' => 15,
        'cantidad_disponible' => 15,
        'costo_unitario' => 100.00,
        'precio_venta' => 180.00,
        'fecha_ingreso' => now()->subDays(5),
        'estado' => 'activo',
    ]);

    // Crear inventario y lotes en Sucursal 2 (8 unidades)
    $inv2 = Inventario::create([
        'producto_id' => $producto->id,
        'almacen_id' => $this->almacen2->id,
        'stock_minimo' => 5,
    ]);
    LoteInventario::create([
        'inventario_id' => $inv2->id,
        'proveedor_id' => $this->proveedor->id,
        'codigo_lote' => 'LOTE-2-B',
        'cantidad_inicial' => 8,
        'cantidad_disponible' => 8,
        'costo_unitario' => 100.00,
        'precio_venta' => 180.00,
        'fecha_ingreso' => now()->subDays(2),
        'estado' => 'activo',
    ]);

    // Crear modelado 3D activo para verificar badge
    Modelado3D::create([
        'producto_id' => $producto->id,
        'task_id' => 'task_test_12345',
        'status' => 'success',
        'tipo_generacion' => 'text_to_model',
        'modelo_glb_path' => 'modelos3d/monopoly.glb',
    ]);

    $service = app(VentaPosService::class);
    $resultado = $service->buscarJuguetesParaPos('Monopoly', $this->sucursal1->id);

    expect($resultado)->toHaveCount(1);
    $juguete = $resultado[0];
    expect($juguete['nombre'])->toBe('Monopoly Clásico Bolivia')
        ->and($juguete['tiene_modelado_3d'])->toBeTrue()
        ->and($juguete['stock_sucursal_actual'])->toBe(15);

    // Debe mostrar la existencia en la Sucursal 2
    $otraSucursal = collect($juguete['desglose_otras_sucursales'])->firstWhere('sucursal_id', $this->sucursal2->id);
    expect($otraSucursal)->not->toBeNull()
        ->and($otraSucursal['stock'])->toBe(8);
});

test('vendedor puede gestionar carrito con control de stock maximo en la sucursal', function () {
    $this->actingAs($this->vendedor);

    $producto = Producto::create([
        'nombre' => 'Cubo Rubik 3x3',
        'codigo_producto' => 'JUG-RBK-01',
        'precio_venta' => 50.00,
        'categoria_id' => $this->categoria->id,
        'marca_id' => $this->marca->id,
        'estado' => 'activo',
    ]);

    $inv = Inventario::create([
        'producto_id' => $producto->id,
        'almacen_id' => $this->almacen1->id,
        'stock_minimo' => 2,
    ]);

    LoteInventario::create([
        'inventario_id' => $inv->id,
        'proveedor_id' => $this->proveedor->id,
        'codigo_lote' => 'LOTE-RBK',
        'cantidad_inicial' => 3,
        'cantidad_disponible' => 3,
        'costo_unitario' => 25.00,
        'precio_venta' => 50.00,
        'fecha_ingreso' => now(),
        'estado' => 'activo',
    ]);

    Livewire::test(Ventas::class)
        ->set('sucursalId', $this->sucursal1->id)
        ->call('agregarAlCarrito', $producto->id)
        ->assertCount('carrito', 1)
        ->assertSee('Cubo Rubik 3x3')
        ->assertSee('Bs. 50.00')
        // Incrementar hasta el máximo disponible (3)
        ->call('incrementarCantidad', $producto->id)
        ->call('incrementarCantidad', $producto->id)
        // Intentar sobrepasar el límite de 3
        ->call('incrementarCantidad', $producto->id)
        ->assertDispatched('alerta-producto');
});

test('registro express de cliente valida datos en espanol y asigna rol cliente', function () {
    $action = app(RegistrarClienteExpressAction::class);

    // Falla por campos requeridos con mensajes en español
    try {
        $action->ejecutar([
            'name' => '',
            'apellido_paterno' => '',
            'apellido_materno' => '',
            'ci_nit' => '',
            'telefono' => '',
            'email' => '',
        ]);
        $this->fail('Debió lanzar ValidationException');
    } catch (ValidationException $e) {
        $errores = $e->errors();
        expect($errores)->toHaveKey('name')
            ->and($errores['name'][0])->toContain('El nombre del cliente es obligatorio.')
            ->and($errores)->toHaveKey('ci_nit')
            ->and($errores['ci_nit'][0])->toContain('El documento de identidad (CI o NIT) es obligatorio.')
            ->and($errores)->toHaveKey('telefono')
            ->and($errores['telefono'][0])->toContain('El teléfono o celular de contacto es obligatorio.');
    }

    // Registro exitoso con número válido boliviano
    $cliente = $action->ejecutar([
        'name' => 'Valeria',
        'apellido_paterno' => 'Mendoza',
        'apellido_materno' => 'Quiroga',
        'ci_nit' => '8942104-LP',
        'telefono' => '71234567',
        'email' => 'valeria@ejemplo.bo',
    ]);

    expect($cliente->exists)->toBeTrue()
        ->and($cliente->name)->toBe('Valeria')
        ->and($cliente->ci_nit)->toBe('8942104-LP')
        ->and($cliente->hasRole('Cliente'))->toBeTrue();
});

test('simulaciones de pago QR y calculo de cambio en efectivo funcionan fluidamente', function () {
    $this->actingAs($this->vendedor);

    Livewire::test(Ventas::class)
        ->set('metodoPago', 'efectivo')
        ->set('montoRecibido', 100.00)
        ->assertSet('cambio', 100.00) // Con carrito vacío cambio es 100
        ->set('metodoPago', 'qr')
        ->call('simularAcreditacionQr')
        ->assertSet('qrAcreditado', true)
        ->assertDispatched('alerta-producto')
        ->set('metodoPago', 'tarjeta')
        ->call('simularCobroTarjeta')
        ->assertDispatched('alerta-producto');
});

test('procesar venta POS descuenta inventario FIFO, crea registros y genera ticket', function () {
    $producto = Producto::create([
        'nombre' => 'LEGO Castillo Medieval',
        'codigo_producto' => 'JUG-LGO-01',
        'precio_venta' => 300.00,
        'categoria_id' => $this->categoria->id,
        'marca_id' => $this->marca->id,
        'estado' => 'activo',
    ]);

    $inv = Inventario::create([
        'producto_id' => $producto->id,
        'almacen_id' => $this->almacen1->id,
        'stock_minimo' => 2,
    ]);

    // Dos lotes: FIFO Lote 1 con 2 unidades, Lote 2 con 5 unidades
    $lote1 = LoteInventario::create([
        'inventario_id' => $inv->id,
        'proveedor_id' => $this->proveedor->id,
        'codigo_lote' => 'LOTE-FIFO-1',
        'cantidad_inicial' => 2,
        'cantidad_disponible' => 2,
        'costo_unitario' => 150.00,
        'precio_venta' => 300.00,
        'fecha_ingreso' => now()->subDays(10),
        'estado' => 'activo',
    ]);

    $lote2 = LoteInventario::create([
        'inventario_id' => $inv->id,
        'proveedor_id' => $this->proveedor->id,
        'codigo_lote' => 'LOTE-FIFO-2',
        'cantidad_inicial' => 5,
        'cantidad_disponible' => 5,
        'costo_unitario' => 160.00,
        'precio_venta' => 300.00,
        'fecha_ingreso' => now()->subDays(2),
        'estado' => 'activo',
    ]);

    $action = app(ProcesarVentaPosAction::class);

    // Vendemos 3 unidades: debe agotar Lote 1 (2 unidades) y tomar 1 unidad de Lote 2
    $resultado = $action->ejecutar(
        usuarioVendedorId: $this->vendedor->id,
        sucursalId: $this->sucursal1->id,
        almacenId: $this->almacen1->id,
        clienteId: null,
        items: [
            ['producto_id' => $producto->id, 'cantidad' => 3, 'precio_unitario' => 300.00],
        ],
        metodoPago: 'efectivo',
        montoRecibido: 1000.00,
        nombreClienteMostrador: 'Juan Perez',
        ciNitClienteMostrador: '4820194'
    );

    $venta = $resultado['venta'];
    $recibo = $resultado['recibo'];

    expect($venta->total)->toBe('900.00')
        ->and($recibo['numero_venta'])->toBe($venta->numero_venta)
        ->and($recibo['cambio'])->toBe(100.0)
        ->and($recibo['cliente_nombre'])->toBe('Juan Perez')
        ->and($recibo['cliente_ci_nit'])->toBe('4820194');

    // Verificar descuento FIFO en los lotes
    $lote1->refresh();
    $lote2->refresh();

    expect($lote1->cantidad_disponible)->toBe(0)
        ->and($lote2->cantidad_disponible)->toBe(4);

    // Verificar que se crearon los detalles de venta con los lotes correspondientes
    expect($venta->detalles)->toHaveCount(2);
    expect($venta->pago)->not->toBeNull()
        ->and($venta->pago->metodo_pago)->toBe('efectivo')
        ->and($venta->pago->monto)->toBe('900.00');
});

test('flujo interactivo livewire de venta en mostrador abre el modal de recibo con datos correctos', function () {
    $this->actingAs($this->vendedor);

    $producto = Producto::create([
        'nombre' => 'Juego Jenga Clásico',
        'codigo_producto' => 'JUG-JNG-01',
        'precio_venta' => 120.00,
        'categoria_id' => $this->categoria->id,
        'marca_id' => $this->marca->id,
        'estado' => 'activo',
    ]);

    $inv = Inventario::create([
        'producto_id' => $producto->id,
        'almacen_id' => $this->almacen1->id,
        'stock_minimo' => 2,
    ]);

    LoteInventario::create([
        'inventario_id' => $inv->id,
        'proveedor_id' => $this->proveedor->id,
        'codigo_lote' => 'LOTE-JNG-1',
        'cantidad_inicial' => 10,
        'cantidad_disponible' => 10,
        'costo_unitario' => 60.00,
        'precio_venta' => 120.00,
        'fecha_ingreso' => now(),
        'estado' => 'activo',
    ]);

    Livewire::test(Ventas::class)
        ->set('sucursalId', $this->sucursal1->id)
        ->set('almacenId', $this->almacen1->id)
        ->call('agregarAlCarrito', $producto->id)
        ->assertCount('carrito', 1)
        ->set('metodoPago', 'efectivo')
        ->set('montoRecibido', 200.00)
        ->call('procesarVenta')
        ->assertSet('mostrarModalRecibo', true)
        ->assertCount('carrito', 0)
        ->assertSee('MundiToys S.R.L.')
        ->assertSee('Bs. 120.00')
        ->assertSee('Imprimir Ticket')
        ->call('iniciarNuevaVenta')
        ->assertSet('mostrarModalRecibo', false)
        ->assertSet('reciboActual', null);
});
