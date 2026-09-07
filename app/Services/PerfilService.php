<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class PerfilService
{
    public function __construct(private UserRepository $userRepository) {}

    /** @return array<string, mixed> */
    public function obtener(int $userId): array
    {
        $usuario = $this->userRepository->buscarPorId($userId);
        Gate::authorize('view', $usuario);

        return $this->mapearUsuario($usuario);
    }

    /**
     * @param  array{name: string, apellido_paterno?: string|null, apellido_materno?: string|null, telefono?: string|null, ci_nit?: string|null}  $atributos
     */
    public function actualizar(int $userId, array $atributos, ?UploadedFile $foto = null): array
    {
        $usuario = $this->userRepository->buscarPorId($userId);
        Gate::authorize('update', $usuario);
        $atributos['telefono'] = preg_replace('/[\s-]/', '', $atributos['telefono'] ?? '');

        if ($foto !== null) {
            $atributos['foto'] = $foto->store('avatars', 'public');
        }

        return $this->mapearUsuario($this->userRepository->actualizar($usuario, $atributos));
    }

    /** @return array<string, mixed> */
    private function mapearUsuario(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'name' => $usuario->name,
            'apellido_paterno' => $usuario->apellido_paterno,
            'apellido_materno' => $usuario->apellido_materno,
            'telefono' => $usuario->telefono,
            'ci_nit' => $usuario->ci_nit,
            'email' => $usuario->email,
            'foto' => $usuario->foto,
        ];
    }
}
