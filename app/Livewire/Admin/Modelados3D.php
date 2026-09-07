<?php

namespace App\Livewire\Admin;

use App\Models\Producto;
use App\Services\Modelado3DService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Modelados3D extends Component
{
    use WithFileUploads, WithPagination;

    /** @var array<int, mixed> */
    public array $fotos = [];

    public ?int $productoId = null;

    public string $buscarProducto = '';

    /** @var array{id: int, nombre: string, codigo: string, imagen: ?string, tiene_3d: bool}|null */
    public ?array $productoSeleccionado = null;

    public string $buscar = '';

    public string $filtroEstado = '';

    // Visor 3D Modal
    public bool $mostrarVisor3D = false;

    public string $visorGlbUrl = '';

    public string $visorTitulo = '';

    #[Locked]
    public ?int $visorModeladoId = null;

    protected Modelado3DService $service;

    public function boot(Modelado3DService $service): void
    {
        $this->service = $service;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return $this->service->reglasFormulario();
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return $this->service->mensajes();
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function seleccionarProducto(int $id): void
    {
        $producto = Producto::with(['imagenes', 'modelos3D'])->find($id);

        if (! $producto) {
            $this->notificar('Producto no encontrado', 'El producto seleccionado ya no está disponible.', 'warning');

            return;
        }

        $imagenPrincipal = $producto->imagenes->where('principal', true)->first()
            ?? $producto->imagenes->first();

        $tiene3d = $producto->modelos3D()
            ->whereIn('status', ['success', 'completed'])
            ->whereNotNull('modelo_glb_path')
            ->exists();

        $this->productoId = $producto->id;
        $this->productoSeleccionado = [
            'id' => $producto->id,
            'nombre' => $producto->nombre,
            'codigo' => $producto->codigo_producto,
            'imagen' => $imagenPrincipal ? asset('storage/'.$imagenPrincipal->ruta_archivo) : null,
            'tiene_3d' => $tiene3d,
        ];

        $this->buscarProducto = '';
    }

    public function quitarProductoSeleccionado(): void
    {
        $this->productoId = null;
        $this->productoSeleccionado = null;
        $this->buscarProducto = '';
    }

    public function procesarModelado(): void
    {
        $this->validate();

        try {
            $modelado = $this->service->iniciarModelado($this->fotos, $this->productoId);

            $this->reset(['fotos', 'productoId', 'productoSeleccionado', 'buscarProducto']);
            $this->resetValidation();
            $this->resetPage();

            $this->notificar(
                '¡Tarea enviada a Tripo AI!',
                "La tarea #{$modelado->id} fue encolada correctamente. Puedes monitorear el progreso del renderizado.",
                'success'
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->notificar('Error al procesar modelado', $e->getMessage(), 'error');
        }
    }

    public function actualizarEstado(int $modeladoId): void
    {
        try {
            $modelado = $this->service->sincronizar($modeladoId);

            if ($modelado->es_completado) {
                $this->notificar(
                    '¡Modelado completado!',
                    'El archivo GLB se sincronizó y descargó correctamente para su visualización interactiva.',
                    'success'
                );
            } elseif ($modelado->es_fallido) {
                $this->notificar(
                    'Tarea fallida en Tripo',
                    $modelado->error_message ?: 'Tripo no pudo completar la geometría del modelo.',
                    'warning'
                );
            } else {
                $this->notificar(
                    'Tarea en proceso',
                    "Progreso actual: {$modelado->progreso}%. Tripo sigue renderizando las mallas.",
                    'info'
                );
            }
        } catch (Exception $e) {
            $this->notificar('Error de sincronización', $e->getMessage(), 'error');
        }
    }

    public function enlazarProducto(int $modeladoId): void
    {
        try {
            $modelado = $this->service->obtener($modeladoId);

            if (! $modelado['producto_id']) {
                $this->notificar('Sin producto asignado', 'Primero asocia el modelo a un producto del catálogo.', 'warning');

                return;
            }

            $this->service->enlazar($modeladoId, (int) $modelado['producto_id']);
            $this->notificar('Modelo enlazado', 'El producto ahora exhibirá este gemelo digital 3D en el catálogo.', 'success');
        } catch (Exception $e) {
            $this->notificar('No se pudo enlazar', $e->getMessage(), 'error');
        }
    }

    public function desenlazarProducto(int $modeladoId): void
    {
        try {
            $this->service->desenlazar($modeladoId);
            $this->notificar('Modelo desenlazado', 'El producto ya no mostrará este modelo 3D.', 'info');
        } catch (Exception $e) {
            $this->notificar('Error al desenlazar', $e->getMessage(), 'error');
        }
    }

    public function eliminar(int $modeladoId): void
    {
        try {
            $this->service->eliminar($modeladoId);
            $this->notificar('Registro eliminado', 'El modelado 3D fue archivado correctamente.', 'success');
        } catch (Exception $e) {
            $this->notificar('Error al eliminar', $e->getMessage(), 'error');
        }
    }

    public function abrirVisor3D(string $urlGlb, string $titulo, ?int $modeladoId = null): void
    {
        $this->visorGlbUrl = $urlGlb;
        $this->visorTitulo = $titulo;
        $this->visorModeladoId = $modeladoId;
        $this->mostrarVisor3D = true;
    }

    public function cerrarVisor3D(): void
    {
        $this->mostrarVisor3D = false;
        $this->reset('visorGlbUrl', 'visorTitulo', 'visorModeladoId');
    }

    public function render(): View
    {
        $productosSugeridos = [];
        if (mb_strlen(trim($this->buscarProducto)) >= 2 && ! $this->productoSeleccionado) {
            $productosSugeridos = $this->service->buscarProductos($this->buscarProducto);
        }

        return view('livewire.admin.modelados-3d', [
            'tasks' => $this->service->listar([
                'buscar' => $this->buscar,
                'estado' => $this->filtroEstado,
            ]),
            'productosSugeridos' => $productosSugeridos,
        ]);
    }

    private function notificar(string $titulo, string $mensaje, string $icono = 'success'): void
    {
        $this->dispatch('alerta-producto', titulo: $titulo, mensaje: $mensaje, icono: $icono);
    }
}
