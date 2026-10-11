<?php

namespace App\Repositories;

use App\Models\Modelado3D;
use App\Models\Producto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class Modelado3DRepository
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 12): LengthAwarePaginator
    {
        $query = Modelado3D::query()
            ->where('tipo_generacion', '!=', 'fotogrametria_local')
            ->with(['producto.imagenes'])
            ->latest('id');

        if (! empty($filtros['buscar'])) {
            $buscar = trim((string) $filtros['buscar']);
            $query->where(function ($q) use ($buscar) {
                $q->where('task_id', 'like', "%{$buscar}%")
                    ->orWhereHas('producto', function ($pq) use ($buscar) {
                        $pq->where('nombre', 'like', "%{$buscar}%")
                            ->orWhere('codigo_producto', 'like', "%{$buscar}%");
                    });
            });
        }

        if (! empty($filtros['estado'])) {
            $estado = (string) $filtros['estado'];
            if ($estado === 'papelera' || $estado === 'eliminados' || $estado === 'trashed') {
                $query->onlyTrashed();
            } elseif ($estado === 'completados' || $estado === 'completed') {
                $query->whereIn('status', ['success', 'completed']);
            } elseif ($estado === 'procesando' || $estado === 'running') {
                $query->whereIn('status', ['running', 'processing']);
            } elseif ($estado === 'queued') {
                $query->where('status', 'queued');
            } elseif ($estado === 'fallidos' || $estado === 'failed') {
                $query->where('status', 'failed');
            }
        }

        return $query->paginate($porPagina);
    }

    public function buscar(int $id): Modelado3D
    {
        return Modelado3D::query()
            ->with(['producto.imagenes'])
            ->findOrFail($id);
    }

    public function buscarConPapelera(int $id): Modelado3D
    {
        return Modelado3D::withTrashed()
            ->with(['producto.imagenes'])
            ->findOrFail($id);
    }

    public function restaurar(Modelado3D $modelado): bool
    {
        return (bool) $modelado->restore();
    }

    public function buscarBloqueado(int $id): Modelado3D
    {
        return Modelado3D::query()
            ->lockForUpdate()
            ->findOrFail($id);
    }

    public function buscarPorTaskId(string $taskId): ?Modelado3D
    {
        return Modelado3D::query()
            ->where('task_id', $taskId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crearTarea(array $datos): Modelado3D
    {
        return Modelado3D::query()->create($datos);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizarEstado(Modelado3D $modelado, array $datos): Modelado3D
    {
        $modelado->update($datos);

        return $modelado->fresh();
    }

    /**
     * @return Collection<int, Producto>
     */
    public function buscarProductosParaEnlazar(string $termino, int $limite = 8): Collection
    {
        $texto = trim($termino);
        if (mb_strlen($texto) < 2) {
            return new Collection;
        }

        return Producto::query()
            ->with(['imagenes', 'modelos3D'])
            ->where(function ($q) use ($texto) {
                $q->where('nombre', 'like', "%{$texto}%")
                    ->orWhere('codigo_producto', 'like', "%{$texto}%");
            })
            ->orderBy('nombre')
            ->limit($limite)
            ->get();
    }

    public function productoTieneModeloActivo(int $productoId, ?int $excluirModeladoId = null): bool
    {
        return Modelado3D::query()
            ->where('producto_id', $productoId)
            ->whereIn('status', ['success', 'completed'])
            ->whereNotNull('modelo_glb_path')
            ->when($excluirModeladoId !== null, fn ($q) => $q->where('id', '!=', $excluirModeladoId))
            ->exists();
    }
}
