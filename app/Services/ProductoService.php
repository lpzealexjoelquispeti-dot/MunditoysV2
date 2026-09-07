<?php

namespace App\Services;

use App\Actions\Productos\ActualizarProductoAction;
use App\Actions\Productos\CrearProductoAction;
use App\Actions\Productos\EliminarProductoAction;
use App\Actions\Productos\RestaurarProductoAction;
use App\Models\Producto;
use App\Repositories\ProductoRepository;
use App\Rules\ArchivoGlb;
use App\Rules\DimensionesProducto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductoService
{
    public function __construct(
        private ProductoRepository $productoRepository,
        private CrearProductoAction $crearProductoAction,
        private ActualizarProductoAction $actualizarProductoAction,
        private EliminarProductoAction $eliminarProductoAction,
        private RestaurarProductoAction $restaurarProductoAction,
    ) {}

    /** @param array<string, mixed> $filtros */
    public function listar(array $filtros, int $porPagina = 12): LengthAwarePaginator
    {
        Gate::authorize('viewAny', Producto::class);

        return $this->productoRepository->paginar($filtros, $porPagina);
    }

    /**
     * @return array{categorias: array<int, array{id: int, nombre: string}>, marcas: array<int, array{id: int, nombre: string}>}
     */
    public function opcionesFormulario(): array
    {
        return [
            'categorias' => $this->productoRepository->categoriasActivas()->toArray(),
            'marcas' => $this->productoRepository->marcas()->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function obtenerParaEditar(int $productoId): array
    {
        $producto = $this->productoRepository->buscarPorId($productoId);
        Gate::authorize('update', $producto);

        return $this->mapearProducto($producto);
    }

    /** @return array<string, mixed> */
    public function obtenerDetalle(int $productoId): array
    {
        $producto = $this->productoRepository->buscarPorId($productoId, true);
        Gate::authorize('view', $producto);

        return $this->mapearProducto($producto);
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @param  array<int, UploadedFile>  $imagenes
     */
    public function crear(array $atributos, array $imagenes, ?UploadedFile $modeloGlb = null): Producto
    {
        Gate::authorize('create', Producto::class);
        $atributos = $this->atributosDelCatalogo($atributos);
        $this->validarReglasDeNegocio($atributos, count($imagenes), $modeloGlb);
        $atributos['estado'] = 'activo';
        $atributos['codigo_producto'] = $this->generarCodigoProducto((string) $atributos['nombre']);
        $atributos['codigo_qr'] = $this->generarCodigoQr();

        return $this->crearProductoAction->ejecutar($atributos, $imagenes, $modeloGlb);
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @param  array<int, UploadedFile>  $imagenes
     * @param  array<int, int>  $imagenesEliminadas
     */
    public function actualizar(int $productoId, array $atributos, array $imagenes, array $imagenesEliminadas, ?UploadedFile $modeloGlb = null): Producto
    {
        $producto = $this->productoRepository->buscarPorId($productoId);
        Gate::authorize('update', $producto);
        $imagenesConservadas = $producto->imagenes->whereNotIn('id', $imagenesEliminadas)->count();
        $atributos = $this->atributosDelCatalogo($atributos);
        $this->validarReglasDeNegocio($atributos, $imagenesConservadas + count($imagenes), $modeloGlb);

        return $this->actualizarProductoAction->ejecutar(
            $producto,
            $atributos,
            $imagenes,
            $imagenesEliminadas,
            $modeloGlb,
        );
    }

    public function eliminar(int $productoId): array
    {
        $producto = $this->productoRepository->buscarPorId($productoId);

        Gate::authorize('delete', $producto);

        $dependencias = [];

        if ($producto->inventarios()->exists()) {
            $dependencias[] = 'inventario registrado';
        }

        if ($producto->proveedores()->exists()) {
            $dependencias[] = 'proveedores asociados';
        }

        if ($producto->reseñas()->exists()) {
            $dependencias[] = 'reseñas de clientes';
        }

        if ($producto->modelos3D()->exists()) {
            $dependencias[] = 'modelos 3D';
        }

        // Solo genera advertencia, pero CONTINÚA eliminando
        $advertencia = null;

        if (count($dependencias) > 0) {

            $advertencia =
                'Este producto tiene: '
                .implode(', ', $dependencias)
                .'. Sus registros serán conservados.';
        }

        // AQUÍ estaba faltando
        $this->eliminarProductoAction->ejecutar($producto);

        return [

            'success' => true,

            'mensaje' => 'Producto enviado correctamente',

            'advertencia' => $advertencia,

        ];
    }

    public function restaurar(int $productoId): bool
    {
        $producto = $this->productoRepository->buscarPorId($productoId, true);
        Gate::authorize('restore', $producto);

        return $this->restaurarProductoAction->ejecutar($producto);
    }

    /** @param array<string, mixed> $atributos */
    private function validarReglasDeNegocio(array $atributos, int $cantidadImagenes, ?UploadedFile $modeloGlb): void
    {
        Validator::make(['dimensiones' => $atributos['dimensiones'] ?? null, 'modeloGlb' => $modeloGlb], [
            'dimensiones' => ['bail', 'required', 'string', 'max:100', new DimensionesProducto],
            'modeloGlb' => ['bail', 'nullable', 'file', 'max:51200', new ArchivoGlb],
        ], ['modeloGlb.max' => 'El modelo 3D debe pesar como máximo 50 MB.'])->validate();

        if ($cantidadImagenes < 3 || $cantidadImagenes > 4) {
            throw ValidationException::withMessages(['imagenes' => 'Cada producto debe conservar entre 3 y 4 imágenes.']);
        }
    }

    /** @param array<string, mixed> $atributos
     * @return array<string, mixed>
     */
    private function atributosDelCatalogo(array $atributos): array
    {
        return Arr::only($atributos, [
            'categoria_id', 'marca_id', 'nombre', 'descripcion', 'edad_recomendada', 'dimensiones',
        ]);
    }

    private function generarCodigoProducto(string $nombre): string
    {
        $prefijo = Str::upper(Str::substr(Str::slug($nombre, ''), 0, 12)) ?: 'JUGUETE';

        do {
            $codigo = 'MT-'.$prefijo.'-'.Str::upper(Str::random(6));
        } while ($this->productoRepository->codigoProductoExiste($codigo));

        return $codigo;
    }

    private function generarCodigoQr(): string
    {
        do {
            $codigo = 'MTQR-'.Str::ulid();
        } while ($this->productoRepository->codigoQrExiste($codigo));

        return $codigo;
    }

    /** @return array<string, mixed> */
    private function mapearProducto(Producto $producto): array
    {
        $modelo = $producto->modelos3D->sortByDesc('created_at')->first();

        return [
            'id' => $producto->id,
            'categoria_id' => $producto->categoria_id,
            'categoria' => $producto->categoria->nombre,
            'marca_id' => $producto->marca_id,
            'marca' => $producto->marca?->nombre ?? 'Sin marca',
            'codigo_producto' => $producto->codigo_producto,
            'codigo_qr' => $producto->codigo_qr,
            'nombre' => $producto->nombre,
            'descripcion' => $producto->descripcion,
            'precio_compra_referencia' => $producto->precio_compra_referencia,
            'precio_venta' => $producto->precio_venta,
            'edad_recomendada' => $producto->edad_recomendada,
            'dimensiones' => $producto->dimensiones,
            'estado' => $producto->estado,
            'eliminado' => $producto->trashed(),
            'imagenes' => $producto->imagenes->sortBy('orden')->map(fn ($imagen): array => [
                'id' => $imagen->id,
                'ruta' => $imagen->ruta_archivo,
                'url' => asset('storage/'.$imagen->ruta_archivo),
            ])->values()->all(),
            'modelo_glb' => $modelo?->modelo_glb_path,
            'modelo_glb_url' => $modelo?->modelo_glb_path ? asset('storage/'.$modelo->modelo_glb_path) : null,
        ];
    }
}
