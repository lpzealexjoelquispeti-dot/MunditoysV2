<?php

namespace App\Services;

use App\Actions\Usuarios\EmitirInvitacionAction;
use App\Actions\Usuarios\GuardarUsuarioAction;
use App\Actions\Usuarios\RevocarSesionesAction;
use App\Models\User;
use App\Repositories\AuditoriaRepository;
use App\Repositories\UsuarioAccesoRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UsuarioAccesoService
{
    public const ROLES = ['Administrador', 'Vendedor', 'Inventario', 'Logistica'];

    public function __construct(
        private UsuarioAccesoRepository $repository,
        private GuardarUsuarioAction $guardarAction,
        private EmitirInvitacionAction $emitirAction,
        private RevocarSesionesAction $sesionesAction,
        private InvitacionService $invitaciones,
        private AuditoriaService $auditoria,
        private AuditoriaRepository $auditoriaRepository,
        private AccesoPanelService $acceso,
    ) {}

    /** @param array<string, string> $filtros */
    public function listar(array $filtros): LengthAwarePaginator
    {
        $this->autorizar();
        $filtros = array_intersect_key($filtros, $this->reglasFiltros());
        Validator::make($filtros, $this->reglasFiltros())->validate();

        return $this->repository->paginar($filtros)->through(fn (User $usuario) => $this->mapear($usuario));
    }

    /** @return array<string, array<int, mixed>> */
    public function reglasFiltros(): array
    {
        return [
            'grupo' => ['required', Rule::in(['empleados', 'clientes'])],
            'buscar' => ['present', 'string', 'max:100'],
            'rol' => ['present', Rule::in(['', ...self::ROLES, 'Cliente'])],
            'estado' => ['required', Rule::in(['todos', 'activo', 'inactivo', 'pendiente', 'revocada'])],
            'orden' => ['required', Rule::in(['recientes', 'antiguos', 'nombre'])],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public function reglasFormulario(bool $edicion = false): array
    {
        $nombre = ['bail', 'required', 'string', 'min:2', 'max:100', "regex:/\A[\pL\pM]+(?:[ '\-][\pL\pM]+)*\z/u"];

        return [
            'name' => $nombre,
            'apellido_paterno' => ['bail', 'required_without:apellido_materno', 'nullable', 'string', 'min:2', 'max:100', "regex:/\A[\pL\pM]+(?:[ '\-][\pL\pM]+)*\z/u"],
            'apellido_materno' => ['bail', 'required_without:apellido_paterno', 'nullable', 'string', 'min:2', 'max:100', "regex:/\A[\pL\pM]+(?:[ '\-][\pL\pM]+)*\z/u"],
            'telefono' => ['bail', 'required', 'string', 'regex:/\A(?:\+591[ -]?)?[67]\d{7}\z/'],
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:254'],
            'rolUsuario' => ['required', Rule::in($edicion ? [...self::ROLES, 'Cliente'] : self::ROLES)],
        ];
    }

    /** @return array<string, string> */
    public function mensajes(): array
    {
        return [
            'apellido_paterno.required_without' => 'Escribe al menos uno de los dos apellidos.',
            'apellido_materno.required_without' => 'Escribe al menos uno de los dos apellidos.',
            'name.regex' => 'El nombre admite letras, espacios, apóstrofes y guiones entre palabras.',
            'apellido_paterno.regex' => 'Escribe un apellido válido, sin números ni símbolos extraños.',
            'apellido_materno.regex' => 'Escribe un apellido válido, sin números ni símbolos extraños.',
            'telefono.regex' => 'El celular debe tener 8 dígitos y comenzar con 6 o 7. El prefijo +591 es opcional.',
            'rolUsuario.in' => 'Selecciona uno de los roles permitidos.',
        ];
    }

    /** @return array<string, mixed> */
    public function obtener(int $id): array
    {
        $this->autorizar();

        return $this->mapear($this->repository->buscar($id));
    }

    /** @return array<int, array<string, mixed>> */
    public function historial(int $id): array
    {
        $this->autorizar();
        $this->repository->buscar($id);

        return $this->auditoriaRepository->deUsuario($id)->map(fn ($evento) => [
            'accion' => $evento->accion, 'fecha' => $evento->created_at->format('d/m/Y H:i'),
            'responsable' => $evento->usuario?->name ?? 'Cuenta no disponible',
            'anterior' => $evento->datos_anterior_jsonb, 'nuevo' => $evento->datos_nuevo_jsonb,
        ])->all();
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos, string $passwordAdministrador): bool
    {
        $this->autorizar();
        $datos = $this->validarDatos($datos);
        try {
            [$usuario, $token, $actor] = DB::transaction(function () use ($datos, $passwordAdministrador): array {
                $this->repository->bloquearAdministracion();
                $actor = $this->confirmarAdministrador($passwordAdministrador);
                if ($this->repository->correoExiste($datos['email'])) {
                    throw ValidationException::withMessages(['email' => 'Este correo ya está registrado. Busca la cuenta existente.']);
                }
                $rol = $datos['rolUsuario'];
                unset($datos['rolUsuario']);
                $usuario = $this->guardarAction->ejecutar(null, $datos + [
                    'password' => Str::random(64), 'tipo_usuario' => 'empleado', 'estado' => false,
                    'invitacion_pendiente' => true, 'email_verified_at' => null,
                ], $rol);
                $token = bin2hex(random_bytes(32));
                $usuario = $this->emitirAction->ejecutar($usuario, $token);
                $this->auditoria->usuario($actor->id, $usuario, 'usuario_invitado');

                return [$usuario, $token, $actor->id];
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Este correo ya está registrado.']);
        }

        return $this->invitaciones->enviar($usuario, $token, $actor);
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(int $id, array $datos, string $passwordAdministrador): void
    {
        $this->autorizar();
        $datos = $this->validarDatos($datos, true);
        DB::transaction(function () use ($id, $datos, $passwordAdministrador): void {
            $this->repository->bloquearAdministracion();
            $actor = $this->confirmarAdministrador($passwordAdministrador);
            $usuario = $this->repository->buscar($id, true);
            if ($usuario->hasRole('Administrador') && ! $actor->is($usuario)) {
                throw ValidationException::withMessages([
                    'usuario' => 'Un administrador no puede editar los datos de otro administrador. Cada administrador gestiona su propia cuenta desde Mi perfil.',
                ]);
            }
            if ($datos['email'] !== mb_strtolower($usuario->email)) {
                throw ValidationException::withMessages(['email' => 'El correo de acceso no se cambia desde este formulario.']);
            }
            $rol = $datos['rolUsuario'];
            $rolAnterior = $usuario->roles->first()?->name;
            if (($usuario->hasRole('Cliente') && $rol !== 'Cliente') || (! $usuario->hasRole('Cliente') && $rol === 'Cliente')) {
                throw ValidationException::withMessages(['rolUsuario' => 'No se puede convertir un cliente en empleado ni un empleado en cliente desde este formulario.']);
            }
            if ($usuario->invitacion_pendiente && $rol !== $rolAnterior) {
                throw ValidationException::withMessages(['rolUsuario' => 'La cuenta debe activarse antes de cambiar su rol. Puedes revocar la invitación si fue asignada incorrectamente.']);
            }
            $this->protegerAdministrador($actor, $usuario, $rol !== 'Administrador', 'rolUsuario');
            $anterior = $this->auditoria->datosUsuario($usuario);
            unset($datos['rolUsuario'], $datos['email']);
            $usuario = $this->guardarAction->ejecutar($usuario, $datos, $rol !== $rolAnterior ? $rol : null);
            if ($rol !== $rolAnterior) {
                $usuario = $this->sesionesAction->ejecutar($usuario);
            }
            $this->auditoria->usuario($actor->id, $usuario, $rol !== $rolAnterior ? 'rol_actualizado' : 'datos_actualizados', $anterior);
        });
    }

    public function ejecutarAccion(int $id, string $accion, string $passwordAdministrador): bool
    {
        $this->autorizar();
        Validator::make(['accion' => $accion], ['accion' => ['required', Rule::in(['desactivar', 'reactivar', 'reenviar', 'revocar'])]])->validate();
        [$usuario, $token, $actor] = DB::transaction(function () use ($id, $accion, $passwordAdministrador): array {
            $this->repository->bloquearAdministracion();
            $actor = $this->confirmarAdministrador($passwordAdministrador);
            $usuario = $this->repository->buscar($id, true);
            $anterior = $this->auditoria->datosUsuario($usuario);
            $token = null;
            if (in_array($accion, ['reenviar', 'revocar'], true)) {
                if (! $usuario->invitacion_pendiente) {
                    throw ValidationException::withMessages(['accion' => 'Esta cuenta ya está activada y no tiene una invitación pendiente.']);
                }
                if ($accion === 'reenviar') {
                    $clave = 'reenviar-invitacion:'.$id;
                    if (RateLimiter::tooManyAttempts($clave, 1)) {
                        throw ValidationException::withMessages(['accion' => 'Espera un minuto antes de reenviar otra invitación.']);
                    }
                    RateLimiter::hit($clave, 60);
                    $token = bin2hex(random_bytes(32));
                    $usuario = $this->emitirAction->ejecutar($usuario, $token);
                } else {
                    $usuario = $this->guardarAction->ejecutar($usuario, [
                        'invitacion_token_hash' => null, 'invitacion_expira_at' => null,
                        'invitacion_revocada_at' => now(), 'estado' => false,
                    ]);
                }
            } else {
                if ($usuario->invitacion_pendiente) {
                    throw ValidationException::withMessages(['accion' => 'La cuenta debe activarse desde el correo. Reenvía la invitación si es necesario.']);
                }
                $activar = $accion === 'reactivar';
                if ($usuario->estado === $activar) {
                    throw ValidationException::withMessages(['accion' => 'El estado de la cuenta ya cambió. Actualiza el listado.']);
                }
                $this->protegerAdministrador($actor, $usuario, ! $activar, 'accion');
                $usuario = $this->guardarAction->ejecutar($usuario, ['estado' => $activar]);
                $usuario = $this->sesionesAction->ejecutar($usuario);
            }
            $this->auditoria->usuario($actor->id, $usuario, match ($accion) {
                'reenviar' => 'invitacion_renovada', 'revocar' => 'invitacion_revocada',
                'reactivar' => 'cuenta_reactivada', default => 'cuenta_desactivada',
            }, $anterior);

            return [$usuario, $token, $actor->id];
        });

        return $token === null || $this->invitaciones->enviar($usuario, $token, $actor);
    }

    private function autorizar(): void
    {
        Gate::authorize('administrar', User::class);
        $actual = $this->repository->buscarCuenta((int) Auth::id());
        abort_unless($actual && $this->acceso->puedeGestionarUsuarios($actual), 403);
    }

    private function confirmarAdministrador(string $password): User
    {
        $usuario = $this->repository->buscar((int) Auth::id(), true);
        abort_unless($this->acceso->puedeGestionarUsuarios($usuario), 403);
        $clave = 'confirmar-administrador:'.$usuario->id.'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($clave, 5)) {
            throw ValidationException::withMessages(['passwordAdministrador' => 'Demasiados intentos. Espera un minuto para confirmar tu contraseña.']);
        }
        if ($password === '' || strlen($password) > 1024 || ! Hash::check($password, $usuario->password)) {
            RateLimiter::hit($clave, 60);
            throw ValidationException::withMessages(['passwordAdministrador' => 'La contraseña del administrador no es correcta.']);
        }
        RateLimiter::clear($clave);

        return $usuario;
    }

    private function protegerAdministrador(User $actor, User $usuario, bool $pierdeAcceso, string $campo): void
    {
        if ($pierdeAcceso && $usuario->hasRole('Administrador')) {
            if ($usuario->estado && $this->repository->administradoresActivos() <= 1) {
                throw ValidationException::withMessages([$campo => 'Debe permanecer al menos un administrador activo.']);
            }
            if ($actor->is($usuario)) {
                throw ValidationException::withMessages([$campo => 'No puedes desactivar tu propia cuenta ni quitarte el rol de administrador.']);
            }
            if (! $actor->is($usuario)) {
                throw ValidationException::withMessages([$campo => 'Un administrador no puede dar de baja ni desactivar a otro administrador.']);
            }
        }
    }

    /** @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function validarDatos(array $datos, bool $edicion = false): array
    {
        $datos = array_intersect_key($datos, $this->reglasFormulario($edicion));
        foreach (['name', 'apellido_paterno', 'apellido_materno', 'telefono', 'email'] as $campo) {
            if (isset($datos[$campo]) && is_string($datos[$campo])) {
                $datos[$campo] = Str::squish($datos[$campo]);
            }
        }
        if (isset($datos['email']) && is_string($datos['email'])) {
            $datos['email'] = mb_strtolower($datos['email']);
        }
        $validado = Validator::make($datos, $this->reglasFormulario($edicion), $this->mensajes(), ['rolUsuario' => 'rol'])->validate();
        $validado['apellido_paterno'] = ($validado['apellido_paterno'] ?? '') ?: null;
        $validado['apellido_materno'] = ($validado['apellido_materno'] ?? '') ?: null;
        $validado['telefono'] = preg_replace('/\A\+591[ -]?/', '', $validado['telefono']);

        return $validado;
    }

    /** @return array<string, mixed> */
    private function mapear(User $usuario): array
    {
        $situacion = $usuario->estado ? 'Activo' : 'Inactivo';
        if ($usuario->invitacion_pendiente) {
            $situacion = $usuario->invitacion_revocada_at ? 'Invitación revocada'
                : ($usuario->invitacion_expira_at?->isPast() ? 'Invitación vencida' : 'Pendiente de activación');
        }

        return $usuario->only(['id', 'name', 'apellido_paterno', 'apellido_materno', 'email', 'telefono', 'estado', 'invitacion_pendiente'])
            + ['rolUsuario' => $usuario->roles->first()?->name ?? '', 'situacion' => $situacion,
                'enviada' => $usuario->invitacion_enviada_at?->format('d/m/Y H:i'),
                'expira' => $usuario->invitacion_expira_at?->format('d/m/Y H:i'),
                'revocada' => $usuario->invitacion_revocada_at !== null];
    }
}
