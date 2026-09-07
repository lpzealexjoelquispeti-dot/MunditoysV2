<?php

namespace App\Policies;

use App\Models\Sucursal;
use App\Models\User;

class SucursalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Administrador') || $user->can('gestionar usuarios');
    }

    public function view(User $user, Sucursal $registro): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Sucursal $registro): bool
    {
        return $this->viewAny($user) && ! $registro->trashed();
    }

    public function delete(User $user, Sucursal $registro): bool
    {
        return $this->update($user, $registro);
    }

    public function restore(User $user, Sucursal $registro): bool
    {
        return $this->viewAny($user) && $registro->trashed();
    }

    public function forceDelete(User $user, Sucursal $registro): bool
    {
        return false;
    }
}
