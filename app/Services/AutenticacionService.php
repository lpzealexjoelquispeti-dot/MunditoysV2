<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AutenticacionService
{
    public function __construct(private UserRepository $userRepository) {}

    /**
     * @param  array{name: string, apellido_paterno: string|null, apellido_materno: string|null, telefono: string, email: string, password: string}  $atributos
     */
    public function registrar(array $atributos): User
    {
        try {
            $usuario = $this->userRepository->crear([
                'name' => $atributos['name'],
                'apellido_paterno' => $atributos['apellido_paterno'],
                'apellido_materno' => $atributos['apellido_materno'],
                'telefono' => preg_replace('/[\s-]/', '', $atributos['telefono']),
                'email' => mb_strtolower(trim($atributos['email'])),
                'password' => $atributos['password'],
                'tipo_usuario' => 'cliente',
                'estado' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Este correo electrónico ya está registrado.']);
        }

        $usuario->assignRole('Cliente');
        Auth::login($usuario);
        session()->regenerate();
        session()->put('cuenta_version', (int) $usuario->sesion_version);

        return $usuario;
    }

    /**
     * @param  array{email: string, password: string, remember?: bool}  $credenciales
     */
    public function iniciarSesion(array $credenciales): User
    {
        $email = strtolower($credenciales['email']);
        $limiterKey = 'login:'.$email.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Inténtalo de nuevo en un minuto.',
            ]);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $credenciales['password'], 'estado' => true, 'invitacion_pendiente' => false], $credenciales['remember'] ?? false)) {
            RateLimiter::hit($limiterKey, 60);

            throw ValidationException::withMessages([
                'email' => 'No encontramos una cuenta activa con esos datos.',
            ]);
        }

        RateLimiter::clear($limiterKey);
        session()->regenerate();

        /** @var User $usuario */
        $usuario = Auth::user();
        session()->put('cuenta_version', (int) $usuario->sesion_version);

        return $usuario;
    }
}
