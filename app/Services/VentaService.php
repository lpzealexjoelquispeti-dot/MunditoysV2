<?php

namespace App\Services;

use App\Repositories\VentaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VentaService
{
    public function __construct(private VentaRepository $ventaRepository) {}

    public function listar(int $porPagina = 15): LengthAwarePaginator
    {
        return $this->ventaRepository->paginar($porPagina);
    }
}
