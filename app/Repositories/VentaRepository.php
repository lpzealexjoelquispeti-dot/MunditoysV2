<?php

namespace App\Repositories;

use App\Models\Venta;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VentaRepository
{
    public function paginar(int $porPagina = 15): LengthAwarePaginator
    {
        return Venta::query()->with(['cliente', 'vendedor'])->latest('fecha_venta')->paginate($porPagina);
    }
}
