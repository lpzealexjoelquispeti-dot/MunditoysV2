<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AccesoPanelService;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Foundation\DevCommands;
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
        DevCommands::artisan('queue:work fotogrametria --queue=fotogrametria --timeout=330 --sleep=2 --memory=256', 'fotogrametria');
        DevCommands::artisan('queue:work fotogrametria_reconstruccion --queue=reconstruccion --timeout=3700 --sleep=3 --memory=512', 'reconstruccion-3d');

        // En Windows, sin TEMP/TMP el servidor de "artisan serve" no puede crear los archivos temporales de las subidas.
        ServeCommand::$passthroughVariables = array_values(array_unique([...ServeCommand::$passthroughVariables, 'TEMP', 'TMP']));

        Gate::define('acceder panel', fn (User $user): bool => $acceso->puedeEntrar($user));
        Gate::define('administrar accesos', fn (User $user): bool => $acceso->puedeGestionarUsuarios($user));
        Gate::define('acceder logistica', fn (User $user): bool => $acceso->puedeEntrar($user)
            && $user->hasRole('Logistica') && ! $user->hasRole('Administrador'));
        Gate::define('gestionar recojos', fn (User $user): bool => $acceso->puedeEntrar($user)
            && $user->hasRole('Vendedor') && ! $user->hasRole('Administrador') && $user->can('crear ventas'));
    }
}
