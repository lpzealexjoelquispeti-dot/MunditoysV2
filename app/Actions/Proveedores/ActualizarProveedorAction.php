<?php

namespace App\Actions\Proveedores;

use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;

class ActualizarProveedorAction
{
    public function __construct(private ProveedorRepository $repository) {}

    /** @param array<string, mixed> $datos */
    public function ejecutar(Proveedor $registro, array $datos): Proveedor
    {
        return $this->repository->actualizar($registro, $datos);
    }
}
