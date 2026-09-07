<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Repositories\UsuarioAccesoRepository;
use Illuminate\Support\Str;

class RevocarSesionesAction
{
    public function __construct(private UsuarioAccesoRepository $repository) {}

    public function ejecutar(User $usuario): User
    {
        $usuario = $this->repository->guardar($usuario, [
            'sesion_version' => $usuario->sesion_version + 1, 'remember_token' => Str::random(60),
        ]);
        $this->repository->eliminarSesiones($usuario->id);

        return $usuario;
    }
}
