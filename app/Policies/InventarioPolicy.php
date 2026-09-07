<?php

namespace App\Policies;

use App\Models\Inventario;
use App\Models\User;

class InventarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Administrador') || $user->can('gestionar inventario');
    }

    public function view(User $user, Inventario $inventario): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Inventario $inventario): bool
    {
        return $this->viewAny($user) && ! $inventario->trashed();
    }

    public function archive(User $user, Inventario $inventario): bool
    {
        return $this->viewAny($user) && ! $inventario->trashed();
    }

    public function restore(User $user, Inventario $inventario): bool
    {
        return $this->viewAny($user) && $inventario->trashed();
    }

    public function delete(User $user, Inventario $inventario): bool
    {
        return false;
    }

    public function forceDelete(User $user, Inventario $inventario): bool
    {
        return false;
    }
}
