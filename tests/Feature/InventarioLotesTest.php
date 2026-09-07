<?php

use App\Actions\Inventario\RegistrarLoteAction;
use App\Livewire\Admin\Inventario as InventarioComponente;
use App\Models\Almacen;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\LoteInventario;
use App\Models\Producto;
use App\Models\ProductoProveedor;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\CalculoStockMinimoService;
use App\Services\InventarioService;
use App\Services\PrecioLoteService;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermisosSeeder::class, RolesSeeder::class]);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrador');
    $this->actingAs($this->admin);
});

function datosIngresoValidos(array $cambios = []): array
{
    $almacen = Almacen::factory()->create();

    return array_replace([
        'productoId' => (string) Producto::factory()->create(['nombre' => 'Tren educativo'])->id,
        'sucursalId' => (string) $almacen->sucursal_id, 'almacenId' => (string) $almacen->id,
        'proveedorId' => (string) Proveedor::factory()->create()->id,
        'cantidad' => '10', 'stockMinimo' => '5', 'costoUnitario' => '80.00', 'margen' => '20',
        'fechaIngreso' => today()->format('Y-m-d'), 'fechaVencimiento' => '',
        'claveIngreso' => (string) Str::uuid(),
    ], $cambios);
}

test('cada lote conserva proveedor y precios y cada almacén conserva su stock', function () {
    $datos = datosIngresoValidos();
    $service = app(InventarioService::class);
    $primero = $service->registrarLote($datos);
    $otroProveedor = Proveedor::factory()->create();
    $segundo = $service->registrarLote(array_replace($datos, [
        'proveedorId' => (string) $otroProveedor->id, 'claveIngreso' => (string) Str::uuid(),
        'cantidad' => '3', 'costoUnitario' => '60', 'margen' => '25', 'stockMinimo' => '99',
    ]));
    $otroAlmacen = Almacen::factory()->create();
    $tercero = $service->registrarLote(array_replace($datos, [
        'almacenId' => (string) $otroAlmacen->id, 'sucursalId' => (string) $otroAlmacen->sucursal_id,
        'claveIngreso' => (string) Str::uuid(), 'cantidad' => '4', 'costoUnitario' => '40',
    ]));
    expect($primero->precio_venta)->toBe('100.00')->and($segundo->precio_venta)->toBe('80.00')
        ->and($tercero->precio_venta)->toBe('50.00')
        ->and($segundo->inventario_id)->toBe($primero->inventario_id)
        ->and($tercero->inventario_id)->not->toBe($primero->inventario_id)
        ->and($primero->inventario->stock_minimo)->toBe(5);
    $this->assertDatabaseCount('inventarios', 2);
    $this->assertDatabaseCount('lotes_inventario', 3);
    $this->assertDatabaseCount('movimientos_inventario', 3);
    $this->assertDatabaseHas('movimientos_inventario', [
        'lote_id' => $primero->id, 'usuario_id' => $this->admin->id, 'tipo_movimiento' => 'entrada',
        'cantidad' => 10, 'cantidad_anterior' => 0, 'cantidad_nueva' => 10,
    ]);
    expect($service->obtenerDetalle($primero->inventario_id)['stock'])->toBe(13)
        ->and($service->obtenerDetalle($tercero->inventario_id)['stock'])->toBe(4);
});

test('un reintento no duplica ingresos ni acepta cambiar sus datos', function () {
    $datos = datosIngresoValidos();
    $service = app(InventarioService::class);
    $lote = $service->registrarLote($datos);
    expect($service->registrarLote($datos)->id)->toBe($lote->id);
    foreach ([
        ['cantidad' => '11'], ['costoUnitario' => '90'],
        ['proveedorId' => ''], ['fechaIngreso' => today()->subDay()->format('Y-m-d')],
    ] as $cambio) {
        expect(fn () => $service->registrarLote(array_replace($datos, $cambio)))->toThrow(ValidationException::class);
    }
    $this->assertDatabaseCount('lotes_inventario', 1);
    $this->assertDatabaseCount('movimientos_inventario', 1);
});

test('un fallo al registrar revierte la transacción completa', function () {
    $datos = datosIngresoValidos();
    $this->mock(RegistrarLoteAction::class)->shouldReceive('ejecutar')->once()->andThrow(new RuntimeException('Fallo simulado'));
    expect(fn () => app(InventarioService::class)->registrarLote($datos))->toThrow(RuntimeException::class);
    $this->assertDatabaseCount('inventarios', 0);
    $this->assertDatabaseCount('lotes_inventario', 0);
    $this->assertDatabaseCount('movimientos_inventario', 0);
});

test('valida importes cantidades y fechas antes de escribir', function ($campo, $valor) {
    $datos = datosIngresoValidos([$campo => $valor]);
    try {
        app(InventarioService::class)->registrarLote($datos);
        $this->fail('Se aceptó un ingreso inválido.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($campo);
    }
    $this->assertDatabaseCount('inventarios', 0);
    $this->assertDatabaseCount('lotes_inventario', 0);
})->with([
    ['cantidad', '0'], ['cantidad', '-1'], ['cantidad', '1.5'], ['cantidad', '1000001'],
    ['stockMinimo', '-1'], ['costoUnitario', '0'], ['costoUnitario', 'xx'],
    ['costoUnitario', '1e2'], ['costoUnitario', '10.123'], ['margen', '100'], ['margen', '0'],
    ['fechaIngreso', '2099-01-01'], ['fechaIngreso', '2026-02-30'], ['fechaVencimiento', '2000-01-01'],
    ['proveedorId', '999999'],
]);

test('rechaza almacenes de otra sucursal y entidades inactivas o borradas', function () {
    $datos = datosIngresoValidos();
    $otro = Sucursal::factory()->create();
    expect(fn () => app(InventarioService::class)->registrarLote(array_replace($datos, ['sucursalId' => (string) $otro->id])))
        ->toThrow(ValidationException::class);
    foreach ([
        [Producto::class, $datos['productoId']], [Almacen::class, $datos['almacenId']],
        [Sucursal::class, $datos['sucursalId']], [Proveedor::class, $datos['proveedorId']],
    ] as [$modelo, $id]) {
        $registro = $modelo::findOrFail($id);
        $registro->delete();
        expect(fn () => app(InventarioService::class)->registrarLote($datos))->toThrow(ValidationException::class);
        $registro->restore();
    }
    Producto::findOrFail($datos['productoId'])->update(['estado' => 'inactivo']);
    expect(fn () => app(InventarioService::class)->registrarLote($datos))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('inventarios', 0);
});

test('el modal valida pasos muestra el juguete y registra con precio calculado', function () {
    $datos = datosIngresoValidos();
    $form = Livewire::test(InventarioComponente::class)->call('abrirIngreso')->call('siguientePaso')
        ->assertHasErrors(['productoId', 'sucursalId', 'almacenId'])->assertSet('paso', 1)
        ->set('buscarProducto', 'tren')->assertSee('Tren educativo')
        ->call('seleccionarProducto', (int) $datos['productoId'])
        ->set('sucursalId', $datos['sucursalId'])->set('almacenId', $datos['almacenId'])
        ->call('siguientePaso')->assertHasNoErrors()->assertSet('paso', 2)
        ->set('cantidad', '10')->set('costoUnitario', '80')->set('margen', '20')
        ->assertSet('precioCalculado.precio_venta', '100.00')
        ->set('proveedorId', $datos['proveedorId'])->call('guardar')->assertHasNoErrors()
        ->assertSet('mostrarIngreso', false)->assertDispatched('alerta-producto');
    $lote = LoteInventario::query()->sole();
    $form->call('verDetalle', $lote->inventario_id)->assertSet('mostrarDetalle', true)
        ->assertSee($lote->codigo_lote)->assertSee('100.00');
    $this->assertDatabaseCount('movimientos_inventario', 1);
});

test('archivar exige stock cero y conserva lotes movimientos e historial al restaurar', function () {
    $datos = datosIngresoValidos();
    $service = app(InventarioService::class);
    $lote = $service->registrarLote($datos);
    $inventario = $lote->inventario;
    expect(fn () => $service->archivar($inventario->id, 'Cierre de ubicación temporal'))->toThrow(ValidationException::class);
    $this->assertNotSoftDeleted($inventario);
    $lote->update(['cantidad_disponible' => 0]);
    $service->archivar($inventario->id, 'Cierre de ubicación temporal');
    $this->assertSoftDeleted($inventario);
    expect(fn () => $service->registrarLote(array_replace($datos, ['claveIngreso' => (string) Str::uuid()])))
        ->toThrow(ValidationException::class);
    $service->restaurar($inventario->id);
    $this->assertNotSoftDeleted($inventario);
    expect($service->obtenerDetalle($inventario->id)['stock'])->toBe(0)
        ->and($inventario->fresh()->historial_estado)->toHaveCount(2)
        ->and(Gate::allows('delete', $inventario))->toBeFalse()
        ->and(Gate::allows('forceDelete', $inventario))->toBeFalse();
    $this->assertDatabaseCount('lotes_inventario', 1);
    $this->assertDatabaseCount('movimientos_inventario', 1);
});

test('filtra existencias sucursales búsquedas y archivados', function () {
    $uno = Inventario::factory()->create(['producto_id' => Producto::factory()->create(['nombre' => 'Tren azul'])->id]);
    LoteInventario::factory()->create(['inventario_id' => $uno->id, 'cantidad_disponible' => 15]);
    $dos = Inventario::factory()->create(['producto_id' => Producto::factory()->create(['nombre' => 'Robot rojo'])->id]);
    Livewire::test(InventarioComponente::class)->assertSee('Tren azul')->assertSee('Robot rojo')
        ->set('buscar', 'ROBOT')->assertSee('Robot rojo')->assertDontSee('Tren azul')
        ->call('limpiarFiltros')->set('filtroStock', 'agotado')->assertSee('Robot rojo')->assertDontSee('Tren azul')
        ->call('limpiarFiltros')->set('filtroSucursal', (string) $uno->almacen->sucursal_id)
        ->assertSee('Tren azul')->assertDontSee('Robot rojo')
        ->call('limpiarFiltros')->call('abrirBaja', $dos->id)->set('motivoBaja', 'Ubicación cerrada temporalmente')
        ->call('archivar')->assertHasNoErrors()->assertDontSee('Robot rojo')
        ->set('filtroEstado', 'archivados')->assertSee('Robot rojo')->assertDontSee('Tren azul');
});

test('los almacenes de una sucursal eliminada no se ofrecen para nuevos ingresos', function () {
    $almacen = Almacen::factory()->create();
    $almacen->sucursal->delete();
    expect(app(InventarioService::class)->opciones()['almacenes'])->toBeEmpty();
});

test('calcula margen bruto y redondea al centavo sin flotantes', function ($costo, $margen, $precio) {
    expect(app(PrecioLoteService::class)->calcular($costo, $margen)['precio_venta'])->toBe($precio);
})->with([['80', '20', '100.00'], ['0.01', '20', '0.02'], ['10', '33.33', '15.00'], ['10', '99.99', '100000.00']]);

test('rechaza precios calculados que exceden la precisión de la columna', function () {
    expect(fn () => app(PrecioLoteService::class)->calcular('99999999.99', '99.99'))->toThrow(ValidationException::class);
});

test('protege las rutas y operaciones del inventario contra clientes sin permiso', function () {
    $this->get(route('admin.inventario'))->assertOk();
    $cliente = User::factory()->create();
    $datos = datosIngresoValidos();
    $this->actingAs($cliente)->get(route('admin.inventario'))->assertForbidden();
    expect(fn () => app(InventarioService::class)->registrarLote($datos))
        ->toThrow(AuthorizationException::class);
    Livewire::actingAs($cliente)->test(InventarioComponente::class)->assertForbidden();
});

test('rechaza filtros manipulados sin enviar texto a columnas numéricas', function () {
    Inventario::factory()->create();
    Livewire::test(InventarioComponente::class)
        ->set('filtroSucursal', 'xx')->assertHasErrors('filtroSucursal')
        ->call('limpiarFiltros')->set('filtroAlmacen', '1 OR 1=1')->assertHasErrors('filtroAlmacen')
        ->call('limpiarFiltros')->set('orden', 'stock; DROP TABLE inventarios')->assertHasErrors('orden');
    $this->assertDatabaseCount('inventarios', 1);
});

test('ignora precios cantidades y autor impuestos fuera del contrato del formulario', function () {
    $datos = datosIngresoValidos([
        'precio_venta' => '1.00', 'cantidad_disponible' => '999', 'registrado_por' => '999',
        'estado' => 'inactivo', 'codigo_lote' => 'MANIPULADO',
    ]);
    $lote = app(InventarioService::class)->registrarLote($datos);
    expect($lote->precio_venta)->toBe('100.00');
    expect($lote->cantidad_disponible)->toBe(10);
    expect($lote->registrado_por)->toBe($this->admin->id);
    expect($lote->estado)->toBe('activo');
    expect($lote->codigo_lote)->toStartWith('MTL-');
});

test('actualizar lote modifica datos, recalcula precio de venta y registra movimiento de ajuste', function () {
    $service = app(InventarioService::class);
    $lote = $service->registrarLote(datosIngresoValidos());
    expect($lote->precio_venta)->toBe('100.00');

    $loteActualizado = $service->actualizarLote($lote->id, [
        'costoUnitario' => '90.00',
        'margen' => '25.00',
        'cantidadInicial' => 12,
        'cantidadDisponible' => 12,
        'proveedorId' => $lote->proveedor_id,
        'fechaIngreso' => today()->format('Y-m-d'),
        'fechaVencimiento' => null,
        'motivo' => 'Corrección de costo por factura rectificada',
    ]);

    expect($loteActualizado->costo_unitario)->toBe('90.00')
        ->and($loteActualizado->precio_venta)->toBe('120.00')
        ->and($loteActualizado->cantidad_disponible)->toBe(12);

    $this->assertDatabaseHas('movimientos_inventario', [
        'lote_id' => $lote->id,
        'tipo_movimiento' => 'ajuste',
        'motivo' => 'Corrección de costo por factura rectificada',
        'cantidad' => 2,
        'cantidad_anterior' => 10,
        'cantidad_nueva' => 12,
    ]);
});

test('descontar existencias sigue estrategia FIFO agotando lotes antiguos primero y registrando salidas', function () {
    $service = app(InventarioService::class);
    $datos = datosIngresoValidos(['cantidad' => '5']);
    $loteAntiguo = $service->registrarLote($datos);

    $otroProveedor = Proveedor::factory()->create();
    $loteNuevo = $service->registrarLote(array_replace($datos, [
        'proveedorId' => (string) $otroProveedor->id,
        'claveIngreso' => (string) Str::uuid(),
        'cantidad' => '8',
    ]));

    // Descontamos 7 unidades (debe consumir 5 del lote antiguo y 2 del nuevo)
    $afectados = $service->descontarStock($loteAntiguo->inventario_id, 7, 'Salida para vitrina de tienda');

    expect($afectados)->toHaveCount(2)
        ->and($afectados[0]['lote_id'])->toBe($loteAntiguo->id)
        ->and($afectados[0]['descontado'])->toBe(5)
        ->and($afectados[0]['restante_lote'])->toBe(0)
        ->and($afectados[1]['lote_id'])->toBe($loteNuevo->id)
        ->and($afectados[1]['descontado'])->toBe(2)
        ->and($afectados[1]['restante_lote'])->toBe(6);

    expect($loteAntiguo->fresh()->cantidad_disponible)->toBe(0)
        ->and($loteNuevo->fresh()->cantidad_disponible)->toBe(6);

    $this->assertDatabaseHas('movimientos_inventario', [
        'lote_id' => $loteAntiguo->id,
        'tipo_movimiento' => 'salida',
        'cantidad' => 5,
        'cantidad_anterior' => 5,
        'cantidad_nueva' => 0,
    ]);

    $this->assertDatabaseHas('movimientos_inventario', [
        'lote_id' => $loteNuevo->id,
        'tipo_movimiento' => 'salida',
        'cantidad' => 2,
        'cantidad_anterior' => 8,
        'cantidad_nueva' => 6,
    ]);
});

test('registrar lote con precio mayor ajusta precios anteriores para proteger capital de reposición', function () {
    $service = app(InventarioService::class);
    $datos = datosIngresoValidos(['costoUnitario' => '80.00', 'margen' => '20.00']); // Venta: 100.00
    $lote1 = $service->registrarLote($datos);
    expect($lote1->precio_venta)->toBe('100.00');

    // Comprobamos la verificación de lotes anteriores más baratos
    $verificacion = $service->verificarLotesAnteriores($lote1->inventario->producto_id, $lote1->inventario->almacen_id, '125.00');
    expect($verificacion['hay_lotes_anteriores_menores'])->toBeTrue()
        ->and($verificacion['total_unidades'])->toBe(10);

    // Registramos nuevo lote a mayor costo (Costo 100, margen 20 -> Venta 125.00) con ajuste activado
    $lote2 = $service->registrarLote(array_replace($datos, [
        'claveIngreso' => (string) Str::uuid(),
        'costoUnitario' => '100.00',
        'margen' => '20.00',
        'ajustarPreciosAnteriores' => true,
    ]));

    expect($lote2->precio_venta)->toBe('125.00');
    // El lote 1 ahora debe haber sido ajustado a 125.00
    expect($lote1->fresh()->precio_venta)->toBe('125.00');

    $this->assertDatabaseHas('movimientos_inventario', [
        'lote_id' => $lote1->id,
        'tipo_movimiento' => 'ajuste_precio',
    ]);
});

test('cálculo de stock mínimo con IO usa modelo heurístico sin ventas y probabilístico con ventas', function () {
    $calculo = app(CalculoStockMinimoService::class);
    $producto = Producto::factory()->create();

    // Sin ventas previas: modelo heurístico determinístico
    $resHeuristico = $calculo->calcular($producto->id, 100);
    expect($resHeuristico['metodo'])->toBe('heuristico_io')
        ->and($resHeuristico['stock_minimo'])->toBeGreaterThanOrEqual(15);

    // Con tiempo de entrega específico en proveedor
    $proveedor = Proveedor::factory()->create();
    ProductoProveedor::query()->create([
        'producto_id' => $producto->id,
        'proveedor_id' => $proveedor->id,
        'codigo_proveedor' => 'PRV-123',
        'costo_compra' => 50,
        'tiempo_entrega_dias' => 14,
    ]);
    $resLeadTime = $calculo->calcular($producto->id, 100, $proveedor->id);
    expect($resLeadTime['lead_time_dias'])->toBe(14);

    // Simulamos historial de ventas en 3 días distintos para probar el modelo probabilístico
    $venta1 = Venta::factory()->create(['fecha_venta' => now()->subDays(5)]);
    DetalleVenta::query()->create(['venta_id' => $venta1->id, 'producto_id' => $producto->id, 'cantidad' => 5, 'precio_unitario' => 10, 'subtotal' => 50]);
    $venta2 = Venta::factory()->create(['fecha_venta' => now()->subDays(3)]);
    DetalleVenta::query()->create(['venta_id' => $venta2->id, 'producto_id' => $producto->id, 'cantidad' => 10, 'precio_unitario' => 10, 'subtotal' => 100]);
    $venta3 = Venta::factory()->create(['fecha_venta' => now()->subDays(1)]);
    DetalleVenta::query()->create(['venta_id' => $venta3->id, 'producto_id' => $producto->id, 'cantidad' => 8, 'precio_unitario' => 10, 'subtotal' => 80]);

    $resProbabilistico = $calculo->calcular($producto->id, 100, $proveedor->id);
    expect($resProbabilistico['metodo'])->toBe('probabilistico')
        ->and($resProbabilistico['stock_minimo'])->toBeGreaterThan(0)
        ->and($resProbabilistico['demanda_diaria_promedio'])->toBeGreaterThan(0);
});

test('componente Livewire edita lote y descuenta existencias FIFO actualizando el modal', function () {
    $service = app(InventarioService::class);
    $lote = $service->registrarLote(datosIngresoValidos(['cantidad' => '20']));

    $test = Livewire::test(InventarioComponente::class)
        ->call('verDetalle', $lote->inventario_id)
        ->assertSet('mostrarDetalle', true)
        ->call('abrirEdicionLote', $lote->id)
        ->assertSet('mostrarEdicionLote', true)
        ->set('costoUnitarioEdicion', '85.00')
        ->set('margenEdicion', '20.00')
        ->set('cantidadInicialEdicion', '20')
        ->set('cantidadDisponibleEdicion', '20')
        ->set('fechaIngresoEdicion', today()->format('Y-m-d'))
        ->set('motivoEdicion', 'Ajuste desde Livewire')
        ->call('guardarEdicionLote')
        ->assertSet('mostrarEdicionLote', false);

    expect($lote->fresh()->costo_unitario)->toBe('85.00');

    // Probar descuento FIFO
    $test->call('abrirDescuento', $lote->inventario_id)
        ->assertSet('mostrarDescuento', true)
        ->set('cantidadDescuento', '5')
        ->set('motivoDescuento', 'Exhibición vitrina')
        ->call('guardarDescuento')
        ->assertSet('mostrarDescuento', false);

    expect($lote->fresh()->cantidad_disponible)->toBe(15);
});

test('componente Livewire valida edición de lote con mensajes en español y reglas estrictas', function () {
    $service = app(InventarioService::class);
    $lote = $service->registrarLote(datosIngresoValidos(['cantidad' => '20']));

    Livewire::test(InventarioComponente::class)
        ->call('abrirEdicionLote', $lote->id)
        ->set('cantidadInicialEdicion', '')
        ->call('guardarEdicionLote')
        ->assertHasErrors(['cantidadInicialEdicion' => 'required'])
        ->assertSee('La cantidad inicial es obligatoria.')
        ->set('cantidadInicialEdicion', '10')
        ->set('cantidadDisponibleEdicion', '15')
        ->call('guardarEdicionLote')
        ->assertHasErrors(['cantidadDisponibleEdicion' => 'lte'])
        ->assertSee('La cantidad disponible no puede ser mayor que la cantidad inicial.')
        ->set('cantidadDisponibleEdicion', '10')
        ->set('motivoEdicion', '')
        ->call('guardarEdicionLote')
        ->assertHasErrors(['motivoEdicion' => 'required'])
        ->assertSee('El motivo de la corrección es obligatorio.')
        ->set('motivoEdicion', 'abc')
        ->call('guardarEdicionLote')
        ->assertHasErrors(['motivoEdicion' => 'min'])
        ->assertSee('Explica el motivo de la corrección con al menos 5 caracteres.');
});

test('componente Livewire autocompleta existencias actuales descontando salidas al editar lote', function () {
    $service = app(InventarioService::class);
    $lote = $service->registrarLote(datosIngresoValidos(['cantidad' => '50']));

    // Descontamos 10 unidades por exhibición
    $service->descontarStock($lote->inventario_id, 10, 'Salida para exhibición');
    expect($lote->fresh()->cantidad_disponible)->toBe(40);

    // Al abrir la edición, cantidadDisponibleEdicion debe autocompletarse con 40 y registrar 10 salidas
    $componente = Livewire::test(InventarioComponente::class)
        ->call('abrirEdicionLote', $lote->id)
        ->assertSet('unidadesSalidasEdicion', 10)
        ->assertSet('cantidadInicialEdicion', '50')
        ->assertSet('cantidadDisponibleEdicion', '40');

    // Al modificar la cantidad inicial a 60, debe recalcular automáticamente a 50 (60 - 10)
    $componente->set('cantidadInicialEdicion', '60')
        ->assertSet('cantidadDisponibleEdicion', '50');

    // Si el usuario altera el valor manualmente y pulsa autocompletar, vuelve a 50
    $componente->set('cantidadDisponibleEdicion', '20')
        ->call('recalcularDisponibleEdicion')
        ->assertSet('cantidadDisponibleEdicion', '50');
});
