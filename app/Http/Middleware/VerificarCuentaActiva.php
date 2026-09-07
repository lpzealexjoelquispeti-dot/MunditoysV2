<?php

namespace App\Http\Middleware;

use App\Services\AccesoPanelService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerificarCuentaActiva
{
    public function __construct(private AccesoPanelService $service) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $actual = $this->service->cuentaActual((int) Auth::id());
            $version = (int) $request->session()->get('cuenta_version', 0);
            if (! $actual || ! $actual->estado || $actual->invitacion_pendiente || $actual->sesion_version !== $version) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('status', 'Tu sesión finalizó. Inicia sesión con una cuenta activa.');
            }
            Auth::setUser($actual);
        }
        $response = $next($request);
        $response->headers->set('Referrer-Policy', 'no-referrer');
        if ($request->is('activar-cuenta/*') || Auth::check()) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
