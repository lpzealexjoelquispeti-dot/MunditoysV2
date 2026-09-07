<?php

namespace App\Repositories;

use App\Models\Auditoria;
use Illuminate\Support\Collection;

class AuditoriaRepository
{
    /** @param array<string, mixed> $datos */
    public function registrar(array $datos): void
    {
        Auditoria::query()->create($datos);
    }

    /** @return Collection<int, Auditoria> */
    public function deUsuario(int $id): Collection
    {
        return Auditoria::query()->with('usuario')->where('modulo_afectado', 'usuarios')->where('registro_id', $id)->latest('id')->limit(20)->get();
    }
}
