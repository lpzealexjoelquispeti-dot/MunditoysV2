<?php

namespace App\Services;

use App\Actions\Modelado3D\DesenlazarModeloProductoAction;
use App\Actions\Modelado3D\EliminarModelado3DAction;
use App\Actions\Modelado3D\EnlazarModeloProductoAction;
use App\Actions\Modelado3D\RestaurarModelado3DAction;
use App\Models\Modelado3D;
use App\Models\Producto;
use App\Repositories\Modelado3DRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class Modelado3DService
{
    public function __construct(
        private Modelado3DRepository $repository,
        private EnlazarModeloProductoAction $enlazarAction,
        private DesenlazarModeloProductoAction $desenlazarAction,
        private EliminarModelado3DAction $eliminarAction,
        private RestaurarModelado3DAction $restaurarAction,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function listar(array $filtros = [], int $porPagina = 12): LengthAwarePaginator
    {
        Gate::authorize('viewAny', Modelado3D::class);

        return $this->repository->paginar($filtros, $porPagina);
    }

    /**
     * Busca productos para asociar al generador 3D.
     *
     * @return Collection<int, array{id: int, nombre: string, codigo: string, imagen: ?string, tiene_3d: bool}>
     */
    public function buscarProductos(string $termino, int $limite = 8): Collection
    {
        Gate::authorize('viewAny', Modelado3D::class);

        $productos = $this->repository->buscarProductosParaEnlazar($termino, $limite);

        return $productos->map(function (Producto $producto) {
            $imagenPrincipal = $producto->imagenes->where('principal', true)->first()
                ?? $producto->imagenes->first();

            $tiene3d = $producto->modelos3D()
                ->whereIn('status', ['success', 'completed'])
                ->whereNotNull('modelo_glb_path')
                ->exists();

            return [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'codigo' => $producto->codigo_producto,
                'imagen' => $imagenPrincipal ? asset('storage/'.$imagenPrincipal->ruta_archivo) : null,
                'tiene_3d' => $tiene3d,
            ];
        });
    }

    /** @return array<string, mixed> */
    public function obtener(int $id): array
    {
        $modelado = $this->repository->buscar($id);
        Gate::authorize('view', $modelado);

        return $modelado->toArray();
    }

    /**
     * Enlaza el modelo 3D con un producto del catálogo.
     */
    public function enlazar(int $modeladoId, int $productoId): Modelado3D
    {
        $modelado = $this->repository->buscarBloqueado($modeladoId);
        Gate::authorize('update', $modelado);

        return $this->enlazarAction->ejecutar($modelado, $productoId);
    }

    /**
     * Desvincula el modelo 3D de su producto asignado.
     */
    public function desenlazar(int $modeladoId): Modelado3D
    {
        $modelado = $this->repository->buscarBloqueado($modeladoId);
        Gate::authorize('update', $modelado);

        return $this->desenlazarAction->ejecutar($modelado);
    }

    /**
     * Elimina lógicamente un modelo 3D.
     */
    public function eliminar(int $modeladoId): bool
    {
        $modelado = $this->repository->buscarBloqueado($modeladoId);
        Gate::authorize('delete', $modelado);

        return $this->eliminarAction->ejecutar($modelado);
    }

    /**
     * Restaura un modelo 3D previamente enviado a la papelera.
     */
    public function restaurar(int $modeladoId): bool
    {
        $modelado = $this->repository->buscarConPapelera($modeladoId);
        Gate::authorize('delete', $modelado);

        return $this->restaurarAction->ejecutar($modelado);
    }

    /**
     * Verifica si un producto ya tiene un modelo 3D activo y enlazado.
     */
    public function productoTieneModeloActivo(int $productoId, ?int $excluirModeladoId = null): bool
    {
        return $this->repository->productoTieneModeloActivo($productoId, $excluirModeladoId);
    }
}
