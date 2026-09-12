<?php

namespace App\Livewire\Cliente;

use App\Services\CarritoService;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class IconoCarrito extends Component
{
    public int $conteo = 0;

    public function mount(CarritoService $carritoService): void
    {
        $this->conteo = $carritoService->totalArticulos();
    }

    #[On('carrito-actualizado')]
    public function actualizarConteo(CarritoService $carritoService): void
    {
        $this->conteo = $carritoService->totalArticulos();
    }

    public function render(): View
    {
        return view('livewire.cliente.icono-carrito');
    }
}
