<?php

namespace App\Livewire\Admin;

use App\Models\ConfiguracionEnvio;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class TarifasEnvio extends Component
{
    public bool $habilitado = false;

    public string $tarifa_km_usd = '';

    public string $minimo_usd = '';

    public string $cambio_paralelo = '';

    public function boot(): void
    {
        $this->authorize('administrar accesos');
    }

    public function mount(): void
    {
        $config = ConfiguracionEnvio::query()->find(1);
        $this->habilitado = $config?->habilitado ?? false;
        foreach (['tarifa_km_usd', 'minimo_usd', 'cambio_paralelo'] as $campo) {
            $this->{$campo} = (string) ($config?->{$campo} ?? '');
        }
    }

    public function guardar(): void
    {
        $this->authorize('administrar accesos');
        $reglas = ['bail', 'required', 'numeric', 'decimal:0,4', 'gt:0', 'max:10000'];
        $datos = $this->validate([
            'habilitado' => ['boolean'],
            'tarifa_km_usd' => $reglas, 'minimo_usd' => $reglas, 'cambio_paralelo' => $reglas,
        ], [
            '*.required' => 'Este importe es obligatorio.', '*.numeric' => 'Ingresa un número válido, usando punto para los decimales.',
            '*.decimal' => 'Se permiten hasta cuatro decimales.', '*.gt' => 'El importe debe ser mayor que cero.',
            '*.max' => 'El importe no puede superar 10000.',
        ]);
        $config = ConfiguracionEnvio::query()->firstOrNew(['id' => 1]);
        $config->fill($datos)->save();
        session()->flash('tarifas_guardadas', 'Configuración guardada. Las nuevas tarifas se aplicarán a las compras que aún no se hayan confirmado.');
    }

    public function render(): View
    {
        return view('livewire.admin.tarifas-envio');
    }
}
