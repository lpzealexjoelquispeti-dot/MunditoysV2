<?php

namespace App\Repositories;

use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\LoteInventario;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventarioRepository
{
    /** @param array<string, mixed> $filtros */
    public function paginar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $consulta = Inventario::query()->with(['producto', 'almacen.sucursal'])
            ->withSum(['lotes as stock_disponible' => fn (Builder $query) => $query->withTrashed()], 'cantidad_disponible')
            ->withCount('lotes');
        if (($filtros['estado'] ?? '') === 'archivados') {
            $consulta->onlyTrashed();
        }
        if ($buscar = trim($filtros['buscar'] ?? '')) {
            $consulta->whereHas('producto', function (Builder $query) use ($buscar): void {
                $query->where(function (Builder $query) use ($buscar): void {
                    $query->whereRaw('LOWER(nombre) LIKE ?', ['%'.Str::lower($buscar).'%'])
                        ->orWhereRaw('LOWER(codigo_producto) LIKE ?', ['%'.Str::lower($buscar).'%']);
                });
            });
        }
        if (! empty($filtros['sucursal'])) {
            $consulta->whereHas('almacen', fn (Builder $query) => $query->where('sucursal_id', (int) $filtros['sucursal']));
        }
        if (! empty($filtros['almacen'])) {
            $consulta->where('almacen_id', (int) $filtros['almacen']);
        }
        $stockSql = '(SELECT COALESCE(SUM(cantidad_disponible), 0) FROM lotes_inventario WHERE lotes_inventario.inventario_id = inventarios.id)';
        if (($filtros['stock'] ?? '') === 'agotado') {
            $consulta->whereRaw($stockSql.' = 0');
        } elseif (($filtros['stock'] ?? '') === 'bajo') {
            $consulta->whereRaw($stockSql.' <= inventarios.stock_minimo');
        } elseif (($filtros['stock'] ?? '') === 'disponible') {
            $consulta->whereRaw($stockSql.' > 0');
        }
        [$campo, $direccion] = match ($filtros['orden'] ?? '') {
            'antiguos' => ['created_at', 'asc'],
            'stock_asc' => ['stock_disponible', 'asc'],
            'stock_desc' => ['stock_disponible', 'desc'],
            default => ['created_at', 'desc'],
        };

        return $consulta->orderBy($campo, $direccion)->orderBy('id', $direccion)->paginate($porPagina);
    }

    /** @return array<string, mixed> */
    public function opciones(): array
    {
        return [
            'sucursales' => Sucursal::query()->where('estado', true)->orderBy('nombre')->get(['id', 'nombre'])->toArray(),
            'almacenes' => Almacen::query()->where('estado', true)->whereHas('sucursal', fn (Builder $query) => $query->where('estado', true)->whereNull('sucursales.deleted_at'))
                ->orderBy('nombre')->get(['id', 'sucursal_id', 'nombre'])->toArray(),
            'proveedores' => Proveedor::query()->where('estado', true)->orderBy('empresa')->get(['id', 'empresa'])->toArray(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function buscarProductos(string $buscar): array
    {
        return Producto::query()->where('estado', 'activo')->with(['imagenes', 'categoria', 'marca'])
            ->when(trim($buscar) !== '', function (Builder $query) use ($buscar): void {
                $query->where(function (Builder $query) use ($buscar): void {
                    $query->whereRaw('LOWER(nombre) LIKE ?', ['%'.Str::lower(trim($buscar)).'%'])
                        ->orWhereRaw('LOWER(codigo_producto) LIKE ?', ['%'.Str::lower(trim($buscar)).'%'])
                        ->orWhereRaw('LOWER(codigo_qr) LIKE ?', ['%'.Str::lower(trim($buscar)).'%']);
                });
            })->orderBy('nombre')->limit(8)->get()->map(fn (Producto $producto): array => $this->productoResumen($producto))->all();
    }

    /** @return array<string, mixed> */
    public function obtenerProducto(int $id): array
    {
        return $this->productoResumen(Producto::query()->where('estado', 'activo')->with(['imagenes', 'categoria', 'marca'])->findOrFail($id));
    }

    /** @return array<string, mixed> */
    private function productoResumen(Producto $producto): array
    {
        return [
            'id' => $producto->id, 'nombre' => $producto->nombre, 'codigo' => $producto->codigo_producto,
            'categoria' => $producto->categoria?->nombre ?? 'Sin categoría', 'marca' => $producto->marca?->nombre ?? 'Sin marca',
            'edad' => $producto->edad_recomendada, 'dimensiones' => $producto->dimensiones,
            'imagenes' => $producto->imagenes->sortBy('orden')->map(fn ($imagen): string => asset('storage/'.$imagen->ruta_archivo))->values()->all(),
        ];
    }

    public function bloquearUbicacionProducto(int $productoId, int $almacenId, int $sucursalId): void
    {
        $producto = Producto::query()->where('estado', 'activo')->lockForUpdate()->find($productoId);
        $sucursal = Sucursal::query()->where('estado', true)->lockForUpdate()->find($sucursalId);
        $almacen = Almacen::query()->where('estado', true)->where('sucursal_id', $sucursalId)->lockForUpdate()->find($almacenId);
        if (! $producto || ! $sucursal || ! $almacen) {
            throw ValidationException::withMessages(['productoId' => 'El producto o la ubicación dejaron de estar disponibles. Vuelve a seleccionarlos.']);
        }
    }

    public function inventarioParaIngreso(int $productoId, int $almacenId, int $stockMinimo): Inventario
    {
        return Inventario::withTrashed()->firstOrCreate(
            ['producto_id' => $productoId, 'almacen_id' => $almacenId],
            ['stock_minimo' => $stockMinimo],
        );
    }

    public function proveedorActivoBloqueado(int $id): ?Proveedor
    {
        return Proveedor::query()->where('estado', true)->lockForUpdate()->find($id);
    }

    public function buscarBloqueado(int $id): Inventario
    {
        return Inventario::withTrashed()->lockForUpdate()->findOrFail($id);
    }

    public function buscarIngreso(string $clave): ?LoteInventario
    {
        return LoteInventario::withTrashed()->where('clave_ingreso', $clave)->first();
    }

    public function buscarLote(int $id): LoteInventario
    {
        return LoteInventario::withTrashed()->findOrFail($id);
    }

    public function buscarLoteBloqueado(int $id): LoteInventario
    {
        return LoteInventario::withTrashed()->lockForUpdate()->findOrFail($id);
    }

    /** @param array<string, mixed> $datos */
    public function crearLote(array $datos): LoteInventario
    {
        return LoteInventario::query()->create($datos);
    }

    /** @param array<string, mixed> $datos */
    public function actualizarLote(LoteInventario $lote, array $datos): LoteInventario
    {
        $lote->update($datos);

        return $lote->refresh();
    }

    /** @return Collection<int, LoteInventario> */
    public function lotesActivosConStock(int $inventarioId): Collection
    {
        return LoteInventario::query()
            ->where('inventario_id', $inventarioId)
            ->where('estado', 'activo')
            ->where('cantidad_disponible', '>', 0)
            ->orderBy('fecha_ingreso', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /** @return Collection<int, LoteInventario> */
    public function lotesFifo(int $inventarioId): Collection
    {
        return LoteInventario::query()
            ->where('inventario_id', $inventarioId)
            ->where('estado', 'activo')
            ->where('cantidad_disponible', '>', 0)
            ->orderBy('fecha_ingreso', 'asc')
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();
    }

    public function obtenerInventarioPorProductoYAlmacen(int $productoId, int $almacenId): ?Inventario
    {
        return Inventario::query()
            ->where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->first();
    }

    /** @param array<string, mixed> $datos */
    public function registrarMovimiento(array $datos): MovimientoInventario
    {
        return MovimientoInventario::query()->create($datos);
    }

    public function stockTotal(Inventario $inventario): int
    {
        return (int) $inventario->lotes()->withTrashed()->sum('cantidad_disponible');
    }

    /** @param array<int, array<string, mixed>> $historial */
    public function archivar(Inventario $inventario, array $historial): void
    {
        $inventario->update(['historial_estado' => $historial]);
        $inventario->delete();
    }

    /** @param array<int, array<string, mixed>> $historial */
    public function restaurar(Inventario $inventario, array $historial): void
    {
        $inventario->update(['historial_estado' => $historial]);
        $inventario->restore();
    }

    public function detalle(int $id): Inventario
    {
        return Inventario::withTrashed()->with(['producto.imagenes', 'almacen.sucursal',
            'lotes' => fn (HasMany $query) => $query->withTrashed()->orderByDesc('fecha_ingreso')->orderByDesc('id'),
            'lotes.proveedor', 'lotes.movimientos.usuario',
        ])->findOrFail($id);
    }
}
