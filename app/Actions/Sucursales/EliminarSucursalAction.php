<?php

namespace App\Actions\Sucursales;

use App\Models\Sucursal;
use App\Repositories\SucursalRepository;

class EliminarSucursalAction
{
    public function __construct(private SucursalRepository $repository) {}

    public function ejecutar(Sucursal $registro): bool
    {
        return $this->repository->eliminar($registro);
    }
}
