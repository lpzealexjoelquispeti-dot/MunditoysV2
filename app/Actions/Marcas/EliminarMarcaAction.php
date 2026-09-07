<?php

namespace App\Actions\Marcas;

use App\Models\Marca;
use App\Repositories\MarcaRepository;

class EliminarMarcaAction
{
    public function __construct(private MarcaRepository $repository) {}

    public function ejecutar(Marca $registro): bool
    {
        return $this->repository->eliminar($registro);
    }
}
