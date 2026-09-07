<?php

namespace App\Livewire\Cliente;

use App\Services\CatalogoService;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Catalogo extends Component
{
    use WithPagination;

    public string $buscar = '';

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function render(CatalogoService $catalogoService): View
    {
        return view('livewire.cliente.catalogo', [
            'productos' => $catalogoService->listar($this->buscar),
        ]);
    }
}
