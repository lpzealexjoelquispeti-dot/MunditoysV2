<?php

namespace App\Actions\Proveedores;

use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;

class CrearProveedorAction
{
    public function __construct(private ProveedorRepository $repository) {}

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos): Proveedor
    {
        return $this->repository->crear($datos);
    }
}
