<?php

namespace App\Repositories;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductoRepository
{
    public function buscarPorId(int $id, bool $incluirEliminados = false): Producto
    {
        $consulta = $this->consultaBase();

        if ($incluirEliminados) {
            $consulta->withTrashed();
        }

        return $consulta->findOrFail($id);
    }

    /**
     * @param  array{buscar?: string, categoria_id?: int|string, edad?: string, estado?: string, precio_min?: float|string, precio_max?: float|string, orden?: string}  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $consulta = $this->consultaBase();

        if (($buscar = $filtros['buscar'] ?? null) !== null && $buscar !== '') {
            $consulta->where(function (Builder $query) use ($buscar): void {
                $busqueda = '%'.Str::lower($buscar).'%';

                $query->whereRaw('LOWER(nombre) LIKE ?', [$busqueda])
                    ->orWhereRaw('LOWER(codigo_producto) LIKE ?', [$busqueda])
                    ->orWhereRaw('LOWER(codigo_qr) LIKE ?', [$busqueda]);
            });
        }

        if (($categoriaId = $filtros['categoria_id'] ?? null) !== null && $categoriaId !== '') {
            $consulta->where('categoria_id', $categoriaId);
        }

        if (($marcaId = $filtros['marca_id'] ?? null) !== null && $marcaId !== '') {
            $consulta->where('marca_id', $marcaId);
        }

        if (($edad = $filtros['edad'] ?? null) !== null && $edad !== '') {
            $consulta->where('edad_recomendada', $edad);
        }

        if (! empty($filtros['solo_3d'])) {
            $consulta->whereHas('modelos3D', function (Builder $query): void {
                $query->whereNotNull('modelo_glb_path');
            });
        }

        $estado = $filtros['estado'] ?? 'activo';

        if ($estado === 'eliminados') {
            $consulta->onlyTrashed();
        } elseif ($estado !== 'todos') {
            $consulta->where('estado', $estado);
        }

        if (($precioMinimo = $filtros['precio_min'] ?? null) !== null && $precioMinimo !== '') {
            $consulta->where('precio_venta', '>=', $precioMinimo);
        }

        if (($precioMaximo = $filtros['precio_max'] ?? null) !== null && $precioMaximo !== '') {
            $consulta->where('precio_venta', '<=', $precioMaximo);
        }

        [$columna, $direccion] = match ($filtros['orden'] ?? 'recientes') {
            'antiguos' => ['created_at', 'asc'],
            'nombre_asc' => ['nombre', 'asc'],
            'nombre_desc' => ['nombre', 'desc'],
            'precio_asc' => ['precio_venta', 'asc'],
            'precio_desc' => ['precio_venta', 'desc'],
            default => ['created_at', 'desc'],
        };

        return $consulta->orderBy($columna, $direccion)->orderBy('id', $direccion)->paginate($porPagina);
    }

    /** @return Collection<int, Categoria> */
    public function categoriasActivas(): Collection
    {
        return Categoria::query()->where('estado', true)->orderBy('nombre')->get(['id', 'nombre']);
    }

    /** @return Collection<int, Marca> */
    public function marcas(): Collection
    {
        return Marca::query()->where('estado', true)->orderBy('nombre')->get(['id', 'nombre']);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function crear(array $atributos): Producto
    {
        return Producto::query()->create($atributos);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function actualizar(Producto $producto, array $atributos): Producto
    {
        $producto->update($atributos);

        return $producto->refresh();
    }

    public function eliminar(Producto $producto): bool
    {
        return (bool) $producto->delete();
    }

    public function restaurar(Producto $producto): bool
    {
        return (bool) $producto->restore();
    }

    public function codigoProductoExiste(string $codigo): bool
    {
        return Producto::query()->withTrashed()->where('codigo_producto', $codigo)->exists();
    }

    public function codigoQrExiste(string $codigo): bool
    {
        return Producto::query()->withTrashed()->where('codigo_qr', $codigo)->exists();
    }

    /**
     * @return Builder<Producto>
     */
    private function consultaBase(): Builder
    {
        return Producto::query()->with(['categoria', 'marca', 'imagenes', 'modelos3D']);
    }
}
