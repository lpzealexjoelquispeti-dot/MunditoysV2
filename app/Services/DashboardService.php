<?php

namespace App\Services;

use App\Repositories\DashboardRepository;

class DashboardService
{
    public function __construct(private DashboardRepository $dashboardRepository) {}

    /** @return array{totalProductos: int, totalInventarios: int, ventasPendientes: int} */
    public function obtenerResumen(): array
    {
        return $this->dashboardRepository->resumen();
    }
}
