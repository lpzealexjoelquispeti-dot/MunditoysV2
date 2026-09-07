<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Repositories\UsuarioAccesoRepository;

class ActivarCuentaAction
{
    public function __construct(private UsuarioAccesoRepository $repository) {}

    public function ejecutar(User $usuario, string $password): User
    {
        return $this->repository->guardar($usuario, [
            'password' => $password, 'estado' => true, 'email_verified_at' => now(),
            'invitacion_pendiente' => false, 'invitacion_token_hash' => null,
            'invitacion_expira_at' => null, 'invitacion_revocada_at' => null,
        ]);
    }
}
