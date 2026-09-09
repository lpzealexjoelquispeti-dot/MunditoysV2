<?php

namespace App\Policies;

use App\Models\User;

class ReportePolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('Administrador') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Administrador') || $user->can('administrar accesos');
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user);
    }
}

