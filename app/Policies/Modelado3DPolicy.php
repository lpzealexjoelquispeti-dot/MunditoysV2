<?php

namespace App\Policies;

use App\Models\Modelado3D;
use App\Models\User;

class Modelado3DPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('Administrador') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ver productos') || $user->hasRole('Administrador');
    }

    public function view(User $user, Modelado3D $modelado): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('crear productos') || $user->can('editar productos') || $user->hasRole('Administrador');
    }

    public function update(User $user, Modelado3D $modelado): bool
    {
        return $user->can('editar productos') || $user->hasRole('Administrador');
    }

    public function delete(User $user, Modelado3D $modelado): bool
    {
        return $user->can('eliminar productos') || $user->hasRole('Administrador');
    }

    public function restore(User $user, Modelado3D $modelado): bool
    {
        return $this->delete($user, $modelado);
    }

    public function forceDelete(User $user, Modelado3D $modelado): bool
    {
        return $this->delete($user, $modelado);
    }
}
