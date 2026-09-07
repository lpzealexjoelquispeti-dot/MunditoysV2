<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    /**
     * @param  array{name: string, apellido_paterno?: string|null, apellido_materno?: string|null, telefono?: string|null, email: string, password: string, tipo_usuario?: string, estado?: bool}  $atributos
     */
    public function crear(array $atributos): User
    {
        return User::query()->create($atributos);
    }

    public function buscarPorId(int $userId): User
    {
        return User::query()->findOrFail($userId);
    }

    /**
     * @param  array{name?: string, apellido_paterno?: string|null, apellido_materno?: string|null, telefono?: string|null, ci_nit?: string|null, foto?: string|null}  $atributos
     */
    public function actualizar(User $user, array $atributos): User
    {
        $user->update($atributos);

        return $user->refresh();
    }
}
