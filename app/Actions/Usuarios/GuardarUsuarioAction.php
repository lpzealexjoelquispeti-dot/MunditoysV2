<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Repositories\UsuarioAccesoRepository;

class GuardarUsuarioAction
{
    public function __construct(private UsuarioAccesoRepository $repository) {}

    /** @param array<string, mixed> $datos */
    public function ejecutar(?User $usuario, array $datos, ?string $rol = null): User
    {
        return $this->repository->guardar($usuario, $datos, $rol);
    }
}
