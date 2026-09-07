<?php

namespace App\Policies;

use App\Models\Categoria;
use App\Models\User;

class CategoriaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Administrador') || $user->can('gestionar usuarios');
    }

    public function view(User $user, Categoria $registro): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Categoria $registro): bool
    {
        return $this->viewAny($user) && ! $registro->trashed();
    }

    public function delete(User $user, Categoria $registro): bool
    {
        return $this->update($user, $registro);
    }

    public function restore(User $user, Categoria $registro): bool
    {
        return $this->viewAny($user) && $registro->trashed();
    }

    public function forceDelete(User $user, Categoria $registro): bool
    {
        return false;
    }
}
