<?php

namespace App\Livewire\Cliente;

use App\Models\Notificacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Campana del navbar con el número de notificaciones sin leer.
 * Se refresca sola (wire:poll) porque las notificaciones de entrega las crea el personal desde el panel.
 */
class IconoNotificaciones extends Component
{
    public int $noLeidas = 0;

    public function mount(): void
    {
        $this->actualizarConteo();
    }

    #[On('notificaciones-actualizadas')]
    public function actualizarConteo(): void
    {
        $this->noLeidas = Auth::check()
            ? Notificacion::query()->where('usuario_id', Auth::id())->where('leido', false)->count()
            : 0;
    }

    public function render(): View
    {
        return view('livewire.cliente.icono-notificaciones');
    }
}
