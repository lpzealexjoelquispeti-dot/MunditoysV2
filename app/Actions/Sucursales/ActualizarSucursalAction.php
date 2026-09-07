<?php

namespace App\Actions\Sucursales;

use App\Models\Sucursal;
use App\Repositories\SucursalRepository;

class ActualizarSucursalAction
{
    public function __construct(private SucursalRepository $repository) {}

    /** @param array<string, mixed> $datos */
    public function ejecutar(Sucursal $registro, array $datos): Sucursal
    {
        return $this->repository->actualizar($registro, $datos);
    }
}
