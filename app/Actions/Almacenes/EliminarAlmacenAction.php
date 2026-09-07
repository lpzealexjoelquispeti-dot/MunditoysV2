<?php

namespace App\Actions\Almacenes;

use App\Models\Almacen;
use App\Repositories\AlmacenRepository;

class EliminarAlmacenAction
{
    public function __construct(private AlmacenRepository $repository) {}

    public function ejecutar(Almacen $registro): bool
    {
        return $this->repository->eliminar($registro);
    }
}
