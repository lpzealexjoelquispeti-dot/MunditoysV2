<?php

use App\Livewire\Admin\RecojoSucursal;
use App\Models\Categoria;
use App\Models\DetalleVenta;
use App\Models\Entrega;
use App\Models\Marca;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermisosSeeder::class, RolesSeeder::class]);

    $this->sucursalCentral = Sucursal::create([
        'nombre' => 'Sucursal Central',
        'ciudad' => 'La Paz',
        'direccion' => 'Av. Principal #100',
        'telefono' => '22110011',
        'activo' => true,
    ]);

    $this->sucursalSur = Sucursal::create([
        'nombre' => 'Sucursal Zona Sur',
        'ciudad' => 'La Paz',
        'direccion' => 'Calle 21 Calacoto #500',
        'telefono' => '22778899',
        'activo' => true,
    ]);

    $this->vendedorCentral = User::factory()->create([
        'name' => 'Carlos Vendedor Central',
        'email' => 'carlos@munditoys.com',
        'estado' => true,
        'sucursal_id' => $this->sucursalCentral->id,
    ]);
    $this->vendedorCentral->assignRole('Vendedor');

    $this->cliente = User::factory()->create([
        'name' => 'Ana Compradora',
        'email' => 'ana@cliente.com',
        'ci_nit' => '8493021',
        'telefono' => '70123456',
    ]);
    $this->cliente->assignRole('Cliente');

    $this->categoria = Categoria::create(['nombre' => 'Educativos', 'slug' => 'educativos']);
    $this->marca = Marca::create(['nombre' => 'Lego', 'slug' => 'lego']);
    $this->proveedor = Proveedor::create([
        'empresa' => 'Distribuidora Toys',
        'nombre_contacto' => 'Juan',
        'nit' => '12345678',
        'telefono' => '71122334',
        'email' => 'toys@proveedor.com',
        'direccion' => 'Zona Industrial',
    ]);

    $this->producto = Producto::create([
        'categoria_id' => $this->categoria->id,
        'marca_id' => $this->marca->id,
        'codigo_producto' => 'JUG-001',
        'nombre' => 'Robot Interactivo 3D',
        'slug' => 'robot-interactivo-3d',
        'descripcion' => 'Robot programable',
        'precio_base' => 150.00,
        'edad_recomendada' => 8,
        'estado' => true,
    ]);

    // Crear un pedido online con recojo en tienda para Sucursal Central
    $this->ventaCentral = Venta::create([
        'numero_venta' => 'VTA-RECOJO-0001',
        'cliente_id' => $this->cliente->id,
        'tipo_venta' => 'online',
        'tipo_entrega' => 'recojo_tienda',
        'estado' => 'pagada',
        'subtotal' => 300.00,
        'descuento' => 0.00,
        'total' => 300.00,
        'fecha_venta' => now(),
    ]);

    $this->entregaCentral = Entrega::create([
        'venta_id' => $this->ventaCentral->id,
        'sucursal_origen_id' => $this->sucursalCentral->id,
        'estado' => 'pendiente',
        'fecha_programada' => now()->toDateString(),
    ]);

    $this->pagoCentral = Pago::create([
        'venta_id' => $this->ventaCentral->id,
        'metodo_pago' => 'stripe',
        'referencia_pago' => 'STRIPE-TEST-12345',
        'estado' => 'completado',
        'monto' => 300.00,
        'fecha_pago' => now(),
    ]);

    DetalleVenta::create([
        'venta_id' => $this->ventaCentral->id,
        'producto_id' => $this->producto->id,
        'cantidad' => 2,
        'precio_unitario' => 150.00,
        'subtotal' => 300.00,
    ]);

    // Crear otro pedido para la Sucursal Sur
    $this->ventaSur = Venta::create([
        'numero_venta' => 'VTA-RECOJO-0002',
        'cliente_id' => $this->cliente->id,
        'tipo_venta' => 'online',
        'tipo_entrega' => 'recojo_tienda',
        'estado' => 'pagada',
        'subtotal' => 150.00,
        'descuento' => 0.00,
        'total' => 150.00,
        'fecha_venta' => now(),
    ]);

    $this->entregaSur = Entrega::create([
        'venta_id' => $this->ventaSur->id,
        'sucursal_origen_id' => $this->sucursalSur->id,
        'estado' => 'pendiente',
        'fecha_programada' => now()->toDateString(),
    ]);
});

test('un vendedor puede ingresar a la vista de recojo en sucursal y ver los pedidos de su sucursal', function () {
    $this->actingAs($this->vendedorCentral);

    $response = $this->get(route('admin.recojo-sucursal'));
    $response->assertOk();
    $response->assertSee('Recojo en sucursal');
    $response->assertSee('VTA-RECOJO-0001');
    $response->assertDontSee('VTA-RECOJO-0002');
});

test('un usuario sin permisos no puede acceder al modulo de recojo en sucursal', function () {
    $this->actingAs($this->cliente);

    $response = $this->get(route('admin.recojo-sucursal'));
    $response->assertForbidden();
});

test('el componente Livewire permite buscar y verificar un pedido por su codigo QR', function () {
    $this->actingAs($this->vendedorCentral);

    Livewire::test(RecojoSucursal::class)
        ->set('codigoQR', 'VTA-RECOJO-0001')
        ->call('procesarCodigoQR')
        ->assertSet('pedidoSeleccionadoId', $this->ventaCentral->id)
        ->assertSet('tipoAlerta', 'success')
        ->assertSee('Robot Interactivo 3D');
});

test('advierte si el codigo QR escaneado pertenece a otra sucursal', function () {
    $this->actingAs($this->vendedorCentral);

    Livewire::test(RecojoSucursal::class)
        ->set('codigoQR', 'VTA-RECOJO-0002')
        ->call('procesarCodigoQR')
        ->assertSet('pedidoSeleccionadoId', $this->ventaSur->id)
        ->assertSet('tipoAlerta', 'warning')
        ->assertSee('Sucursal Zona Sur');
});

test('la opcion de simular escaneo QR selecciona automaticamente un pedido pendiente', function () {
    $this->actingAs($this->vendedorCentral);

    Livewire::test(RecojoSucursal::class)
        ->call('simularEscaneoQR')
        ->assertSet('pedidoSeleccionadoId', $this->ventaCentral->id)
        ->assertSet('tipoAlerta', 'success');
});

test('no se puede marcar como entregado un pedido sin antes validar el codigo QR del cliente', function () {
    $this->actingAs($this->vendedorCentral);

    Livewire::test(RecojoSucursal::class)
        ->call('seleccionarPedido', $this->ventaCentral->id)
        ->assertSet('qrValidado', false)
        ->call('confirmarEntrega')
        ->assertSet('tipoAlerta', 'error')
        ->assertSee('Validación requerida');

    $this->entregaCentral->refresh();
    expect($this->entregaCentral->estado)->toBe('pendiente');
});

test('rechaza un codigo QR erroneo que no corresponde al pedido', function () {
    $this->actingAs($this->vendedorCentral);

    Livewire::test(RecojoSucursal::class)
        ->call('seleccionarPedido', $this->ventaCentral->id)
        ->set('codigoQrModal', 'CODIGO-INCORRECTO-999')
        ->call('validarQrPedidoModal')
        ->assertSet('qrValidado', false)
        ->assertSet('tipoAlerta', 'error')
        ->assertSee('no coincide con este pedido');
});

test('permite marcar como entregado tras validar el codigo QR del cliente con exito', function () {
    $this->actingAs($this->vendedorCentral);

    Livewire::test(RecojoSucursal::class)
        ->call('seleccionarPedido', $this->ventaCentral->id)
        ->set('codigoQrModal', "MUNDITOYS-RECOJO:{$this->ventaCentral->numero_venta}")
        ->call('validarQrPedidoModal')
        ->assertSet('qrValidado', true)
        ->assertSet('tipoAlerta', 'success')
        ->call('confirmarEntrega')
        ->assertSet('tipoAlerta', 'success');

    $this->entregaCentral->refresh();
    $this->ventaCentral->refresh();

    expect($this->entregaCentral->estado)->toBe('entregado')
        ->and($this->entregaCentral->empleado_id)->toBe($this->vendedorCentral->id)
        ->and($this->ventaCentral->estado)->toBe('completada');

    // Comprobar que se generó notificación al cliente
    expect(Notificacion::query()->where('usuario_id', $this->cliente->id)->exists())->toBeTrue();
});

test('permite descargar la ficha de entrega en PDF correctamente', function () {
    $this->actingAs($this->vendedorCentral);

    Livewire::test(RecojoSucursal::class)
        ->call('descargarFichaPdf', $this->ventaCentral->id)
        ->assertFileDownloaded("ficha_entrega_{$this->ventaCentral->numero_venta}.pdf");
});

test('el metodo procesarCodigoEscaneado procesa directamente el QR leido por la camara y abre el pedido validado', function () {
    $this->actingAs($this->vendedorCentral);

    Livewire::test(RecojoSucursal::class)
        ->set('modalCamaraAbierto', true)
        ->call('procesarCodigoEscaneado', "MUNDITOYS-RECOJO:{$this->ventaCentral->numero_venta}")
        ->assertSet('modalCamaraAbierto', false)
        ->assertSet('pedidoSeleccionadoId', $this->ventaCentral->id)
        ->assertSet('qrValidado', true)
        ->assertSet('tipoAlerta', 'success')
        ->assertSee('¡Código QR Validado Exitosamente!')
        ->assertSee('Confirmar y Marcar como Entregado');
});
