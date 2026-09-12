<?php

use App\Livewire\Cliente\Carrito;
use App\Livewire\Cliente\Checkout;
use App\Livewire\Cliente\CompraExitosa;
use App\Livewire\Cliente\SeguimientoRegistro;
use App\Mail\FacturaCompraMailable;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Entrega;
use App\Models\Inventario;
use App\Models\LoteInventario;
use App\Models\Marca;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\CarritoService;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([PermisosSeeder::class, RolesSeeder::class]);
});

test('el cliente puede ver la pagina del carrito con los juguetes seleccionados y totales', function (): void {
    $categoria = Categoria::factory()->create(['nombre' => 'Juegos de Mesa']);
    $marca = Marca::factory()->create(['nombre' => 'Hasbro']);

    $producto = Producto::factory()->create([
        'categoria_id' => $categoria->id,
        'marca_id' => $marca->id,
        'nombre' => 'Monopoly Edición Especial Bolivia',
        'codigo_producto' => 'MT-MONOP-BOL-100',
        'precio_venta' => 199.90,
        'estado' => 'activo',
    ]);

    $carritoService = app(CarritoService::class);
    $carritoService->vaciar();
    $carritoService->agregar($producto, 2);

    $this->get(route('tienda.carrito'))
        ->assertOk()
        ->assertSee('Monopoly Edición Especial Bolivia')
        ->assertSee('MT-MONOP-BOL-100')
        ->assertSee('Bs. 199.90')
        ->assertSee('Bs. 399.80')
        ->assertSee('Continuar al Pago');
});

test('el cliente puede incrementar, decrementar y eliminar productos del carrito interactivamente', function (): void {
    $producto = Producto::factory()->create([
        'nombre' => 'Lego Star Wars Nave',
        'precio_venta' => 150.00,
        'estado' => 'activo',
    ]);

    $carritoService = app(CarritoService::class);
    $carritoService->vaciar();
    $carritoService->agregar($producto, 1);

    Livewire::test(Carrito::class)
        ->assertSee('Lego Star Wars Nave')
        ->assertSee('Bs. 150.00')
        ->call('incrementar', $producto->id)
        ->assertSee('Bs. 300.00')
        ->call('decrementar', $producto->id)
        ->assertSee('Bs. 150.00')
        ->call('eliminar', $producto->id)
        ->assertSee('Tu carrito está actualmente vacío');
});

test('el cliente completa el checkout con recojo en sucursal, generando venta, entrega, notificacion y factura', function (): void {
    Mail::fake();

    $sucursal = Sucursal::factory()->create([
        'nombre' => 'Sucursal Calacoto Zona Sur',
        'direccion' => 'Calle 21 de Calacoto #500',
        'ciudad' => 'La Paz',
        'estado' => true,
    ]);

    $vendedor = User::factory()->create([
        'name' => 'Carlos Vendedor',
        'email' => 'carlos.vendedor@munditoys.bo',
        'sucursal_id' => $sucursal->id,
        'tipo_usuario' => 'empleado',
        'estado' => true,
    ]);
    $vendedor->assignRole('Vendedor');

    $almacen = Almacen::factory()->create([
        'sucursal_id' => $sucursal->id,
        'nombre' => 'Almacén Calacoto',
        'estado' => true,
    ]);

    $producto = Producto::factory()->create([
        'nombre' => 'Auto Radiocontrolado 4x4 Todo Terreno',
        'precio_venta' => 250.00,
        'estado' => 'activo',
    ]);

    $inventario = Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
    ]);

    LoteInventario::factory()->create([
        'inventario_id' => $inventario->id,
        'cantidad_inicial' => 10,
        'cantidad_disponible' => 10,
        'precio_venta' => 250.00,
        'estado' => 'activo',
    ]);

    // Agregar al carrito
    $carritoService = app(CarritoService::class);
    $carritoService->vaciar();
    $carritoService->agregar($producto, 2);

    Livewire::test(Checkout::class)
        ->assertSee('Sucursal Calacoto Zona Sur')
        ->assertSee('Recojo en Sucursal')
        ->assertSee('Pronto')
        ->set('sucursalId', $sucursal->id)
        ->set('nombreRetira', 'Alejandro Morales Quispe')
        ->set('ciRetira', '6894021 LP')
        ->set('telefonoRetira', '77712345')
        ->set('facturaNombre', 'Alejandro Morales Quispe')
        ->set('facturaNitCi', '6894021')
        ->set('facturaEmail', 'alejandro.morales@test.bo')
        ->call('rellenarTarjetaPrueba')
        ->call('procesarPago')
        ->assertHasNoErrors()
        ->assertRedirect();

    // Validar creación de Venta
    $venta = Venta::query()->where('cliente_id', '!=', null)->latest()->first();
    expect($venta)->not->toBeNull()
        ->and($venta->tipo_entrega)->toBe('recojo_tienda')
        ->and($venta->tipo_venta)->toBe('online')
        ->and($venta->estado)->toBe('pagado')
        ->and((float) $venta->total)->toBe(500.00);

    // Validar DetalleVenta
    expect($venta->detalles)->toHaveCount(1)
        ->and($venta->detalles->first()->cantidad)->toBe(2);

    // Validar Entrega en Sucursal
    $entrega = Entrega::query()->where('venta_id', $venta->id)->first();
    expect($entrega)->not->toBeNull()
        ->and($entrega->sucursal_origen_id)->toBe($sucursal->id)
        ->and($entrega->estado)->toBe('pendiente');

    // Validar Pago Stripe
    $pago = Pago::query()->where('venta_id', $venta->id)->first();
    expect($pago)->not->toBeNull()
        ->and($pago->metodo_pago)->toBe('stripe')
        ->and($pago->estado)->toBe('completado')
        ->and((float) $pago->monto)->toBe(500.00);

    // Validar Notificación al vendedor de la sucursal
    $notificacionVendedor = Notificacion::query()
        ->where('usuario_id', $vendedor->id)
        ->where('titulo', 'like', "%{$venta->numero_venta}%")
        ->first();
    expect($notificacionVendedor)->not->toBeNull();

    // Validar Envío de Correo con Factura PDF
    Mail::assertSent(FacturaCompraMailable::class, function (FacturaCompraMailable $mail) use ($venta) {
        return $mail->venta->id === $venta->id
            && $mail->hasTo('alejandro.morales@test.bo')
            && count($mail->attachments()) > 0;
    });

    // Validar que el carrito se vació
    expect($carritoService->obtenerItems())->toHaveCount(0);
});

test('la pantalla de compra exitosa muestra el qr y permite ocultarlo y descargar la factura', function (): void {
    $sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Miraflores']);
    $cliente = User::factory()->create(['name' => 'Carla Mendoza', 'ci_nit' => '4920192']);

    $venta = Venta::factory()->create([
        'numero_venta' => 'VTA-20260910-9999',
        'cliente_id' => $cliente->id,
        'tipo_entrega' => 'recojo_tienda',
        'tipo_venta' => 'online',
        'estado' => 'pagado',
        'subtotal' => 200.00,
        'total' => 200.00,
    ]);

    Entrega::query()->create([
        'venta_id' => $venta->id,
        'sucursal_origen_id' => $sucursal->id,
        'estado' => 'pendiente',
    ]);

    Pago::query()->create([
        'venta_id' => $venta->id,
        'metodo_pago' => 'stripe',
        'estado' => 'completado',
        'monto' => 200.00,
    ]);

    Livewire::test(CompraExitosa::class, ['numeroVenta' => $venta->numero_venta])
        ->assertOk()
        ->assertSee('VTA-20260910-9999')
        ->assertSee('Sucursal Miraflores')
        ->assertSee('👁️ Ocultar QR')
        ->call('toggleQr')
        ->assertSee('👁️ Ver QR')
        ->assertSee('Código QR Oculto')
        ->call('toggleQr')
        ->assertSee('👁️ Ocultar QR');

    $this->get(route('tienda.factura.descargar', ['numeroVenta' => $venta->numero_venta]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('el cliente puede ver sus pedidos y notificaciones en seguimiento y registro', function (): void {
    $cliente = User::factory()->create(['name' => 'David Morales']);
    $this->actingAs($cliente);

    $sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal El Prado']);

    $venta = Venta::factory()->create([
        'numero_venta' => 'VTA-20260910-8888',
        'cliente_id' => $cliente->id,
        'tipo_entrega' => 'recojo_tienda',
        'estado' => 'pagado',
        'total' => 350.00,
    ]);

    Entrega::query()->create([
        'venta_id' => $venta->id,
        'sucursal_origen_id' => $sucursal->id,
        'estado' => 'pendiente',
    ]);

    $notificacion = Notificacion::create([
        'usuario_id' => $cliente->id,
        'titulo' => 'Tu compra está lista para retiro',
        'mensaje' => 'Puedes pasar por Sucursal El Prado a recoger tu juguete.',
        'leido' => false,
    ]);

    Livewire::test(SeguimientoRegistro::class)
        ->assertSee('VTA-20260910-8888')
        ->assertSee('Sucursal El Prado')
        ->assertSee('Listo para Recojo')
        ->call('verQr', $venta->id)
        ->assertSee('Código de Retiro en Tienda')
        ->call('toggleQrVisibilidad')
        ->assertSee('Código QR Oculto')
        ->call('cerrarQr')
        ->call('cambiarPestana', 'notificaciones')
        ->assertSee('Tu compra está lista para retiro')
        ->call('marcarNotificacionLeida', $notificacion->id);

    expect($notificacion->fresh()->leido)->toBeTrue();
});

test('el asistente de checkout valida paso 1 y paso 2 en español antes de avanzar a la pasarela', function (): void {
    $sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal San Pedro']);
    $producto = Producto::factory()->create(['nombre' => 'Rompecabezas 1000 piezas', 'precio_venta' => 80.00]);

    $carritoService = app(CarritoService::class);
    $carritoService->vaciar();
    $carritoService->agregar($producto, 1);

    Livewire::test(Checkout::class)
        ->set('sucursalId', null)
        ->set('nombreRetira', '')
        ->set('ciRetira', '')
        ->set('telefonoRetira', '')
        ->call('avanzarPaso1')
        ->assertHasErrors(['sucursalId', 'nombreRetira', 'ciRetira', 'telefonoRetira'])
        ->assertSet('pasoActual', 1)
        ->set('sucursalId', $sucursal->id)
        ->set('nombreRetira', 'Carlos Condori')
        ->set('ciRetira', '5544332')
        ->set('telefonoRetira', '70011223')
        ->call('avanzarPaso1')
        ->assertHasNoErrors()
        ->assertSet('pasoActual', 2)
        ->assertSee('Fase 2 de 3')
        ->set('facturaEmail', 'invalido')
        ->call('avanzarPaso2')
        ->assertHasErrors(['facturaEmail'])
        ->assertSet('pasoActual', 2)
        ->set('facturaEmail', 'carlos.condori@correo.bo')
        ->call('avanzarPaso2')
        ->assertHasNoErrors()
        ->assertSet('pasoActual', 3)
        ->assertSee('Fase 3 de 3')
        ->call('retrocederPaso')
        ->assertSet('pasoActual', 2);
});
