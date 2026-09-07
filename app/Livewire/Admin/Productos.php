<?php

namespace App\Livewire\Admin;

use App\Rules\ArchivoGlb;
use App\Rules\DimensionesProducto;
use App\Services\ProductoService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Productos extends Component
{
    use WithFileUploads, WithPagination;

    public string $buscar = '';

    public string $filtroCategoria = '';

    public string $filtroEdad = '';

    public string $filtroEstado = 'activo';

    public string $orden = 'recientes';

    public bool $modalAbierto = false;

    public bool $modalDetalleAbierto = false;

    public int $paso = 1;

    public ?int $productoId = null;

    public string $nombre = '';

    public string $categoriaId = '';

    public string $marcaId = '';

    public string $descripcion = '';

    public string $edadRecomendada = '';

    public string $dimensiones = '';

    /** @var array<int, mixed> */
    public array $imagenes = [];

    /** @var array<int, array{id: int, ruta: string, url: string}> */
    public array $imagenesExistentes = [];

    /** @var array<int, int> */
    public array $imagenesEliminadas = [];

    public $modeloGlb = null;

    public ?string $modeloActual = null;

    /** @var array<string, mixed> */
    public array $productoDetalle = [];

    /** @var array<int, string> */
    public array $rangosEdad = ['0-2', '3-5', '6-8', '9-12', '13+'];

    /** @return array<string, array<int, mixed>> */
    protected function rules(): array
    {
        return array_merge($this->reglasFiltros(), $this->reglasDatos(), $this->reglasMultimedia());
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'filtroCategoria' => 'categoría',
            'filtroEdad' => 'rango de edad',
            'filtroEstado' => 'estado',
            'categoriaId' => 'categoría',
            'marcaId' => 'marca',
            'edadRecomendada' => 'edad recomendada',
            'imagenes' => 'imágenes del producto',
            'imagenes.*' => 'imagen',
            'modeloGlb' => 'modelo 3D',
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'imagenes.max' => 'Solo puedes cargar hasta 4 imágenes por producto.',
            'imagenes.*.image' => 'Cada archivo debe ser una imagen válida.',
            'imagenes.*.mimes' => 'Las imágenes deben estar en formato JPG, PNG o WEBP.',
            'imagenes.*.max' => 'Cada imagen debe pesar como máximo 3 MB.',
            'modeloGlb.extensions' => 'El modelo 3D debe tener extensión .glb.',
            'modeloGlb.max' => 'El modelo 3D debe pesar como máximo 50 MB.',
        ];
    }

    public function updated(string $property): void
    {
        $this->validateOnly($property);

        if (array_key_exists($property, $this->reglasFiltros())) {
            $this->resetPage();
        }

        if (str_starts_with($property, 'imagenes') && $this->cantidadTotalImagenes() > 4) {
            $this->addError('imagenes', 'Solo puedes conservar un máximo de 4 imágenes.');
        }
    }

    public function abrirCrear(): void
    {
        $this->limpiarFormulario();
        $this->modalAbierto = true;
    }

    public function editar(int $productoId, ProductoService $productoService): void
    {
        $this->limpiarFormulario();
        $producto = $productoService->obtenerParaEditar($productoId);

        $this->productoId = $producto['id'];
        $this->categoriaId = (string) $producto['categoria_id'];
        $this->marcaId = (string) ($producto['marca_id'] ?? '');
        $this->nombre = $producto['nombre'];
        $this->descripcion = $producto['descripcion'] ?? '';
        $this->edadRecomendada = $producto['edad_recomendada'] ?? '';
        $this->dimensiones = $producto['dimensiones'] ?? '';
        $this->imagenesExistentes = $producto['imagenes'];
        $this->modeloActual = $producto['modelo_glb'];
        $this->modalAbierto = true;
    }

    public function siguientePaso(): void
    {
        $this->validate($this->reglasDatos());
        $this->paso = 2;
    }

    public function anteriorPaso(): void
    {
        $this->paso = 1;
    }

    public function quitarImagenNueva(int $indice): void
    {
        if (! array_key_exists($indice, $this->imagenes)) {
            return;
        }

        unset($this->imagenes[$indice]);
        $this->imagenes = array_values($this->imagenes);
        $this->resetErrorBag('imagenes');
    }

    public function quitarImagenExistente(int $imagenId): void
    {
        $imagen = collect($this->imagenesExistentes)->firstWhere('id', $imagenId);

        if ($imagen === null) {
            return;
        }

        $this->imagenesExistentes = collect($this->imagenesExistentes)
            ->reject(fn (array $item): bool => $item['id'] === $imagenId)
            ->values()
            ->all();
        $this->imagenesEliminadas[] = $imagenId;
        $this->resetErrorBag('imagenes');
    }

    public function quitarModeloGlb(): void
    {
        $this->modeloGlb = null;
        $this->resetErrorBag('modeloGlb');
    }

    public function guardar(ProductoService $productoService): void
    {
        if ($this->paso === 1) {
            $this->siguientePaso();

            return;
        }

        $validado = $this->validate($this->reglasDatos());
        $this->validarMultimedia();
        $atributos = [
            'categoria_id' => (int) $validado['categoriaId'],
            'marca_id' => (int) $validado['marcaId'],
            'nombre' => $validado['nombre'],
            'descripcion' => $validado['descripcion'],
            'edad_recomendada' => $validado['edadRecomendada'],
            'dimensiones' => $validado['dimensiones'],
        ];

        try {
            if ($this->productoId === null) {
                $productoService->crear($atributos, $this->imagenes, $this->modeloGlb);
                $mensaje = 'El producto fue creado correctamente.';
            } else {
                $productoService->actualizar(
                    $this->productoId,
                    $atributos,
                    $this->imagenes,
                    $this->imagenesEliminadas,
                    $this->modeloGlb,
                );
                $mensaje = 'Los cambios del producto fueron guardados.';
            }
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return;
        }

        $this->cerrarModal();
        $this->dispatch('alerta-producto', titulo: '¡Operación exitosa!', mensaje: $mensaje, icono: 'success');
    }

    public function verDetalle(int $productoId, ProductoService $productoService): void
    {
        $this->productoDetalle = $productoService->obtenerDetalle($productoId);
        $this->modalDetalleAbierto = true;
    }

    public function cerrarDetalle(): void
    {
        $this->modalDetalleAbierto = false;
        $this->productoDetalle = [];
    }

    public function eliminar(int $productoId, ProductoService $productoService): void
    {
        $resultado = $productoService->eliminar($productoId);

        if (! $resultado['success']) {

            $this->dispatch(
                'alerta-producto',
                titulo: 'Producto con dependencias',
                mensaje: $resultado['mensaje'],
                icono: 'warning'
            );

            return;
        }

        $this->resetPage();

        $this->dispatch(
            'alerta-producto',
            titulo: 'Producto enviado a la papelera',
            mensaje: 'El producto no fue eliminado definitivamente. Puedes restaurarlo cuando lo necesites.',
            icono: 'success'
        );
    }

    public function restaurar(int $productoId, ProductoService $productoService): void
    {
        $productoService->restaurar($productoId);
        $this->resetPage();
        $this->dispatch('alerta-producto', titulo: 'Producto restaurado', mensaje: 'El producto vuelve a estar disponible.', icono: 'success');
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'filtroCategoria', 'filtroEdad']);
        $this->filtroEstado = 'activo';
        $this->orden = 'recientes';
        $this->resetValidation();
        $this->resetPage();
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->limpiarFormulario();
    }

    public function render(ProductoService $productoService): View
    {
        $opciones = $productoService->opcionesFormulario();

        return view('livewire.admin.productos', [
            'categorias' => $opciones['categorias'],
            'marcas' => $opciones['marcas'],
            'productos' => $productoService->listar([
                'buscar' => $this->buscar,
                'categoria_id' => $this->filtroCategoria,
                'edad' => $this->filtroEdad,
                'estado' => $this->filtroEstado,
                'orden' => $this->orden,
            ]),
        ]);
    }

    private function limpiarFormulario(): void
    {
        $this->reset([
            'productoId', 'nombre', 'categoriaId', 'marcaId', 'descripcion',
            'edadRecomendada', 'dimensiones',
            'imagenes', 'imagenesExistentes', 'imagenesEliminadas', 'modeloGlb', 'modeloActual',
        ]);
        $this->paso = 1;
        $this->resetValidation();
    }

    private function validarMultimedia(): void
    {
        $this->validate($this->reglasMultimedia());

        if ($this->cantidadTotalImagenes() < 3 || $this->cantidadTotalImagenes() > 4) {
            throw ValidationException::withMessages([
                'imagenes' => 'Debes cargar y conservar entre 3 y 4 imágenes del juguete.',
            ]);
        }
    }

    private function cantidadTotalImagenes(): int
    {
        return count($this->imagenesExistentes) + count($this->imagenes);
    }

    /** @return array<string, array<int, mixed>> */
    private function reglasFiltros(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'filtroCategoria' => ['nullable', 'integer', 'exists:categorias,id'],
            'filtroEdad' => ['nullable', Rule::in($this->rangosEdad)],
            'filtroEstado' => ['required', Rule::in(['activo', 'inactivo', 'todos', 'eliminados'])],
            'orden' => ['required', Rule::in(['recientes', 'antiguos', 'nombre_asc', 'nombre_desc'])],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function reglasDatos(): array
    {
        return [
            'nombre' => ['bail', 'required', 'string', 'min:3', 'max:150', 'regex:/^[\pL\pM\s]+$/u'],
            'categoriaId' => ['required', 'integer', 'exists:categorias,id'],
            'marcaId' => ['required', 'integer', 'exists:marcas,id'],
            'descripcion' => ['required', 'string', 'min:10', 'max:2000'],
            'edadRecomendada' => ['required', Rule::in($this->rangosEdad)],
            'dimensiones' => ['bail', 'required', 'string', 'max:100', new DimensionesProducto],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function reglasMultimedia(): array
    {
        return [
            'imagenes' => ['array', 'max:4'],
            'imagenes.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'modeloGlb' => ['bail', 'nullable', 'file', 'max:51200', new ArchivoGlb],
        ];
    }

    public function confirmarEliminar(int $id): void
    {
        $this->dispatch(
            'confirmar-eliminacion',
            id: $id
        );
    }

    #[On('eliminarProducto')]
    public function eliminarProducto($id, ProductoService $productoService): void
    {
        $this->eliminar((int) $id, $productoService);
    }
}
