<?php

namespace App\Livewire\Cliente;

use App\Services\DireccionService;
use App\Services\GeocodificacionInversaService;
use App\Services\UbicacionLaPazService;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MisDirecciones extends Component
{
    public bool $mostrarModal = false;

    public bool $modoEdicion = false;

    public ?int $direccionId = null;

    public string $nombreReferencia = '';

    public string $direccionCompleta = '';

    public string $zona = '';

    public string $ciudad = 'La Paz';

    public string $latitud = '-16.4950000';

    public string $longitud = '-68.1330000';

    public string $detallesAdicionales = '';

    public bool $principal = false;

    /** Últimos valores autocompletados desde el mapa; permiten actualizarlos si el cliente no los editó. */
    #[Locked]
    public string $zonaSugerida = '';

    #[Locked]
    public string $direccionSugerida = '';

    public ?int $direccionEliminarId = null;

    public bool $mostrarModalEliminar = false;

    public ?string $mensajeFeedback = null;

    public ?string $tipoFeedback = null;

    protected function rules(): array
    {
        return [
            'nombreReferencia' => ['bail', 'required', 'string', 'min:3', 'max:60'],
            'direccionCompleta' => ['bail', 'required', 'string', 'min:5', 'max:255'],
            'zona' => ['bail', 'required', 'string', 'min:2', 'max:80'],
            'ciudad' => ['required', 'in:La Paz'],
            'latitud' => ['bail', 'required', 'regex:/\A-?\d{1,3}(?:\.\d{1,7})?\z/', 'numeric', 'between:-90,90'],
            'longitud' => ['bail', 'required', 'regex:/\A-?\d{1,3}(?:\.\d{1,7})?\z/', 'numeric', 'between:-180,180'],
            'detallesAdicionales' => ['nullable', 'string', 'max:255'],
            'principal' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'nombreReferencia' => 'nombre de referencia',
            'direccionCompleta' => 'dirección detallada',
            'zona' => 'zona o barrio',
            'ciudad' => 'ciudad',
            'latitud' => 'latitud',
            'longitud' => 'longitud',
            'detallesAdicionales' => 'detalles de entrega',
        ];
    }

    protected function messages(): array
    {
        return [
            'nombreReferencia.required' => 'Indica un nombre de referencia (ej. Mi Casa, Trabajo).',
            'nombreReferencia.min' => 'El nombre de referencia debe tener al menos 3 caracteres.',
            'nombreReferencia.max' => 'El nombre de referencia no puede superar 60 caracteres.',
            'direccionCompleta.required' => 'Escribe tu dirección completa (calle, número y edificio si aplica).',
            'direccionCompleta.min' => 'La dirección debe tener al menos 5 caracteres.',
            'direccionCompleta.max' => 'La dirección no puede superar 255 caracteres.',
            'zona.required' => 'Indica la zona o barrio de La Paz (ej. Sopocachi, Miraflores).',
            'ciudad.in' => 'Por el momento los envíos están habilitados únicamente para la ciudad de La Paz.',
            'latitud.required' => 'Debes ubicar tu dirección en el mapa interactivo.',
            'longitud.required' => 'Debes ubicar tu dirección en el mapa interactivo.',
            '*.regex' => 'Las coordenadas deben ser números válidos con hasta 7 decimales.',
        ];
    }

    public function abrirModalCrear(DireccionService $service): void
    {
        $userId = (int) auth()->id();

        if (! $service->puedeAgregar($userId)) {
            $this->mensajeFeedback = 'Has alcanzado el límite máximo de 3 direcciones. Para registrar una nueva, debes eliminar una dirección antigua.';
            $this->tipoFeedback = 'error';

            return;
        }

        $this->resetValidation();
        $this->reset('zonaSugerida', 'direccionSugerida');
        $this->modoEdicion = false;
        $this->direccionId = null;
        $this->nombreReferencia = '';
        $this->direccionCompleta = '';
        $this->zona = '';
        $this->ciudad = 'La Paz';
        $this->latitud = '-16.4950000';
        $this->longitud = '-68.1330000';
        $this->detallesAdicionales = '';
        $this->principal = $service->contar($userId) === 0;
        $this->mostrarModal = true;

        $this->dispatch('inicializar-mapa-direccion', [
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
        ]);
    }

    public function abrirModalEditar(int $id, DireccionService $service): void
    {
        $userId = (int) auth()->id();
        $direccion = $service->listar($userId)->firstWhere('id', $id);

        if (! $direccion) {
            return;
        }

        $this->resetValidation();
        $this->reset('zonaSugerida', 'direccionSugerida');
        $this->modoEdicion = true;
        $this->direccionId = $direccion->id;
        $this->nombreReferencia = $direccion->nombre_referencia ?? '';
        $this->direccionCompleta = $direccion->direccion_completa;
        $this->zona = $direccion->zona ?? '';
        $this->ciudad = 'La Paz';
        $this->latitud = number_format((float) $direccion->latitud, 7, '.', '');
        $this->longitud = number_format((float) $direccion->longitud, 7, '.', '');
        $this->detallesAdicionales = $direccion->ubicacion_jsonb['detalles'] ?? '';
        $this->principal = (bool) $direccion->principal;
        $this->mostrarModal = true;

        $this->dispatch('inicializar-mapa-direccion', [
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
        ]);
    }

    /**
     * Fija el pin y autocompleta calle y zona. Solo reemplaza campos vacíos o que se autocompletaron antes,
     * para no borrar lo que el cliente escribió a mano.
     */
    public function seleccionarUbicacion(string $lat, string $lng, GeocodificacionInversaService $geocodificacion): void
    {
        $this->latitud = $lat;
        $this->longitud = $lng;
        $this->resetValidation(['latitud', 'longitud']);

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return;
        }

        $sugerencia = $geocodificacion->buscar((float) $lat, (float) $lng);
        if (! $sugerencia) {
            return;
        }

        if ($sugerencia['zona'] && ($this->zona === '' || $this->zona === $this->zonaSugerida)) {
            $this->zona = $this->zonaSugerida = $sugerencia['zona'];
            $this->resetValidation('zona');
        }

        if ($sugerencia['direccion'] && ($this->direccionCompleta === '' || $this->direccionCompleta === $this->direccionSugerida)) {
            $this->direccionCompleta = $this->direccionSugerida = $sugerencia['direccion'];
            $this->resetValidation('direccionCompleta');
        }
    }

    public function cerrarModal(): void
    {
        $this->mostrarModal = false;
        $this->resetValidation();
    }

    public function guardar(DireccionService $service, UbicacionLaPazService $ubicacionService): void
    {
        $this->validate();

        $userId = (int) auth()->id();

        // Validar límite en creación
        if (! $this->modoEdicion && ! $service->puedeAgregar($userId)) {
            throw ValidationException::withMessages([
                'limite' => 'Has alcanzado el límite máximo de 3 direcciones. Para registrar una nueva, debes eliminar una dirección antigua.',
            ]);
        }

        // Validar límite cartográfico en La Paz
        if (! $ubicacionService->contiene((float) $this->latitud, (float) $this->longitud)) {
            throw ValidationException::withMessages([
                'latitud' => 'Por el momento, MundiToys solo realiza entregas dentro del municipio de La Paz. Por favor selecciona una ubicación dentro de los límites.',
            ]);
        }

        $payload = [
            'nombre_referencia' => $this->nombreReferencia,
            'direccion_completa' => $this->direccionCompleta,
            'zona' => $this->zona,
            'ciudad' => 'La Paz',
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
            'ubicacion_jsonb' => [
                'detalles' => $this->detallesAdicionales,
            ],
            'principal' => $this->principal,
        ];

        try {
            if ($this->modoEdicion && $this->direccionId) {
                $service->actualizar($userId, $this->direccionId, $payload);
                $this->mensajeFeedback = '¡Dirección actualizada correctamente!';
            } else {
                $service->crear($userId, $payload);
                $this->mensajeFeedback = '¡Dirección registrada exitosamente!';
            }

            $this->tipoFeedback = 'success';
            $this->mostrarModal = false;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->mensajeFeedback = 'No se pudo guardar la dirección. Intenta nuevamente.';
            $this->tipoFeedback = 'error';
        }
    }

    public function confirmarEliminar(int $id): void
    {
        $this->direccionEliminarId = $id;
        $this->mostrarModalEliminar = true;
    }

    public function cancelarEliminar(): void
    {
        $this->mostrarModalEliminar = false;
        $this->direccionEliminarId = null;
    }

    public function eliminar(DireccionService $service): void
    {
        if (! $this->direccionEliminarId) {
            return;
        }

        $userId = (int) auth()->id();
        $service->eliminar($userId, $this->direccionEliminarId);

        $this->mostrarModalEliminar = false;
        $this->direccionEliminarId = null;
        $this->mensajeFeedback = 'La dirección ha sido eliminada. Ahora tienes un espacio disponible para registrar otra.';
        $this->tipoFeedback = 'success';
    }

    public function marcarPrincipal(int $id, DireccionService $service): void
    {
        $userId = (int) auth()->id();
        $service->establecerPrincipal($userId, $id);

        $this->mensajeFeedback = 'Dirección principal actualizada con éxito.';
        $this->tipoFeedback = 'success';
    }

    public function render(DireccionService $service): View
    {
        $userId = (int) auth()->id();
        $direcciones = $service->listar($userId);
        $totalDirecciones = $direcciones->count();
        $puedeAgregar = $service->puedeAgregar($userId);

        return view('livewire.cliente.mis-direcciones', [
            'direcciones' => $direcciones,
            'totalDirecciones' => $totalDirecciones,
            'puedeAgregar' => $puedeAgregar,
            'limiteMaximo' => DireccionService::LIMITE_MAXIMO_DIRECCIONES,
        ]);
    }
}
