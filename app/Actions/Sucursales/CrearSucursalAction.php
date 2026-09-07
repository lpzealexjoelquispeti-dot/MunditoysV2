<?php

namespace App\Actions\Sucursales;

use App\Models\Sucursal;
use App\Repositories\SucursalRepository;

class CrearSucursalAction
{
    public function __construct(private SucursalRepository $repository) {}

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos): Sucursal
    {
        return $this->repository->crear($datos);
    }
}
