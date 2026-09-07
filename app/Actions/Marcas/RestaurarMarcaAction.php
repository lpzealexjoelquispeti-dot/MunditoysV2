<?php

namespace App\Actions\Marcas;

use App\Models\Marca;
use App\Repositories\MarcaRepository;

class RestaurarMarcaAction
{
    public function __construct(private MarcaRepository $repository) {}

    public function ejecutar(Marca $registro): bool
    {
        return $this->repository->restaurar($registro);
    }
}
