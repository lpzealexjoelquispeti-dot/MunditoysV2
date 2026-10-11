<?php

use App\Actions\Entregas\ConfirmarEntregaSucursalAction;
use App\Models\LoteInventario;
use App\Models\MovimientoInventario;
use App\Models\User;
use App\Models\Venta;
use App\Services\AutenticacionService;
use App\Services\RecojoSucursalService;
use App\Services\ReporteVendedorService;
use Database\Seeders\CatalogoDiversoSeeder;
use Database\Seeders\VendedorPresentacionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('el vendedor puede entrar y sus ventas y recojos aparecen en los reportes sin duplicarse', function (): void {
    Storage::fake('public');
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(16, 0));
    $this->seed(CatalogoDiversoSeeder::class);
    $cliente = User::factory()->create(['email' => VendedorPresentacionSeeder::EMAIL_CLIENTE, 'estado' => true]);
    $cliente->assignRole('Cliente');
    $claveCliente = $cliente->password;
    Mail::fake();
    Str::createRandomStringsUsing(fn (int $longitud): string => 'DemoClave2026Prueba!!');
    try {
        $this->seed(VendedorPresentacionSeeder::class);
    } finally {
        Str::createRandomStringsNormally();
    }
    $vendedor = User::query()->where('email', VendedorPresentacionSeeder::EMAIL_VENDEDOR)->firstOrFail();
    $autenticado = app(AutenticacionService::class)->iniciarSesion([
        'email' => $vendedor->email, 'password' => 'DemoClave2026Prueba!!',
    ]);
    expect($autenticado->id)->toBe($vendedor->id);
    expect($vendedor->can('acceder panel'))->toBeTrue();
    expect($vendedor->can('crear ventas'))->toBeTrue();
    expect($vendedor->can('gestionar recojos'))->toBeTrue();
    expect($vendedor->can('administrar accesos'))->toBeFalse();
    expect($vendedor->can('gestionar inventario'))->toBeFalse();
    expect($cliente->fresh()->password)->toBe($claveCliente);
    expect($cliente->fresh()->getRoleNames()->all())->toBe(['Cliente']);

    $reportes = app(ReporteVendedorService::class);
    $ventas = $reportes->obtenerReporteVentasFisicas($vendedor->id, $vendedor->sucursal_id, '2026-09-01', '2026-09-30');
    expect($ventas['total_ventas'])->toBe(20);
    expect($ventas['unidades_vendidas'])->toBe(46);
    $recojos = $reportes->obtenerReporteRecojosSucursal($vendedor->sucursal_id, $vendedor->id, '2026-09-01', '2026-09-30');
    expect($recojos['total_pedidos'])->toBe(6);
    expect($recojos['entregados_por_vendedor'])->toBe(4);
    expect($recojos['pedidos_pendientes'])->toBe(2);
    $this->assertDatabaseCount('detalle_ventas', 52);
    $this->assertDatabaseCount('pagos', 26);
    $this->assertDatabaseCount('notificaciones', 6);
    expect($cliente->ventas()->count())->toBe(26);
    expect(Venta::query()->get()->every(fn (Venta $venta): bool => $venta->cotizacion_envio['es_demostracion']
        && (float) $venta->total === (float) $venta->pago->monto
        && (float) $venta->subtotal === (float) $venta->detalles->sum('subtotal')))->toBeTrue();

    foreach (LoteInventario::query()->with('movimientos')->get() as $lote) {
        expect($lote->cantidad_disponible)->toBe((int) ($lote->movimientos->where('tipo_movimiento', 'entrada')->sum('cantidad')
            - $lote->movimientos->where('tipo_movimiento', 'salida')->sum('cantidad')));
    }
    $stocks = LoteInventario::query()->pluck('cantidad_disponible', 'id')->all();
    $claveVendedor = $vendedor->password;
    $this->seed(VendedorPresentacionSeeder::class);
    expect(Venta::query()->count())->toBe(26);
    expect(LoteInventario::query()->pluck('cantidad_disponible', 'id')->all())->toBe($stocks);
    expect($vendedor->fresh()->password)->toBe($claveVendedor);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

test('los pedidos pendientes permiten demostrar el QR y el recojo sin descontar stock dos veces', function (): void {
    Storage::fake('public');
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(16, 0));
    User::factory()->create(['email' => VendedorPresentacionSeeder::EMAIL_CLIENTE, 'estado' => true]);
    $this->seed(VendedorPresentacionSeeder::class);
    $vendedor = User::query()->where('email', VendedorPresentacionSeeder::EMAIL_VENDEDOR)->firstOrFail();
    $pedido = Venta::query()->where('numero_venta', 'DEMO-PRES-RECOJO-05')->firstOrFail();
    $codigo = 'MUNDITOYS-RECOJO:'.$pedido->numero_venta;
    $servicio = app(RecojoSucursalService::class);
    $resultado = $servicio->buscarPorCodigoQR($codigo, $vendedor->sucursal_id);
    expect($resultado['encontrado'])->toBeTrue();
    expect($resultado['sucursal_coincide'])->toBeTrue();
    expect($servicio->validarQrParaVenta($pedido, $codigo))->toBeTrue();
    expect($pedido->estado)->toBe('pagada');
    $stocks = LoteInventario::query()->pluck('cantidad_disponible', 'id')->all();

    $entregado = app(ConfirmarEntregaSucursalAction::class)->execute($pedido, $vendedor, true);

    expect($entregado->estado)->toBe('completada');
    expect($entregado->entrega->estado)->toBe('entregado');
    expect($entregado->entrega->empleado_id)->toBe($vendedor->id);
    expect(LoteInventario::query()->pluck('cantidad_disponible', 'id')->all())->toBe($stocks);
});

test('el seeder no crea cuentas ni ventas si falta el cliente solicitado', function (): void {
    expect(fn () => app(VendedorPresentacionSeeder::class)->run())->toThrow(ModelNotFoundException::class);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('ventas', 0);
});

test('el seeder revierte las ventas y el vendedor si no alcanza el stock de demostración', function (): void {
    Storage::fake('public');
    User::factory()->create(['email' => VendedorPresentacionSeeder::EMAIL_CLIENTE, 'estado' => true]);
    $this->seed(CatalogoDiversoSeeder::class);
    LoteInventario::query()->where('codigo_lote', 'DEMO-DIV-MT-DIV-005')->update(['cantidad_disponible' => 0]);
    $stocks = LoteInventario::query()->pluck('cantidad_disponible', 'id')->all();
    $movimientos = MovimientoInventario::query()->count();

    expect(fn () => app(VendedorPresentacionSeeder::class)->run())->toThrow(RuntimeException::class, 'Stock de demostración insuficiente');

    $this->assertDatabaseMissing('users', ['email' => VendedorPresentacionSeeder::EMAIL_VENDEDOR]);
    $this->assertDatabaseCount('ventas', 0);
    $this->assertDatabaseCount('pagos', 0);
    $this->assertDatabaseCount('entregas', 0);
    expect(MovimientoInventario::query()->count())->toBe($movimientos);
    expect(LoteInventario::query()->pluck('cantidad_disponible', 'id')->all())->toBe($stocks);
});

test('el seeder rechaza ejecutarse en producción', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    try {
        expect(fn () => app(VendedorPresentacionSeeder::class)->run())->toThrow(RuntimeException::class, 'solo puede ejecutarse en local o testing');
        $this->assertDatabaseCount('users', 0);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

test('el seeder conserva una cuenta existente que no corresponde al vendedor de demostración', function (): void {
    Storage::fake('public');
    User::factory()->create(['email' => VendedorPresentacionSeeder::EMAIL_CLIENTE, 'estado' => true]);
    $this->seed(CatalogoDiversoSeeder::class);
    $usuario = User::factory()->create(['email' => VendedorPresentacionSeeder::EMAIL_VENDEDOR, 'estado' => true]);
    $usuario->assignRole('Administrador');
    $clave = $usuario->password;

    expect(fn () => app(VendedorPresentacionSeeder::class)->run())->toThrow(RuntimeException::class, 'no coincide con el vendedor esperado');

    expect($usuario->fresh()->password)->toBe($clave);
    expect($usuario->fresh()->getRoleNames()->all())->toBe(['Administrador']);
    $this->assertDatabaseCount('ventas', 0);
});
