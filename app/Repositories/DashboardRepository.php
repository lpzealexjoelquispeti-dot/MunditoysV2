<?php

namespace App\Repositories;

use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Venta;

class DashboardRepository
{
    /** @return array{totalProductos: int, totalInventarios: int, ventasPendientes: int} */
    public function resumen(): array
    {
        return [
            'totalProductos' => Producto::query()->count(),
            'totalInventarios' => Inventario::query()->count(),
            'ventasPendientes' => Venta::query()->where('estado', 'pendiente')->count(),
        ];
    }
}
