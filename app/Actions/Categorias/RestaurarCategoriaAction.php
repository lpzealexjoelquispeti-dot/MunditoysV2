<?php

namespace App\Actions\Categorias;

use App\Models\Categoria;
use App\Repositories\CategoriaRepository;

class RestaurarCategoriaAction
{
    public function __construct(private CategoriaRepository $repository) {}

    public function ejecutar(Categoria $registro): bool
    {
        return $this->repository->restaurar($registro);
    }
}
