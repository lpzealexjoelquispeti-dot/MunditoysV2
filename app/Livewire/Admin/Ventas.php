<?php

namespace App\Livewire\Admin;

use App\Services\VentaService;
use Illuminate\View\View;
use Livewire\Component;

class Ventas extends Component
{
    public function render(VentaService $ventaService): View
    {
        return view('livewire.admin.ventas', [
            'ventas' => $ventaService->listar(),
        ]);
    }
}
