<?php

namespace App\Actions\Almacenes;

use App\Models\Almacen;
use App\Repositories\AlmacenRepository;

class CrearAlmacenAction
{
    public function __construct(private AlmacenRepository $repository) {}

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos): Almacen
    {
        return $this->repository->crear($datos);
    }
}
