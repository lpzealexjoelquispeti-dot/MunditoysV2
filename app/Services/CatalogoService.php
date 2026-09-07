<?php

namespace App\Services;

use App\Repositories\ProductoRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CatalogoService
{
    public function __construct(private ProductoRepository $productoRepository) {}

    public function listar(string $buscar, int $porPagina = 12): LengthAwarePaginator
    {
        return $this->productoRepository->paginar([
            'buscar' => $buscar,
            'estado' => 'activo',
        ], $porPagina);
    }
}
