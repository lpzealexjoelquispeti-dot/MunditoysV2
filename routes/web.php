<?php

use App\Livewire\Admin\Almacenes;
use App\Livewire\Admin\Categorias;
use App\Livewire\Admin\Home;
use App\Livewire\Admin\Inventario;
use App\Livewire\Admin\Marcas;
use App\Livewire\Admin\Modelados3D;
use App\Livewire\Admin\Productos;
use App\Livewire\Admin\Proveedores;
use App\Livewire\Admin\Sucursales;
use App\Livewire\Admin\Usuarios;
use App\Livewire\Admin\Ventas;
use App\Livewire\Auth\ActivarCuenta;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Cliente\Carrito;
use App\Livewire\Cliente\Catalogo;
use App\Livewire\Cliente\Checkout;
use App\Livewire\Cliente\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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

Route::get('/activar-cuenta/{usuario}/{token}', ActivarCuenta::class)
    ->whereNumber('usuario')->where('token', '[a-f0-9]{64}')
    ->middleware('throttle:30,1')->name('invitacion.activar');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:acceder panel'])->group(function (): void {
    Route::get('/', Home::class)->name('dashboard');
    Route::get('/productos', Productos::class)->middleware('can:ver productos')->name('productos');
    Route::get('/modelos-3d', Modelados3D::class)->middleware('can:ver productos')->name('modelos-3d');
    Route::get('/inventario', Inventario::class)->middleware('can:gestionar inventario')->name('inventario');
    Route::get('/ventas', Ventas::class)->middleware('can:crear ventas')->name('ventas');
    Route::middleware('can:administrar accesos')->group(function (): void {
        Route::get('/usuarios', Usuarios::class)->name('usuarios');
        Route::get('/categorias', Categorias::class)->name('categorias');
        Route::get('/marcas', Marcas::class)->name('marcas');
        Route::get('/sucursales', Sucursales::class)->name('sucursales');
        Route::get('/almacenes', Almacenes::class)->name('almacenes');
        Route::get('/proveedores', Proveedores::class)->name('proveedores');
    });
});

Route::prefix('tienda')->name('tienda.')->group(function (): void {
    Route::get('/catalogo', Catalogo::class)->name('catalogo');
    Route::get('/carrito', Carrito::class)->name('carrito');
    Route::get('/checkout', Checkout::class)->name('checkout');
});
