<?php

namespace App\Actions\Almacenes;

use App\Models\Almacen;
use App\Repositories\AlmacenRepository;

class ActualizarAlmacenAction
{
    public function __construct(private AlmacenRepository $repository) {}

    /** @param array<string, mixed> $datos */
    public function ejecutar(Almacen $registro, array $datos): Almacen
    {
        return $this->repository->actualizar($registro, $datos);
    }
}
