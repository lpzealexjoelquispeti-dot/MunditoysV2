<?php

namespace App\Actions\Proveedores;

use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;

class EliminarProveedorAction
{
    public function __construct(private ProveedorRepository $repository) {}

    public function ejecutar(Proveedor $registro): bool
    {
        return $this->repository->eliminar($registro);
    }
}
