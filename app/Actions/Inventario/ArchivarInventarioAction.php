<?php

namespace App\Actions\Inventario;

use App\Models\Inventario;
use App\Repositories\InventarioRepository;

class ArchivarInventarioAction
{
    public function __construct(private InventarioRepository $repository) {}

    /** @param array<int, array<string, mixed>> $historial */
    public function ejecutar(Inventario $inventario, array $historial): void
    {
        $this->repository->archivar($inventario, $historial);
    }
}
