<?php

namespace App\Livewire\Admin;

use App\Services\CategoriaService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Categorias extends Component
{
    use WithFileUploads, WithPagination;

    public string $buscar = '';

    public string $filtroEstado = 'activo';

    public string $orden = 'recientes';

    public bool $mostrarFormulario = false;

    public bool $mostrarDetalle = false;

    #[Locked]
    public ?int $registroId = null;

    #[Locked]
    public ?string $imagenActual = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $detalle = [];

    public string $nombre = '';

    public string $descripcion = '';

    public bool $estado = true;

    public $imagen = null;

    protected CategoriaService $service;

    public function boot(CategoriaService $service): void
    {
        $this->service = $service;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return array_merge($this->service->reglasFormulario($this->registroId, (bool) $this->imagenActual), [
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

    public function updated(string $property): void
    {
        if (in_array($property, ['buscar', 'filtroEstado', 'orden'], true)) {
            $this->resetPage();
        }
        $this->validateOnly($property);
    }

    public function crear(): void
    {
        $this->resetFormulario();
        $this->mostrarFormulario = true;
    }

    public function editar(int $id): void
    {
        $datos = $this->service->obtenerParaEditar($id);
        $this->resetFormulario();
        $this->registroId = $datos['id'];
        $this->nombre = $datos['nombre'];
        $this->descripcion = $datos['descripcion'];
        $this->estado = $datos['estado'];
        $this->imagenActual = $datos['imagen'];
        $this->mostrarFormulario = true;
    }

    public function guardar(): void
    {
        $datos = $this->validate($this->service->reglasFormulario($this->registroId, (bool) $this->imagenActual));
        if ($this->registroId === null) {
            $this->service->crear($datos, $this->imagen);
        } else {
            $this->service->actualizar($this->registroId, $datos, $this->imagen);
        }
        $this->cerrarFormulario();
        $this->resetPage();
        $this->notificar('Categoría guardada', 'Los datos se guardaron correctamente.');
    }

    public function quitarImagenNueva(): void
    {
        $this->reset('imagen');
        $this->resetErrorBag('imagen');
    }

    public function verDetalle(int $id): void
    {
        $this->detalle = $this->service->obtenerDetalle($id);
        $this->mostrarDetalle = true;
    }

    public function eliminar(int $id): void
    {
        try {
            $this->service->eliminar($id);
        } catch (ValidationException $exception) {
            $this->notificar('No se puede enviar a la papelera', $exception->validator->errors()->first(), 'warning');

            return;
        }
        $this->resetPage();
        $this->notificar('Registro enviado a la papelera', 'Se conserva su información y podrás restaurarlo.');
    }

    public function restaurar(int $id): void
    {
        $this->service->restaurar($id);
        $this->resetPage();
        $this->notificar('Registro restaurado', 'La categoría vuelve a estar disponible.');
    }

    public function cerrarFormulario(): void
    {
        $this->mostrarFormulario = false;
        $this->resetFormulario();
    }

    public function cerrarDetalle(): void
    {
        $this->mostrarDetalle = false;
        $this->detalle = [];
    }

    public function limpiarFiltros(): void
    {
        $this->reset('buscar', 'filtroEstado', 'orden');
        $this->resetPage();
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.admin.categorias', [
            'registros' => $this->service->listar(['buscar' => $this->buscar, 'estado' => $this->filtroEstado, 'orden' => $this->orden]),
        ]);
    }

    private function resetFormulario(): void
    {
        $this->reset('registroId', 'imagenActual', 'nombre', 'descripcion', 'estado', 'imagen');
        $this->resetValidation();
    }

    private function notificar(string $titulo, string $mensaje, string $icono = 'success'): void
    {
        $this->dispatch('alerta-producto', titulo: $titulo, mensaje: $mensaje, icono: $icono);
    }
}
