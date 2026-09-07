<?php

namespace App\Actions\Productos;

use App\Models\Producto;
use App\Repositories\ProductoRepository;

class RestaurarProductoAction
{
    public function __construct(private ProductoRepository $productoRepository) {}

    public function ejecutar(Producto $producto): bool
    {
        return $this->productoRepository->restaurar($producto);
    }
}
