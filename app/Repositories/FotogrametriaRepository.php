<?php

namespace App\Repositories;

use App\Models\Modelado3D;
use Illuminate\Database\Eloquent\Collection;

class FotogrametriaRepository
{
    public function obtener(int $id, bool $bloquear = false, bool $incluirArchivadas = false): Modelado3D
    {
        return Modelado3D::query()->where('tipo_generacion', 'fotogrametria_local')
            ->when($incluirArchivadas, fn ($query) => $query->withTrashed())
            ->when($bloquear, fn ($query) => $query->lockForUpdate())
            ->with(['producto', 'fotografias'])->findOrFail($id);
    }

    /** @return Collection<int, Modelado3D> */
    public function listar(): Collection
    {
        return Modelado3D::query()->where('tipo_generacion', 'fotogrametria_local')
            ->with('producto')->withCount('fotografias')->latest('id')->limit(30)->get();
    }

    /** @return Collection<int, Modelado3D> */
    public function listarArchivadas(): Collection
    {
        return Modelado3D::onlyTrashed()->where('tipo_generacion', 'fotogrametria_local')
            ->with('producto')->withCount('fotografias')->latest('deleted_at')->limit(30)->get();
    }
}
