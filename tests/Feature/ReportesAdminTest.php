<?php

use App\Livewire\Admin\Reportes;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\ConversacionIA;
use App\Models\DetalleVenta;
use App\Models\Direccion;
use App\Models\Entrega;
use App\Models\InteraccionUsuario;
use App\Models\Inventario;
use App\Models\LoteInventario;
use App\Models\MensajeIA;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Resena;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\ReporteService;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermisosSeeder::class, RolesSeeder::class]);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrador');
    $this->actingAs($this->admin);
});

test('usuario no autenticado o sin rol administrador no puede acceder a reportes', function () {
    auth()->logout();
    $this->get(route('admin.reportes'))->assertRedirect(route('login'));

    $cliente = User::factory()->create();
    $this->actingAs($cliente)->get(route('admin.reportes'))->assertForbidden();
});

test('administrador puede acceder a la pantalla principal de reportes y analitica', function () {
    $this->get(route('admin.reportes'))
        ->assertOk()
        ->assertSee('Analítica y Reportes Ejecutivos')
        ->assertSee('Ventas y Rentabilidad (FIFO)')
        ->assertSee('Descargar PDF')
        ->assertSee('Descargar Excel');
});

test('componente livewire navega reactivamente entre las cuatro secciones', function () {
    Livewire::test(Reportes::class)
        ->assertSet('seccion', 'ventas')
        ->assertSee('Ingresos Totales')
        ->call('cambiarSeccion', 'interacciones')
        ->assertSet('seccion', 'interacciones')
        ->assertSee('Visor 3D Interactivo')
        ->assertSee('Tópicos Recurrentes en el Asistente IA')
        ->call('cambiarSeccion', 'inventario')
        ->assertSet('seccion', 'inventario')
        ->assertSee('Valuación Costo FIFO')
        ->assertSee('Stock Total')
        ->call('cambiarSeccion', 'comercial')
        ->assertSet('seccion', 'comercial')
        ->assertSee('Sucursal Líder')
        ->assertSee('Éxito en Entregas');
});

test('filtro por preset temporal actualiza correctamente el rango de fechas', function () {
    Livewire::test(Reportes::class)
        ->set('periodoPreset', 'hoy')
        ->assertSet('fechaDesde', now()->toDateString())
        ->assertSet('fechaHasta', now()->toDateString())
        ->set('periodoPreset', 'ultimos_7_dias')
        ->assertSet('fechaHasta', now()->toDateString())
        ->set('periodoPreset', 'este_ano')
        ->assertSet('fechaDesde', now()->startOfYear()->toDateString())
        ->call('reiniciarFiltros')
        ->assertSet('periodoPreset', 'este_mes')
        ->assertSet('sucursalId', 'todos');
});

test('calcula con precision matematica la rentabilidad real fifo para ventas', function () {
    $categoria = Categoria::factory()->create(['nombre' => 'Juegos de Mesa']);
    $producto = Producto::factory()->create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Ajedrez de Madera Premium',
        'precio_venta' => 150.00,
    ]);

    $sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Central']);
    $almacen = Almacen::factory()->create(['sucursal_id' => $sucursal->id]);
    $inventario = Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
    ]);

    $lote = LoteInventario::factory()->create([
        'inventario_id' => $inventario->id,
        'costo_unitario' => 60.00,
        'precio_venta' => 150.00,
        'cantidad_disponible' => 10,
        'estado' => 'activo',
    ]);

    $vendedor = User::factory()->create(['name' => 'Carlos Vendedor']);
    $vendedor->assignRole('Vendedor');

    // Venta de 2 unidades a Bs. 150 c/u = Bs. 300
    // Costo del lote FIFO: 2 * Bs. 60 = Bs. 120
    // Ganancia real esperada: Bs. 180 (Margen: 60%)
    $venta = Venta::create([
        'numero_venta' => 'VTA-TEST-001',
        'cliente_id' => $this->admin->id,
        'usuario_vendedor_id' => $vendedor->id,
        'tipo_venta' => 'online',
        'tipo_entrega' => 'delivery',
        'estado' => 'completada',
        'subtotal' => 300.00,
        'descuento' => 0.00,
        'total' => 300.00,
        'fecha_venta' => now(),
    ]);

    DetalleVenta::create([
        'venta_id' => $venta->id,
        'producto_id' => $producto->id,
        'lote_id' => $lote->id,
        'cantidad' => 2,
        'precio_unitario' => 150.00,
        'subtotal' => 300.00,
    ]);

    Pago::create([
        'venta_id' => $venta->id,
        'metodo_pago' => 'qr',
        'referencia_pago' => 'REF-QR-001',
        'estado' => 'completado',
        'monto' => 300.00,
        'fecha_pago' => now(),
    ]);

    Entrega::create([
        'venta_id' => $venta->id,
        'sucursal_origen_id' => $sucursal->id,
        'estado' => 'entregado',
    ]);

    $service = app(ReporteService::class);
    $reporte = $service->obtenerReporteVentas([
        'fecha_desde' => now()->startOfMonth()->toDateString(),
        'fecha_hasta' => now()->endOfDay()->toDateString(),
    ]);

    expect($reporte['kpis']['total_ingresos'])->toBe(300.0)
        ->and($reporte['kpis']['total_costo'])->toBe(120.0)
        ->and($reporte['kpis']['ganancia_bruta'])->toBe(180.0)
        ->and($reporte['kpis']['margen_promedio'])->toBe(60.0)
        ->and($reporte['kpis']['total_pedidos'])->toBe(1)
        ->and($reporte['kpis']['ticket_promedio'])->toBe(300.0)
        ->and($reporte['kpis']['unidades_vendidas'])->toBe(2);
});

test('calcula metricas de interacciones de usuario modelos 3d y topics del asistente ia', function () {
    $producto = Producto::factory()->create(['nombre' => 'Robot Educativo 3D']);

    // Interacción ver_3d
    InteraccionUsuario::create([
        'user_id' => $this->admin->id,
        'producto_id' => $producto->id,
        'tipo_interaccion' => 'ver_3d',
        'datos_jsonb' => ['duracion_segundos' => 45],
    ]);

    // Interacción agregar_carrito
    InteraccionUsuario::create([
        'user_id' => $this->admin->id,
        'producto_id' => $producto->id,
        'tipo_interaccion' => 'agregar_carrito',
        'datos_jsonb' => ['cantidad' => 1],
    ]);

    // Conversación IA sobre robot y precio
    $conversacion = ConversacionIA::create([
        'user_id' => $this->admin->id,
        'titulo' => 'Consulta sobre robot 3D y precio',
        'metadata_jsonb' => ['contexto' => 'catalogo'],
    ]);

    MensajeIA::create([
        'conversacion_id' => $conversacion->id,
        'rol' => 'user',
        'mensaje' => '¿Cuál es el precio del robot educativo y si tienen lego para edad 6?',
    ]);

    $service = app(ReporteService::class);
    $reporte = $service->obtenerReporteInteracciones([
        'fecha_desde' => now()->subDays(5)->toDateString(),
        'fecha_hasta' => now()->endOfDay()->toDateString(),
    ]);

    expect($reporte['kpis']['total_interacciones'])->toBeGreaterThanOrEqual(2)
        ->and($reporte['kpis']['total_interacciones_3d'])->toBeGreaterThanOrEqual(1)
        ->and($reporte['kpis']['total_agregados_carrito'])->toBeGreaterThanOrEqual(1)
        ->and($reporte['topicos_ia'])->toHaveKey('Consultas de Precios y Ofertas');
});

test('calcula reporte de inventario valoracion fifo y alertas de stock bajo minimo', function () {
    $producto = Producto::factory()->create(['nombre' => 'Peluche Oso Gigante', 'precio_venta' => 80.00]);
    $sucursal = Sucursal::factory()->create();
    $almacen = Almacen::factory()->create(['sucursal_id' => $sucursal->id]);

    $inventario = Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'stock_minimo' => 15,
    ]);

    LoteInventario::factory()->create([
        'inventario_id' => $inventario->id,
        'costo_unitario' => 30.00,
        'precio_venta' => 80.00,
        'cantidad_disponible' => 5, // Bajo mínimo (5 <= 15)
        'estado' => 'activo',
    ]);

    $service = app(ReporteService::class);
    $reporte = $service->obtenerReporteInventario([
        'sucursal_id' => $sucursal->id,
    ]);

    expect($reporte['kpis']['valor_costo_total'])->toBe(150.0) // 5 * 30
        ->and($reporte['kpis']['valor_venta_total'])->toBe(400.0) // 5 * 80
        ->and($reporte['kpis']['margen_potencial'])->toBe(250.0)
        ->and($reporte['kpis']['productos_bajo_minimo'])->toBeGreaterThanOrEqual(1);
});

test('calcula rendimiento comercial de sucursales y satisfaccion del cliente', function () {
    $sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Norte', 'ciudad' => 'Cochabamba']);
    $producto = Producto::factory()->create();

    Resena::create([
        'producto_id' => $producto->id,
        'user_id' => $this->admin->id,
        'calificacion' => 5,
        'comentario' => 'Excelente calidad de juguetes y atención rápida.',
        'estado' => 'aprobada',
    ]);

    $service = app(ReporteService::class);
    $reporte = $service->obtenerReporteRendimientoComercial([
        'fecha_desde' => now()->startOfMonth()->toDateString(),
        'fecha_hasta' => now()->endOfDay()->toDateString(),
    ]);

    expect($reporte['kpis']['total_resenas'])->toBeGreaterThanOrEqual(1)
        ->and($reporte['kpis']['calificacion_promedio'])->toBe(5.0)
        ->and($reporte['distribucion_estrellas'][5])->toBeGreaterThanOrEqual(1);
});

test('descarga de reporte en pdf genera documento binario con dompdf', function () {
    $response = Livewire::test(Reportes::class)
        ->call('exportarPdf');

    $response->assertFileDownloaded();
});

test('descarga de reporte en excel responde con tipo de contenido enriquecido para hojas de calculo', function () {
    $response = Livewire::test(Reportes::class)
        ->call('exportarExcel');

    $response->assertFileDownloaded();
});

test('calcula reporte de logistica turnos de 90 min y zonas de la paz', function () {
    $chofer = User::factory()->create(['name' => 'Carlos Chofer']);
    $chofer->assignRole('Logistica');

    $cliente = User::factory()->create();
    $cliente->assignRole('Cliente');

    $sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Central', 'ciudad' => 'La Paz']);

    $direccion = Direccion::create([
        'user_id' => $cliente->id,
        'nombre_referencia' => 'Depto Calacoto',
        'direccion_completa' => 'Calle 15 de Calacoto #100',
        'zona' => 'Calacoto',
        'ciudad' => 'La Paz',
        'latitud' => -16.5400,
        'longitud' => -68.0800,
        'es_principal' => true,
    ]);

    $venta1 = Venta::factory()->create([
        'cliente_id' => $cliente->id,
        'total' => 250.00,
        'fecha_venta' => now(),
    ]);

    $venta2 = Venta::factory()->create([
        'cliente_id' => $cliente->id,
        'total' => 180.00,
        'fecha_venta' => now(),
    ]);

    // Entrega 1: Entregada en turno 12:00
    Entrega::create([
        'venta_id' => $venta1->id,
        'empleado_id' => $chofer->id,
        'direccion_id' => $direccion->id,
        'sucursal_origen_id' => $sucursal->id,
        'fecha_programada' => now()->toDateString(),
        'hora_inicio' => '12:00:00',
        'hora_fin' => '12:40:00',
        'estado' => 'entregado',
        'tipo_entrega' => 'domicilio',
    ]);

    // Entrega 2: Cancelada en turno 13:30
    Entrega::create([
        'venta_id' => $venta2->id,
        'empleado_id' => $chofer->id,
        'direccion_id' => $direccion->id,
        'sucursal_origen_id' => $sucursal->id,
        'fecha_programada' => now()->toDateString(),
        'hora_inicio' => '13:30:00',
        'hora_fin' => '14:10:00',
        'estado' => 'cancelado',
        'motivo_cancelacion' => 'Cliente ausente en domicilio',
        'tipo_entrega' => 'domicilio',
    ]);

    $service = app(ReporteService::class);
    $reporte = $service->obtenerReporteLogistica([
        'fecha_desde' => now()->startOfMonth()->toDateString(),
        'fecha_hasta' => now()->endOfDay()->toDateString(),
    ]);

    expect($reporte['kpis']['total_despachos'])->toBeGreaterThanOrEqual(2)
        ->and($reporte['kpis']['total_entregados'])->toBeGreaterThanOrEqual(1)
        ->and($reporte['kpis']['total_cancelados'])->toBeGreaterThanOrEqual(1)
        ->and($reporte['kpis']['monto_total_despachado'])->toBeGreaterThanOrEqual(250.00)
        ->and($reporte['despachos_por_turno']['12:00']['conteo'])->toBeGreaterThanOrEqual(1)
        ->and($reporte['despachos_por_turno']['13:30']['conteo'])->toBeGreaterThanOrEqual(1)
        ->and($reporte['motivos_cancelacion'])->toHaveKey('Cliente ausente en domicilio');
});
