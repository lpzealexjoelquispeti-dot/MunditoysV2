<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UsuarioAccesoRepository
{
    /** @param array<string, string> $filtros */
    public function paginar(array $filtros): LengthAwarePaginator
    {
        $query = User::query()->with(['roles', 'sucursal']);
        $query->whereHas('roles', function (Builder $roles) use ($filtros): void {
            if ($filtros['grupo'] === 'clientes') {
                $roles->where('name', 'Cliente');
            } else {
                $roles->where('name', '!=', 'Cliente');
            }
        });
        if ($filtros['buscar'] !== '') {
            $buscar = '%'.mb_strtolower($filtros['buscar']).'%';
            $query->where(function (Builder $busqueda) use ($buscar): void {
                foreach (['name', 'apellido_paterno', 'apellido_materno', 'email', 'telefono'] as $campo) {
                    $busqueda->orWhereRaw('LOWER('.$campo.') LIKE ?', [$buscar]);
                }
            });
        }
        if ($filtros['rol'] !== '') {
            $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $filtros['rol']));
        }
        match ($filtros['estado']) {
            'activo' => $query->where('estado', true)->where('invitacion_pendiente', false),
            'inactivo' => $query->where('estado', false)->where('invitacion_pendiente', false),
            'pendiente' => $query->where('invitacion_pendiente', true)->whereNull('invitacion_revocada_at'),
            'revocada' => $query->where('invitacion_pendiente', true)->whereNotNull('invitacion_revocada_at'),
            default => null,
        };
        [$campo, $direccion] = match ($filtros['orden']) {
            'antiguos' => ['created_at', 'asc'],
            'nombre' => ['name', 'asc'],
            default => ['created_at', 'desc'],
        };

        return $query->orderBy($campo, $direccion)->orderBy('id')->paginate(12);
    }

    public function buscar(int $id, bool $bloquear = false): User
    {
        return User::query()->with('roles')->when($bloquear, fn (Builder $query) => $query->lockForUpdate())->findOrFail($id);
    }

    public function buscarCuenta(int $id): ?User
    {
        return User::query()->with('roles')->find($id);
    }

    public function bloquearAdministracion(): void
    {
        Role::query()->where('name', 'Administrador')->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
    }

    public function administradoresActivos(): int
    {
        return User::role('Administrador')->where('estado', true)->where('invitacion_pendiente', false)->count();
    }

    public function correoExiste(string $correo): bool
    {
        return User::withTrashed()->whereRaw('LOWER(TRIM(email)) = ?', [$correo])->exists();
    }

    /** @param array<string, mixed> $datos */
    public function guardar(?User $usuario, array $datos, ?string $rol = null): User
    {
        $usuario ??= new User;
        $usuario->forceFill($datos)->save();
        if ($rol !== null) {
            $usuario->syncRoles([$rol]);
            $usuario->syncPermissions([]);
        }

        return $usuario->refresh()->load('roles');
    }

    public function eliminarSesiones(int $id): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $id)->delete();
        }
    }
}
