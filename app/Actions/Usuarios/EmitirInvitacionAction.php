<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Repositories\UsuarioAccesoRepository;

class EmitirInvitacionAction
{
    public function __construct(private UsuarioAccesoRepository $repository) {}

    public function ejecutar(User $usuario, string $token): User
    {
        return $this->repository->guardar($usuario, [
            'estado' => false, 'invitacion_pendiente' => true,
            'invitacion_token_hash' => hash('sha256', $token),
            'invitacion_expira_at' => now()->addHours(24),
            'invitacion_revocada_at' => null, 'invitacion_enviada_at' => null,
        ]);
    }
}
