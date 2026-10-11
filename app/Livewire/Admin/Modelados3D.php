<?php

namespace App\Livewire\Admin;

use App\Models\FotografiaModelado;
use App\Models\Modelado3D;
use App\Models\Producto;
use App\Services\FotogrametriaService;
use App\Services\Modelado3DService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Modelados3D extends Component
{
    use WithFileUploads, WithPagination;

    public mixed $foto = null;

    #[Locked]
    public ?int $sesionId = null;

    public string $errorSubida = '';

    public string $filtroFoto = '';

    public string $filtroVista = '';

    public string $ordenFotos = 'ranura';

    public ?int $productoId = null;

    public string $buscarProducto = '';

    public string $modoCaptura = 'plato_giratorio';

    /** @var array{id: int, nombre: string, codigo: string, imagen: ?string, tiene_3d: bool}|null */
    public ?array $productoSeleccionado = null;

    public string $buscar = '';

    public string $filtroEstado = '';

    public bool $mostrarVisor3D = false;

    public string $visorGlbUrl = '';

    public string $visorTitulo = '';

    #[Locked]
    public ?int $visorModeladoId = null;

    protected Modelado3DService $service;

    protected FotogrametriaService $fotogrametria;

    public function boot(Modelado3DService $service, FotogrametriaService $fotogrametria): void
    {
        $this->service = $service;
        $this->fotogrametria = $fotogrametria;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return ['productoId' => ['required', 'integer', 'exists:productos,id']];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return ['productoId.required' => 'Selecciona el juguete al que pertenecen las fotografías.'];
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
        Gate::authorize('create', Modelado3D::class);

        $producto = Producto::with('imagenes')->find($id);

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
        $this->resetErrorBag('productoId');

        if ($tiene3d) {
            $this->notificar('Juguete con modelo 3D existente', 'Este juguete ya tiene un modelo 3D. Puedes preparar una nueva colección sin cambiar el modelo actual.', 'info');
        }
    }

    public function quitarProductoSeleccionado(): void
    {
        $this->productoId = null;
        $this->productoSeleccionado = null;
        $this->buscarProducto = '';
        $this->resetValidation();
    }

    public function crearSesion(): void
    {
        $this->validate();
        $sesion = $this->fotogrametria->crearSesion($this->productoId, $this->modoCaptura);
        $this->abrirSesion($sesion->id);
        $this->quitarProductoSeleccionado();
    }

    public function abrirSesion(int $id): void
    {
        $sesion = $this->fotogrametria->obtenerSesion($id);
        $this->sesionId = $sesion->id;
        $this->reset(['foto', 'errorSubida', 'filtroFoto', 'filtroVista', 'ordenFotos']);
        $this->resetValidation();
        $this->dispatch('fotogrametria-sesion-abierta');
    }

    public function guardarFoto(?int $reemplazarId = null): bool
    {
        $this->resetErrorBag();
        $this->errorSubida = '';

        try {
            $this->validate([
                'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            ], [
                'foto.required' => 'Selecciona una fotografía.',
                'foto.image' => 'El archivo debe ser una imagen válida.',
                'foto.mimes' => 'Utiliza fotografías JPG, PNG o WEBP.',
                'foto.max' => 'Cada fotografía debe pesar como máximo 10 MB.',
            ]);

            $this->fotogrametria->guardarFoto($this->sesionActual(), $this->foto, $reemplazarId);

            return true;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError($campo, $mensaje);
                }
            }

            $this->errorSubida = collect($exception->errors())->flatten()->implode(' ');

            return false;
        } finally {
            $this->reset('foto');
        }
    }

    public function seleccionarReferencia(int $fotoId): void
    {
        $this->fotogrametria->referencia($this->sesionActual(), $fotoId);
    }

    public function clasificarVista(int $fotoId, string $vista): void
    {
        $this->fotogrametria->clasificarVista($this->sesionActual(), $fotoId, $vista);
    }

    public function cambiarModoCaptura(string $modo): void
    {
        $this->fotogrametria->cambiarModoCaptura($this->sesionActual(), $modo);
    }

    public function sugerirVistas(): void
    {
        $asignadas = $this->fotogrametria->sugerirVistas($this->sesionActual());

        $this->notificar(
            $asignadas > 0 ? 'Vistas asignadas' : 'Sin cambios',
            $asignadas > 0
                ? "Se asignaron {$asignadas} vistas según el protocolo 16 · 16 · 16. Corrige manualmente cualquier toma fuera de orden."
                : 'Todas las fotografías ya tienen una vista asignada.',
            $asignadas > 0 ? 'success' : 'info',
        );
    }

    public function etiquetarFoto(int $fotoId, string $etiqueta): void
    {
        if (! in_array($etiqueta, ['apta', 'no_apta', 'sin_etiqueta'], true)) {
            throw ValidationException::withMessages(['etiqueta' => 'La etiqueta seleccionada no es válida.']);
        }

        $this->fotogrametria->etiquetar(
            $this->sesionActual(),
            $fotoId,
            $etiqueta === 'sin_etiqueta' ? null : $etiqueta === 'apta',
        );
    }

    public function analizarColeccion(): void
    {
        $this->resetErrorBag();
        $this->fotogrametria->solicitarAnalisis($this->sesionActual());
    }

    public function confirmarFotosRevisadas(): void
    {
        $confirmadas = $this->fotogrametria->confirmarRevisadas($this->sesionActual());
        $this->notificar('Fotografías confirmadas', "Registraste {$confirmadas} fotografías como aptas tras revisarlas visualmente.", 'success');
    }

    public function reconstruirModelo(): void
    {
        $this->resetErrorBag();
        $this->fotogrametria->solicitarReconstruccion($this->sesionActual());
        $this->notificar('Reconstrucción en cola', 'El modelo 3D se genera en tu equipo. Puedes seguir trabajando; el progreso se actualiza solo.', 'info');
    }

    /**
     * Envía la sesión a la papelera; sus fotos se conservan para poder restaurarla.
     */
    public function archivarSesion(?int $id = null): void
    {
        $id ??= $this->sesionActual();
        $this->fotogrametria->archivarSesion($id);
        $this->cerrarSesionSiEsLaActual($id);
        $this->notificar('Sesión en la papelera', 'Puedes restaurarla con todas sus fotos.', 'success');
    }

    public function restaurarSesion(int $id): void
    {
        $this->fotogrametria->restaurarSesion($id);
        $this->notificar('Sesión restaurada', 'Ya aparece en tus sesiones.', 'success');
    }

    /**
     * Borra la sesión definitivamente (activa o archivada) y libera el espacio de sus fotografías.
     */
    public function eliminarSesion(?int $id = null): void
    {
        $id ??= $this->sesionActual();
        $this->fotogrametria->eliminarSesion($id);
        $this->cerrarSesionSiEsLaActual($id);
        $this->notificar('Sesión eliminada', 'Se borraron sus fotos y se liberó el espacio.', 'success');
    }

    public function cancelarReconstruccion(): void
    {
        $this->fotogrametria->cancelarReconstruccion($this->sesionActual());
        $this->notificar('Reconstrucción cancelada', 'Puedes volver a generar el modelo o eliminar la sesión.', 'info');
    }

    public function publicarModelo(): void
    {
        $modelo = $this->fotogrametria->publicarModelo($this->sesionActual());
        $this->notificar('Modelo publicado', 'El catálogo de «'.($modelo->producto?->nombre ?? 'este juguete').'» ahora muestra el modelo 3D reconstruido.', 'success');
    }

    public function enlazarProducto(int $modeladoId): void
    {
        try {
            $modelado = $this->service->obtener($modeladoId);

            if (! $modelado['producto_id']) {
                $this->notificar('Sin producto asignado', 'Primero asocia el modelo a un producto del catálogo.', 'warning');

                return;
            }

            $productoId = (int) $modelado['producto_id'];
            $yaTeniaOtro = $this->service->productoTieneModeloActivo($productoId, $modeladoId);

            $this->service->enlazar($modeladoId, $productoId);

            if ($yaTeniaOtro) {
                $this->notificar(
                    'Modelo 3D reemplazado',
                    'El producto ya tenía un modelo 3D enlazado previamente, el cual fue desvinculado para activar este nuevo modelo.',
                    'info'
                );
            } else {
                $this->notificar('Modelo enlazado', 'El producto ahora exhibirá este gemelo digital 3D en el catálogo.', 'success');
            }
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
            $this->notificar('Registro eliminado', 'El modelado 3D fue archivado en la papelera correctamente.', 'success');
        } catch (Exception $e) {
            $this->notificar('Error al eliminar', $e->getMessage(), 'error');
        }
    }

    public function restaurar(int $modeladoId): void
    {
        try {
            $this->service->restaurar($modeladoId);
            $this->notificar('Modelo restaurado', 'El modelado 3D fue recuperado de la papelera.', 'success');
        } catch (Exception $e) {
            $this->notificar('Error al restaurar', $e->getMessage(), 'error');
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
            $productosSugeridos = $this->fotogrametria->buscarProductos($this->buscarProducto);
        }

        $sesion = $this->sesionId ? $this->fotogrametria->obtenerSesion($this->sesionId) : null;
        $resumen = $sesion ? $this->fotogrametria->resumen($sesion->id) : null;
        $reconstruccion = $sesion ? $this->fotogrametria->reconstruccionActual($sesion) : null;
        $fotografias = $sesion?->fotografias ?? collect();
        $fotosVisibles = $fotografias
            ->when($this->filtroFoto !== '', fn (Collection $fotos): Collection => $fotos->where('estado', $this->filtroFoto))
            ->when($this->filtroVista !== '', fn (Collection $fotos): Collection => $fotos->where('vista', $this->filtroVista));

        if ($this->ordenFotos === 'similitud' && ! empty($resumen['orden_sugerido'])) {
            $posiciones = array_flip($resumen['orden_sugerido']);
            $fotosVisibles = $fotosVisibles->sortBy(fn (FotografiaModelado $foto): int => $posiciones[$foto->id] ?? PHP_INT_MAX);
        }

        return view('livewire.admin.modelados-3d', [
            'tasks' => $this->service->listar([
                'buscar' => $this->buscar,
                'estado' => $this->filtroEstado,
            ]),
            'productosSugeridos' => $productosSugeridos,
            'sesiones' => $this->fotogrametria->listarSesiones(),
            'sesionesArchivadas' => $this->fotogrametria->listarSesionesArchivadas(),
            'sesion' => $sesion,
            'resumen' => $resumen,
            'reconstruccion' => $reconstruccion,
            'etapasReconstruccion' => [
                'preparacion' => 'Preparando fotografías',
                'caracteristicas' => 'Detectando puntos característicos (GPU)',
                'correspondencias' => 'Relacionando fotografías (GPU)',
                'estructura' => 'Calculando posiciones de cámara',
                'correccion' => 'Corrigiendo distorsión del lente',
                'mascaras' => 'Aplicando máscaras de IA (sin fondo)',
                'nube_densa' => 'Generando nube de puntos densa',
                'malla' => 'Construyendo la malla 3D',
                'refinado' => 'Refinando la malla con las fotos',
                'textura' => 'Aplicando textura fotográfica',
                'empaquetado' => 'Empaquetando el archivo GLB',
            ],
            'fotografias' => $fotografias,
            'fotosVisibles' => $fotosVisibles,
            'fotosPorCorregir' => $sesion?->revision_analizada !== null
                ? $fotografias->whereIn('estado', ['rejected', 'review'])->sortBy(fn (FotografiaModelado $foto): int => $foto->estado === 'rejected' ? 0 : 1)
                : collect(),
            'modosCaptura' => config('fotogrametria.modos_captura'),
            'vistas' => [
                'sin_clasificar' => 'Sin clasificar',
                'frente' => 'Frente',
                'trasera' => 'Trasera',
                'izquierda' => 'Izquierda',
                'derecha' => 'Derecha',
                'superior' => 'Superior',
                'inferior' => 'Inferior',
            ],
        ]);
    }

    private function sesionActual(): int
    {
        if ($this->sesionId === null) {
            throw ValidationException::withMessages(['sesion' => 'Crea o abre una sesión de captura primero.']);
        }

        return $this->sesionId;
    }

    private function cerrarSesionSiEsLaActual(int $id): void
    {
        if ($this->sesionId !== $id) {
            return;
        }

        $this->reset(['sesionId', 'foto', 'errorSubida', 'filtroFoto', 'filtroVista', 'ordenFotos']);
        $this->resetValidation();
        $this->dispatch('fotogrametria-sesion-abierta');
    }

    private function notificar(string $titulo, string $mensaje, string $icono = 'success'): void
    {
        $this->dispatch('alerta-producto', titulo: $titulo, mensaje: $mensaje, icono: $icono);
    }
}
