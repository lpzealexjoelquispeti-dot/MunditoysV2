<?php

namespace App\Policies;

use App\Models\Almacen;
use App\Models\User;

class AlmacenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Administrador') || $user->can('gestionar usuarios');
    }

    public function view(User $user, Almacen $registro): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Almacen $registro): bool
    {
        return $this->viewAny($user) && ! $registro->trashed();
    }

    public function delete(User $user, Almacen $registro): bool
    {
        return $this->update($user, $registro);
    }

    public function restore(User $user, Almacen $registro): bool
    {
        return $this->viewAny($user) && $registro->trashed();
    }

    public function forceDelete(User $user, Almacen $registro): bool
    {
        return false;
    }
}
