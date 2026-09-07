<?php

namespace App\Actions\Almacenes;

use App\Models\Almacen;
use App\Repositories\AlmacenRepository;

class RestaurarAlmacenAction
{
    public function __construct(private AlmacenRepository $repository) {}

    public function ejecutar(Almacen $registro): bool
    {
        return $this->repository->restaurar($registro);
    }
}
