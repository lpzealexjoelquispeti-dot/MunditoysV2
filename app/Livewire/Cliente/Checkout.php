<?php

namespace App\Livewire\Cliente;

use Illuminate\View\View;
use Livewire\Component;

class Checkout extends Component
{
    public function render(): View
    {
        return view('livewire.cliente.checkout');
    }
}
