<?php

use App\Actions\Ventas\GenerarFacturaPdfAction;
use App\Http\Controllers\FotogrametriaController;
use App\Livewire\Admin\Almacenes;
use App\Livewire\Admin\Categorias;
use App\Livewire\Admin\Home;
use App\Livewire\Admin\Inventario;
use App\Livewire\Admin\Logistica;
use App\Livewire\Admin\Marcas;
use App\Livewire\Admin\MisReportes;
use App\Livewire\Admin\Modelados3D;
use App\Livewire\Admin\Productos;
use App\Livewire\Admin\Proveedores;
use App\Livewire\Admin\RecojoSucursal;
use App\Livewire\Admin\Reportes;
use App\Livewire\Admin\Sucursales;
use App\Livewire\Admin\TarifasEnvio;
use App\Livewire\Admin\Usuarios;
use App\Livewire\Admin\Ventas;
use App\Livewire\Auth\ActivarCuenta;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Cliente\Carrito;
use App\Livewire\Cliente\Catalogo;
use App\Livewire\Cliente\Checkout;
use App\Livewire\Cliente\CompraExitosa;
use App\Livewire\Cliente\DetalleProducto;
use App\Livewire\Cliente\MisDirecciones;
use App\Livewire\Cliente\Profile;
use App\Livewire\Cliente\SeguimientoRegistro;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::get('/', function () {
    $productosRecientes = Producto::query()
        ->where('estado', 'activo')
        ->with(['categoria', 'marca', 'imagenes', 'modelos3D'])
        ->latest('id')
        ->take(8)
        ->get();

    $productoEscaneado = $productosRecientes->first(
        fn (Producto $producto): bool => $producto->modelos3D->whereNotNull('modelo_glb_path')->isNotEmpty()
    );
    $modeloEscaneado = $productoEscaneado?->modelos3D->whereNotNull('modelo_glb_path')->first();

    $categorias = Categoria::query()
        ->where('estado', true)
        ->withCount(['productos' => fn ($q) => $q->where('estado', 'activo')])
        ->orderBy('nombre')
        ->take(6)
        ->get();

    return view('welcome', compact('productosRecientes', 'productoEscaneado', 'modeloEscaneado', 'categorias'));
})->name('welcome');

Route::middleware('guest')->group(function (): void {
    Route::get('/ingresar', Login::class)->name('login');
    Route::get('/registro', Register::class)->name('register');
});

Route::post('/salir', function (): RedirectResponse {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return to_route('welcome');
})->middleware('auth')->name('logout');

Route::get('/mi-perfil', Profile::class)->middleware('auth')->name('perfil');
Route::get('/mis-direcciones', fn () => to_route('tienda.direcciones'))->middleware('auth')->name('cliente.direcciones');

Route::get('/activar-cuenta/{usuario}/{token}', ActivarCuenta::class)
    ->whereNumber('usuario')->where('token', '[a-f0-9]{64}')
    ->middleware('throttle:30,1')->name('invitacion.activar');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:acceder panel'])->group(function (): void {
    Route::get('/', Home::class)->name('dashboard');
    Route::get('/productos', Productos::class)->middleware('can:ver productos')->name('productos');
    Route::get('/modelos-3d', Modelados3D::class)->middleware('can:ver productos')->name('modelos-3d');
    Route::prefix('fotogrametria/{modelado}')->name('fotogrametria.')->middleware('can:ver productos')->whereNumber('modelado')->group(function (): void {
        Route::get('/fotos/{foto}', [FotogrametriaController::class, 'foto'])->whereNumber('foto')->name('foto');
        Route::get('/informe', [FotogrametriaController::class, 'exportar'])->name('exportar');
        Route::get('/dataset', [FotogrametriaController::class, 'dataset'])->name('dataset');
    });
    Route::get('/inventario', Inventario::class)->middleware('can:gestionar inventario')->name('inventario');
    Route::get('/ventas', Ventas::class)->middleware('can:crear ventas')->name('ventas');
    Route::get('/recojo-sucursal', RecojoSucursal::class)->middleware('can:gestionar recojos')->name('recojo-sucursal');
    Route::get('/mis-reportes', MisReportes::class)->middleware('can:crear ventas')->name('mis-reportes');
    Route::get('/logistica', Logistica::class)->middleware('can:acceder logistica')->name('logistica');
    Route::middleware('can:administrar accesos')->group(function (): void {
        Route::get('/tarifas-envio', TarifasEnvio::class)->name('tarifas-envio');
        Route::get('/usuarios', Usuarios::class)->name('usuarios');
        Route::get('/categorias', Categorias::class)->name('categorias');
        Route::get('/marcas', Marcas::class)->name('marcas');
        Route::get('/sucursales', Sucursales::class)->name('sucursales');
        Route::get('/almacenes', Almacenes::class)->name('almacenes');
        Route::get('/proveedores', Proveedores::class)->name('proveedores');
        Route::get('/reportes', Reportes::class)->name('reportes');
    });
});

Route::prefix('tienda')->name('tienda.')->group(function (): void {
    Route::get('/catalogo', Catalogo::class)->name('catalogo');
    Route::get('/producto/{codigo}', DetalleProducto::class)->name('producto.detalle');
    Route::get('/carrito', Carrito::class)->name('carrito');
    Route::get('/checkout', Checkout::class)->name('checkout');
    Route::get('/compra-exitosa/{numeroVenta}', CompraExitosa::class)->name('compra-exitosa');
    Route::get('/seguimiento', SeguimientoRegistro::class)->name('seguimiento');
    Route::get('/direcciones', MisDirecciones::class)->middleware('auth')->name('direcciones');
    Route::get('/factura/{numeroVenta}/descargar', function (string $numeroVenta, GenerarFacturaPdfAction $action): StreamedResponse {
        $venta = Venta::query()->where('numero_venta', $numeroVenta)->firstOrFail();
        $pdf = $action->execute($venta);

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            "Factura_{$venta->numero_venta}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    })->name('factura.descargar');
});
