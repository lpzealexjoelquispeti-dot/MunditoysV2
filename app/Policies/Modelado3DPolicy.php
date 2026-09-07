<?php

namespace App\Policies;

use App\Models\Modelado3D;
use App\Models\User;

class Modelado3DPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->estado && ! $user->invitacion_pendiente
            && ($user->can('ver productos') || $user->hasRole('Administrador'));
    }

    public function view(User $user, Modelado3D $modelado): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->estado && ! $user->invitacion_pendiente
            && ($user->can('crear productos') || $user->can('editar productos') || $user->hasRole('Administrador'));
    }

    public function update(User $user, Modelado3D $modelado): bool
    {
        return $user->estado && ! $user->invitacion_pendiente
            && ($user->can('editar productos') || $user->hasRole('Administrador'));
    }

    public function delete(User $user, Modelado3D $modelado): bool
    {
        return $user->estado && ! $user->invitacion_pendiente
            && ($user->can('eliminar productos') || $user->hasRole('Administrador'));
    }
}
