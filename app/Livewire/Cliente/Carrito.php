<?php

namespace App\Livewire\Cliente;

use App\Services\CarritoService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mi Carrito de Compras - MundiToys')]
class Carrito extends Component
{
    public function incrementar(int $productoId, CarritoService $carritoService): void
    {
        $items = $carritoService->obtenerItems();
        $item = $items->firstWhere('producto_id', $productoId);
        if ($item) {
            $carritoService->actualizarCantidad($productoId, $item['cantidad'] + 1);
            $this->dispatch('carrito-actualizado');
        }
    }

    public function decrementar(int $productoId, CarritoService $carritoService): void
    {
        $items = $carritoService->obtenerItems();
        $item = $items->firstWhere('producto_id', $productoId);
        if ($item) {
            $nuevaCantidad = $item['cantidad'] - 1;
            if ($nuevaCantidad <= 0) {
                $carritoService->eliminar($productoId);
            } else {
                $carritoService->actualizarCantidad($productoId, $nuevaCantidad);
            }
            $this->dispatch('carrito-actualizado');
        }
    }

    public function eliminar(int $productoId, CarritoService $carritoService): void
    {
        $carritoService->eliminar($productoId);
        $this->dispatch('carrito-actualizado');
    }

    public function vaciar(CarritoService $carritoService): void
    {
        $carritoService->vaciar();
        $this->dispatch('carrito-actualizado');
    }

    public function render(CarritoService $carritoService): View
    {
        $items = $carritoService->obtenerItems();
        $total = $carritoService->montoTotal();
        $totalArticulos = $carritoService->totalArticulos();

        return view('livewire.cliente.carrito', [
            'items' => $items,
            'total' => $total,
            'totalArticulos' => $totalArticulos,
        ]);
    }
}
