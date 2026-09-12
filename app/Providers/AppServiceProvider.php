<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AccesoPanelService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(AccesoPanelService $acceso): void
    {
        Gate::define('acceder panel', fn (User $user): bool => $acceso->puedeEntrar($user));
        Gate::define('administrar accesos', fn (User $user): bool => $acceso->puedeGestionarUsuarios($user));
        Gate::define('acceder logistica', fn (User $user): bool => $acceso->puedeEntrar($user)
            && $user->hasRole('Logistica') && ! $user->hasRole('Administrador'));
        Gate::define('gestionar recojos', fn (User $user): bool => $acceso->puedeEntrar($user)
            && $user->hasRole('Vendedor') && ! $user->hasRole('Administrador') && $user->can('crear ventas'));
    }
}
