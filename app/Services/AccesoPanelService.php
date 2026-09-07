<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UsuarioAccesoRepository;

class AccesoPanelService
{
    public function __construct(private UsuarioAccesoRepository $repository) {}

    public function cuentaActual(int $id): ?User
    {
        return $this->repository->buscarCuenta($id);
    }

    public function puedeEntrar(User $usuario): bool
    {
        return $usuario->estado && ! $usuario->invitacion_pendiente
            && $usuario->hasAnyRole(['Administrador', 'Vendedor', 'Inventario', 'Logistica']);
    }

    public function puedeGestionarUsuarios(User $usuario): bool
    {
        return $this->puedeEntrar($usuario) && $usuario->hasRole('Administrador') && $usuario->can('gestionar usuarios');
    }
}
