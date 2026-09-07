<?php

namespace App\Livewire\Admin;

use App\Services\ProveedorService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Proveedores extends Component
{
    use WithPagination;

    public string $buscar = '';

    public string $filtroEstado = 'activo';

    public string $orden = 'recientes';

    public bool $mostrarFormulario = false;

    public bool $mostrarDetalle = false;

    #[Locked]
    public ?int $registroId = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $detalle = [];

    public string $empresa = '';

    public string $nombre_contacto = '';

    public string $telefono = '';

    public string $email = '';

    public string $direccion = '';

    public string $nit = '';

    public bool $estado = true;

    protected ProveedorService $service;

    public function boot(ProveedorService $service): void
    {
        $this->service = $service;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return array_merge($this->service->reglasFormulario($this->registroId, $this->formulario()), [
            'buscar' => ['nullable', 'string', 'max:100'],
            'filtroEstado' => ['required', Rule::in(['activo', 'inactivo', 'todos', 'eliminados'])],
            'orden' => ['required', Rule::in(['recientes', 'antiguos', 'nombre_asc', 'nombre_desc'])],
        ]);
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return $this->service->mensajes();
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return $this->service->atributos();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['buscar', 'filtroEstado', 'orden'], true)) {
            $this->resetPage();
        }
        $this->validateOnly($property);
    }

    public function crear(): void
    {
        $this->cerrarFormulario();
        $this->mostrarFormulario = true;
    }

    public function editar(int $id): void
    {
        $datos = $this->service->obtenerParaEditar($id);
        $this->cerrarFormulario();
        $this->registroId = $id;
        $this->empresa = (string) ($datos['empresa'] ?? '');
        $this->nombre_contacto = (string) ($datos['nombre_contacto'] ?? '');
        $this->telefono = (string) ($datos['telefono'] ?? '');
        $this->email = (string) ($datos['email'] ?? '');
        $this->direccion = (string) ($datos['direccion'] ?? '');
        $this->nit = (string) ($datos['nit'] ?? '');
        $this->estado = (bool) $datos['estado'];
        $this->mostrarFormulario = true;
    }

    public function guardar(): void
    {
        $datos = $this->validate($this->service->reglasFormulario($this->registroId, $this->formulario()));
        if ($this->registroId === null) {
            $this->service->crear($datos);
        } else {
            $this->service->actualizar($this->registroId, $datos);
        }
        $this->cerrarFormulario();
        $this->resetPage();
        $this->notificar('Registro guardado', 'Los datos se guardaron correctamente.');
    }

    public function verDetalle(int $id): void
    {
        $this->detalle = $this->service->obtenerDetalle($id);
        $this->mostrarDetalle = true;
    }

    public function cerrarDetalle(): void
    {
        $this->mostrarDetalle = false;
        $this->detalle = [];
    }

    public function eliminar(int $id): void
    {
        try {
            $this->service->eliminar($id);
        } catch (ValidationException $exception) {
            $this->notificar('No se puede dar de baja', $exception->validator->errors()->first(), 'warning');

            return;
        }
        $this->resetPage();
        $this->notificar('Registro enviado a la papelera', 'No se eliminó definitivamente. Su historial se conserva.');
    }

    public function restaurar(int $id): void
    {
        try {
            $this->service->restaurar($id);
        } catch (ValidationException $exception) {
            $this->notificar('No se puede restaurar', $exception->validator->errors()->first(), 'warning');

            return;
        }
        $this->resetPage();
        $this->notificar('Registro restaurado', 'La información vuelve a estar disponible.');
    }

    public function cerrarFormulario(): void
    {
        $this->mostrarFormulario = false;
        $this->reset('registroId', 'empresa', 'nombre_contacto', 'telefono', 'email', 'direccion', 'nit', 'estado');
        $this->resetValidation();
    }

    public function limpiarFiltros(): void
    {
        $this->reset('buscar', 'filtroEstado', 'orden');
        $this->resetPage();
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    private function formulario(): array
    {
        return $this->only(['empresa', 'nombre_contacto', 'telefono', 'email', 'direccion', 'nit', 'estado']);
    }

    public function render(): View
    {
        return view('livewire.admin.proveedores', [
            'registros' => $this->service->listar([
                'buscar' => $this->buscar, 'estado' => $this->filtroEstado, 'orden' => $this->orden,
            ]),
        ]);
    }

    private function notificar(string $titulo, string $mensaje, string $icono = 'success'): void
    {
        $this->dispatch('alerta-producto', titulo: $titulo, mensaje: $mensaje, icono: $icono);
    }
}
