<?php

use App\Models\DetalleVenta;
use App\Models\Entrega;
use App\Models\LoteInventario;
use App\Models\MovimientoInventario;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\Venta;
use Database\Seeders\HistorialJuguetesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('el historial crea juguetes variados y conserva datos al repetirlo', function (): void {
    Storage::fake('public');
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(16, 0));
    $this->seed(HistorialJuguetesSeeder::class);
    $producto = Producto::query()->where('codigo_producto', 'MT-DIV-001')->firstOrFail();
    $producto->update(['precio_venta' => 299]);
    $imagen = ProductoImagen::factory()->create(['producto_id' => $producto->id]);
    $stocks = LoteInventario::query()->pluck('cantidad_disponible', 'id')->all();
    $movimientos = MovimientoInventario::query()->count();

    $this->seed(HistorialJuguetesSeeder::class);

    $this->assertDatabaseCount('productos', 26);
    $this->assertDatabaseCount('ventas', 422);
    $this->assertDatabaseCount('detalle_ventas', 844);
    $this->assertDatabaseCount('pagos', 422);
    $this->assertDatabaseCount('entregas', 318);
    $this->assertDatabaseCount('horarios_empleados', 6);
    $this->assertDatabaseCount('producto_proveedor', 26);
    $this->assertDatabaseCount('movimientos_inventario', $movimientos);
    $this->assertModelExists($imagen);
    expect($producto->fresh()->precio_venta)->toBe('299.00');
    expect(LoteInventario::query()->pluck('cantidad_disponible', 'id')->all())->toBe($stocks);
    expect(Producto::query()->distinct()->count('categoria_id'))->toBe(9);
    expect(Entrega::query()->distinct()->pluck('estado')->sort()->values()->all())->toBe(['cancelado', 'en_camino', 'entregado', 'pendiente']);
    expect(Venta::query()->oldest('fecha_venta')->first()->fecha_venta->diffInWeeks(now()))->toBeGreaterThanOrEqual(51);
    expect(Venta::query()->get()->every(fn (Venta $v): bool => $v->cotizacion_envio['es_demostracion'] === true))->toBeTrue();
});

test('las cancelaciones devuelven stock y los saldos coinciden con los movimientos', function (): void {
    Storage::fake('public');
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(16, 0));
    $this->seed(HistorialJuguetesSeeder::class);

    foreach (LoteInventario::query()->with('movimientos')->get() as $lote) {
        $salidas = $lote->movimientos->where('tipo_movimiento', 'salida')->sum('cantidad');
        $entradas = $lote->movimientos->where('tipo_movimiento', 'entrada')->sum('cantidad');
        expect((int) $lote->cantidad_disponible)->toBe((int) ($entradas - $salidas))->toBeGreaterThanOrEqual(0);
    }
    $canceladas = Venta::query()->where('estado', 'cancelada')->with('pago', 'entrega')->get();
    expect($canceladas)->not->toBeEmpty();
    foreach ($canceladas as $venta) {
        expect($venta->pago->estado)->toBe('reembolsado');
        expect($venta->entrega->motivo_cancelacion)->toContain('stock devuelto');
        expect(MovimientoInventario::query()->where('referencia_id', $venta->id)->where('referencia_tipo', 'venta')->where('tipo_movimiento', 'entrada')->count())->toBe(2);
    }
    expect(DetalleVenta::query()->whereNull('lote_id')->count())->toBe(0);
    expect(Pago::query()->whereNotNull('stripe_payment_id')->count())->toBe(0);
});
