<?php

use App\Livewire\Admin\MisReportes;
use App\Models\Categoria;
use App\Models\DetalleVenta;
use App\Models\Entrega;
use App\Models\Marca;
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

    $this->sucursal = Sucursal::create([
        'nombre' => 'Sucursal Central',
        'ciudad' => 'La Paz',
        'direccion' => 'Av. Principal #100',
        'telefono' => '22110011',
        'activo' => true,
    ]);

    $this->vendedor = User::factory()->create([
        'name' => 'Marcos Vendedor',
        'email' => 'marcos@munditoys.com',
        'sucursal_id' => $this->sucursal->id,
    ]);
    $this->vendedor->assignRole('Vendedor');

    $this->cliente = User::factory()->create([
        'name' => 'Pedro Cliente',
        'email' => 'pedro@cliente.com',
    ]);
    $this->cliente->assignRole('Cliente');

    $this->categoria = Categoria::create(['nombre' => 'Lego y Bloques', 'slug' => 'lego-bloques']);
    $this->marca = Marca::create(['nombre' => 'Lego', 'slug' => 'lego']);
    $this->proveedor = Proveedor::create([
        'empresa' => 'Distro Toys',
        'nombre_contacto' => 'Carlos',
        'nit' => '87654321',
        'telefono' => '71100223',
        'email' => 'distro@toys.com',
        'direccion' => 'Calle 1',
    ]);

    $this->producto = Producto::create([
        'categoria_id' => $this->categoria->id,
        'marca_id' => $this->marca->id,
        'codigo_producto' => 'LEGO-101',
        'nombre' => 'Castillo Medieval',
        'slug' => 'castillo-medieval',
        'descripcion' => 'Bloques para armar castillo',
        'precio_base' => 200.00,
        'edad_recomendada' => 10,
        'estado' => true,
    ]);

    // Crear una venta física (POS) para el vendedor
    $this->ventaPos = Venta::create([
        'numero_venta' => 'VTA-POS-20260909-0001',
        'cliente_id' => $this->cliente->id,
        'usuario_vendedor_id' => $this->vendedor->id,
        'tipo_venta' => 'presencial',
        'tipo_entrega' => 'inmediata',
        'estado' => 'completada',
        'subtotal' => 400.00,
        'descuento' => 0.00,
        'total' => 400.00,
        'fecha_venta' => now(),
    ]);

    Pago::create([
        'venta_id' => $this->ventaPos->id,
        'metodo_pago' => 'efectivo',
        'referencia_pago' => 'Efectivo en caja',
        'estado' => 'completado',
        'monto' => 400.00,
        'fecha_pago' => now(),
    ]);

    DetalleVenta::create([
        'venta_id' => $this->ventaPos->id,
        'producto_id' => $this->producto->id,
        'cantidad' => 2,
        'precio_unitario' => 200.00,
        'subtotal' => 400.00,
    ]);

    // Crear un pedido online con recojo en tienda
    $this->ventaRecojo = Venta::create([
        'numero_venta' => 'VTA-ONLINE-0001',
        'cliente_id' => $this->cliente->id,
        'tipo_venta' => 'online',
        'tipo_entrega' => 'recojo_tienda',
        'estado' => 'completada',
        'subtotal' => 200.00,
        'descuento' => 0.00,
        'total' => 200.00,
        'fecha_venta' => now(),
    ]);

    Entrega::create([
        'venta_id' => $this->ventaRecojo->id,
        'sucursal_origen_id' => $this->sucursal->id,
        'empleado_id' => $this->vendedor->id,
        'estado' => 'entregado',
        'fecha_programada' => now()->toDateString(),
        'hora_fin' => '14:30:00',
    ]);
});

test('un vendedor puede ingresar a su panel de reportes', function () {
    $this->actingAs($this->vendedor);

    $response = $this->get(route('admin.mis-reportes'));
    $response->assertOk();
    $response->assertSee('Mis Reportes de Ventas');
    $response->assertSee('Ventas Físicas (Mostrador / POS)');
    $response->assertSee('Ventas de Recojo en Sucursal');
});

test('un cliente no tiene permiso para ver los reportes del vendedor', function () {
    $this->actingAs($this->cliente);

    $response = $this->get(route('admin.mis-reportes'));
    $response->assertForbidden();
});

test('valida logicamente las fechas impidiendo que fechaDesde sea mayor a fechaHasta', function () {
    $this->actingAs($this->vendedor);

    Livewire::test(MisReportes::class)
        ->set('fechaDesde', now()->toDateString())
        ->set('fechaHasta', now()->subDays(2)->toDateString())
        ->assertHasErrors(['fechaDesde', 'fechaHasta'])
        ->assertSee('no puede ser posterior a la fecha final');
});

test('valida logicamente que no se puedan consultar fechas futuras', function () {
    $this->actingAs($this->vendedor);

    $manana = now()->addDays(2)->toDateString();

    Livewire::test(MisReportes::class)
        ->set('fechaHasta', $manana)
        ->assertHasErrors(['fechaHasta'])
        ->assertSee('No es posible consultar fechas futuras');
});

test('los presets de periodo configuran las fechas correctamente', function () {
    $this->actingAs($this->vendedor);

    Livewire::test(MisReportes::class)
        ->call('aplicarPreset', 'hoy')
        ->assertSet('fechaDesde', now()->toDateString())
        ->assertSet('fechaHasta', now()->toDateString())
        ->call('aplicarPreset', 'ultimos_7_dias')
        ->assertSet('fechaDesde', now()->subDays(6)->toDateString())
        ->assertSet('fechaHasta', now()->toDateString());
});

test('calcula correctamente los kpis de ventas fisicas de mostrador para el vendedor', function () {
    $this->actingAs($this->vendedor);

    Livewire::test(MisReportes::class)
        ->set('seccion', 'ventas_fisicas')
        ->call('aplicarPreset', 'este_mes')
        ->assertSee('Bs. 400.00')
        ->assertSee('Castillo Medieval')
        ->assertSee('Efectivo');
});

test('calcula correctamente los kpis de pedidos de recojo en sucursal', function () {
    $this->actingAs($this->vendedor);

    Livewire::test(MisReportes::class)
        ->set('seccion', 'recojos_sucursal')
        ->call('aplicarPreset', 'este_mes')
        ->assertSee('Pedidos para Recojo')
        ->assertSee('VTA-ONLINE-0001')
        ->assertSee('Bs. 200.00');
});

test('permite exportar el reporte a formato PDF', function () {
    $this->actingAs($this->vendedor);

    Livewire::test(MisReportes::class)
        ->call('exportarPdf')
        ->assertFileDownloaded();
});

test('permite exportar el reporte a formato Excel', function () {
    $this->actingAs($this->vendedor);

    Livewire::test(MisReportes::class)
        ->call('exportarExcel')
        ->assertFileDownloaded();
});
