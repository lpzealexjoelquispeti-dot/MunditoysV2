<?php

namespace App\Services;

use App\Actions\Usuarios\ActivarCuentaAction;
use App\Actions\Usuarios\GuardarUsuarioAction;
use App\Mail\InvitacionEmpleado;
use App\Models\User;
use App\Repositories\UsuarioAccesoRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class InvitacionService
{
    public function __construct(
        private UsuarioAccesoRepository $repository,
        private ActivarCuentaAction $activarAction,
        private GuardarUsuarioAction $guardarAction,
        private AuditoriaService $auditoria,
    ) {}

    public function enviar(User $usuario, string $token, int $actor): bool
    {
        $enviado = false;
        if (config('mail.default') === 'smtp' || (app()->environment('testing') && config('mail.default') === 'array')) {
            $url = rtrim(config('app.url'), '/').route('invitacion.activar', ['usuario' => $usuario->id, 'token' => $token], false);
            try {
                Mail::to($usuario->email)->send(new InvitacionEmpleado(
                    $usuario->name, $usuario->email, $usuario->roles->first()->name, $url,
                ));
                $enviado = true;
            } catch (TransportExceptionInterface) {
                $enviado = false;
            }
        }

        DB::transaction(function () use ($usuario, $token, $actor, $enviado): void {
            $actual = $this->repository->buscar($usuario->id, true);
            if (hash_equals((string) $actual->invitacion_token_hash, hash('sha256', $token))) {
                if ($enviado) {
                    $actual = $this->guardarAction->ejecutar($actual, ['invitacion_enviada_at' => now()]);
                }
                $this->auditoria->usuario($actor, $actual, $enviado ? 'invitacion_enviada' : 'invitacion_envio_fallido');
            }
        });

        return $enviado;
    }

    /** @return array{name: string, email: string, rol: string}|null */
    public function consultar(int $id, string $token): ?array
    {
        $usuario = $this->repository->buscarCuenta($id);
        if (! $this->esValida($usuario, $token)) {
            return null;
        }

        return ['name' => $usuario->name, 'email' => $usuario->email, 'rol' => $usuario->roles->first()?->name ?? 'Empleado'];
    }

    /** @return array<string, array<int, mixed>> */
    public function reglas(): array
    {
        return [
            'password' => ['bail', 'required', 'string', 'min:12', 'max:72', function (string $attribute, mixed $value, \Closure $fail): void {
                if (strlen($value) > 72) {
                    $fail('La contraseña es demasiado larga al incluir caracteres especiales. Usa como máximo 72 bytes.');
                }
            }, Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'passwordConfirmation' => ['bail', 'required', 'string', 'same:password'],
        ];
    }

    /** @param array<string, mixed> $datos */
    public function activar(int $id, string $token, array $datos): void
    {
        abort_if(Auth::check(), 403);
        $clave = 'activar-cuenta:'.$id.'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($clave, 10)) {
            throw ValidationException::withMessages(['enlace' => 'Demasiados intentos. Espera un minuto antes de volver a intentarlo.']);
        }
        RateLimiter::hit($clave, 60);
        Validator::make($datos, $this->reglas(), [], ['passwordConfirmation' => 'confirmación de contraseña'])->validate();

        DB::transaction(function () use ($id, $token, $datos): void {
            $this->repository->bloquearAdministracion();
            $usuario = $this->repository->buscarCuenta($id);
            if ($usuario !== null) {
                $usuario = $this->repository->buscar($id, true);
            }
            if (! $this->esValida($usuario, $token)) {
                throw ValidationException::withMessages(['enlace' => 'La invitación no es válida, venció o ya fue utilizada. Solicita una nueva al administrador.']);
            }
            $anterior = $this->auditoria->datosUsuario($usuario);
            $usuario = $this->activarAction->ejecutar($usuario, $datos['password']);
            $this->auditoria->usuario($usuario->id, $usuario, 'cuenta_activada', $anterior);
        });
        RateLimiter::clear($clave);
    }

    private function esValida(?User $usuario, string $token): bool
    {
        return $usuario !== null && strlen($token) === 64 && ctype_xdigit($token)
            && $usuario->invitacion_pendiente && ! $usuario->estado
            && $usuario->invitacion_revocada_at === null
            && $usuario->invitacion_expira_at?->isFuture()
            && hash_equals((string) $usuario->invitacion_token_hash, hash('sha256', $token));
    }
}
