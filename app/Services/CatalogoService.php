<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Repositories\ProductoRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CatalogoService
{
    public function __construct(private ProductoRepository $productoRepository) {}

    /**
     * Lista productos activos aplicando los filtros del catálogo.
     *
     * @param  string|array<string, mixed>  $filtros
     */
    public function listar(string|array $filtros = [], int $porPagina = 12): LengthAwarePaginator
    {
        if (is_string($filtros)) {
            $filtros = ['buscar' => $filtros];
        }

        $filtros['estado'] = 'activo';

        return $this->productoRepository->paginar($filtros, $porPagina);
    }

    /**
     * Obtiene las categorías activas con el conteo de productos disponibles.
     *
     * @return Collection<int, Categoria>
     */
    public function categorias(): Collection
    {
        return Categoria::query()
            ->where('estado', true)
            ->withCount(['productos' => fn ($q) => $q->where('estado', 'activo')])
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Obtiene las marcas activas con el conteo de productos disponibles.
     *
     * @return Collection<int, Marca>
     */
    public function marcas(): Collection
    {
        return Marca::query()
            ->where('estado', true)
            ->withCount(['productos' => fn ($q) => $q->where('estado', 'activo')])
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Obtiene un producto por su código único con todas sus relaciones para la vitrina.
     */
    public function obtenerPorCodigo(string $codigo): Producto
    {
        return Producto::query()
            ->where('codigo_producto', $codigo)
            ->where('estado', 'activo')
            ->with([
                'categoria',
                'marca',
                'imagenes',
                'modelos3D',
                'inventarios.lotes',
                'resenas' => fn ($q) => $q->where('estado', 'activo')->with('usuario')->latest(),
            ])
            ->firstOrFail();
    }

    /**
     * Obtiene productos relacionados por categoría o marca excluyendo el actual.
     *
     * @return Collection<int, Producto>
     */
    public function relacionados(Producto $producto, int $limite = 4): Collection
    {
        $relacionados = Producto::query()
            ->where('estado', 'activo')
            ->where('id', '!=', $producto->id)
            ->where(function ($q) use ($producto): void {
                if ($producto->categoria_id) {
                    $q->where('categoria_id', $producto->categoria_id);
                }
                if ($producto->marca_id) {
                    $q->orWhere('marca_id', $producto->marca_id);
                }
            })
            ->with(['categoria', 'marca', 'imagenes', 'modelos3D', 'inventarios.lotes'])
            ->take($limite)
            ->get();

        if ($relacionados->isEmpty()) {
            return Producto::query()
                ->where('estado', 'activo')
                ->where('id', '!=', $producto->id)
                ->with(['categoria', 'marca', 'imagenes', 'modelos3D', 'inventarios.lotes'])
                ->latest('id')
                ->take($limite)
                ->get();
        }

        return $relacionados;
    }

    /**
     * Calcula el stock disponible acumulado de un producto.
     */
    public function stockDisponible(Producto $producto): int
    {
        if ($producto->relationLoaded('inventarios')) {
            return (int) $producto->inventarios
                ->flatMap(fn ($inv) => $inv->lotes ?? collect())
                ->where('estado', 'activo')
                ->sum('cantidad_disponible');
        }

        return (int) $producto->inventarios()
            ->with('lotes')
            ->get()
            ->flatMap->lotes
            ->where('estado', 'activo')
            ->sum('cantidad_disponible');
    }
}
