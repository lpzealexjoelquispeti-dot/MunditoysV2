<?php

namespace App\Livewire\Cliente;

use App\Services\CatalogoService;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Catálogo de Juguetes - MundiToys')]
class Catalogo extends Component
{
    use WithPagination;

    #[Url(as: 'buscar')]
    public string $buscar = '';

    #[Url(as: 'categoria')]
    public string $categoriaId = '';

    #[Url(as: 'marca')]
    public string $marcaId = '';

    #[Url(as: 'edad')]
    public string $edad = '';

    #[Url(as: 'precio_min')]
    public string $precioMin = '';

    #[Url(as: 'precio_max')]
    public string $precioMax = '';

    #[Url(as: 'solo_3d')]
    public bool $solo3D = false;

    #[Url(as: 'orden')]
    public string $orden = 'recientes';

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedCategoriaId(): void
    {
        $this->resetPage();
    }

    public function updatedMarcaId(): void
    {
        $this->resetPage();
    }

    public function updatedEdad(): void
    {
        $this->resetPage();
    }

    public function updatedPrecioMin(): void
    {
        $this->resetPage();
    }

    public function updatedPrecioMax(): void
    {
        $this->resetPage();
    }

    public function updatedSolo3D(): void
    {
        $this->resetPage();
    }

    public function updatedOrden(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->buscar = '';
        $this->categoriaId = '';
        $this->marcaId = '';
        $this->edad = '';
        $this->precioMin = '';
        $this->precioMax = '';
        $this->solo3D = false;
        $this->orden = 'recientes';
        $this->resetPage();
    }

    public function render(CatalogoService $catalogoService): View
    {
        $filtros = [
            'buscar' => $this->buscar,
            'categoria_id' => $this->categoriaId,
            'marca_id' => $this->marcaId,
            'edad' => $this->edad,
            'precio_min' => $this->precioMin !== '' ? (float) $this->precioMin : null,
            'precio_max' => $this->precioMax !== '' ? (float) $this->precioMax : null,
            'solo_3d' => $this->solo3D,
            'orden' => $this->orden,
        ];

        return view('livewire.cliente.catalogo', [
            'productos' => $catalogoService->listar($filtros, 12),
            'categorias' => $catalogoService->categorias(),
            'marcas' => $catalogoService->marcas(),
        ]);
    }
}
