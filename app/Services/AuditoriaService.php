<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\AuditoriaRepository;

class AuditoriaService
{
    public function __construct(private AuditoriaRepository $repository) {}

    /** @return array<string, mixed> */
    public function datosUsuario(User $usuario): array
    {
        return $usuario->only(['name', 'apellido_paterno', 'apellido_materno', 'email', 'telefono', 'estado', 'tipo_usuario', 'invitacion_pendiente'])
            + ['roles' => $usuario->roles->pluck('name')->all()];
    }

    /** @param array<string, mixed> $anterior */
    public function usuario(?int $actor, User $usuario, string $accion, array $anterior = []): void
    {
        $this->repository->registrar([
            'usuario_id' => $actor, 'modulo_afectado' => 'usuarios', 'accion' => $accion,
            'tabla_afectada' => 'users', 'registro_id' => $usuario->id,
            'datos_anterior_jsonb' => $anterior ?: null,
            'datos_nuevo_jsonb' => $this->datosUsuario($usuario),
            'ip' => request()->ip(), 'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
        ]);
    }
}
