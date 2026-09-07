<?php

namespace App\Policies;

use App\Models\Proveedor;
use App\Models\User;

class ProveedorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Administrador') || $user->can('gestionar usuarios');
    }

    public function view(User $user, Proveedor $registro): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Proveedor $registro): bool
    {
        return $this->viewAny($user) && ! $registro->trashed();
    }

    public function delete(User $user, Proveedor $registro): bool
    {
        return $this->update($user, $registro);
    }

    public function restore(User $user, Proveedor $registro): bool
    {
        return $this->viewAny($user) && $registro->trashed();
    }

    public function forceDelete(User $user, Proveedor $registro): bool
    {
        return false;
    }
}
