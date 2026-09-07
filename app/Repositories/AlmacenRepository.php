<?php

namespace App\Repositories;

use App\Models\Almacen;
use App\Models\Sucursal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AlmacenRepository
{
    /** @param array<string, mixed> $filtros */
    public function paginar(array $filtros, int $porPagina = 12): LengthAwarePaginator
    {
        $consulta = Almacen::query()->withCount('inventarios')->with('sucursal');
        $buscar = Str::lower(trim($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $consulta->where(function (Builder $query) use ($buscar): void {
                $query->whereRaw('LOWER(nombre) LIKE ?', ['%'.$buscar.'%'])
                    ->orWhereRaw('LOWER(tipo) LIKE ?', ['%'.$buscar.'%']);
            });
        }
        if (! empty($filtros['sucursal'])) {
            $consulta->where('sucursal_id', (int) $filtros['sucursal']);
        }
        if (! empty($filtros['tipo'])) {
            $consulta->where('tipo', $filtros['tipo']);
        }
        $estado = $filtros['estado'] ?? 'activo';
        if ($estado === 'eliminados') {
            $consulta->onlyTrashed();
        } elseif ($estado !== 'todos') {
            $consulta->where('estado', $estado === 'activo');
        }
        [$campo, $direccion] = match ($filtros['orden'] ?? '') {
            'antiguos' => ['created_at', 'asc'],
            'nombre_asc' => ['nombre', 'asc'],
            'nombre_desc' => ['nombre', 'desc'],
            default => ['created_at', 'desc'],
        };

        return $consulta->orderBy($campo, $direccion)->orderBy('id', $direccion)->paginate($porPagina);
    }

    public function buscarPorId(int $id, bool $incluirEliminados = false, bool $bloquear = false): Almacen
    {
        return Almacen::query()->when($incluirEliminados, fn (Builder $query) => $query->withTrashed())
            ->when($bloquear, fn (Builder $query) => $query->lockForUpdate())
            ->withCount('inventarios')->with('sucursal')->findOrFail($id);
    }

    public function nombreExiste(string $nombre, ?int $excepto = null, int $sucursalId = 0): bool
    {
        return Almacen::withTrashed()->whereRaw('LOWER(TRIM(nombre)) = ?', [Str::lower(trim($nombre))])
            ->where('sucursal_id', $sucursalId)
            ->when($excepto !== null, fn (Builder $query) => $query->where('id', '!=', $excepto))->exists();
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Almacen
    {
        return Almacen::query()->create($datos);
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(Almacen $registro, array $datos): Almacen
    {
        $registro->update($datos);

        return $registro->refresh();
    }

    public function eliminar(Almacen $registro): bool
    {
        return (bool) $registro->delete();
    }

    public function restaurar(Almacen $registro): bool
    {
        return (bool) $registro->restore();
    }

    public function sucursales(): array
    {
        return Sucursal::query()->orderBy('nombre')->get(['id', 'nombre', 'estado'])->toArray();
    }

    public function bloquearSucursal(int $id): ?Sucursal
    {
        return Sucursal::query()->lockForUpdate()->find($id);
    }

    public function tieneHistorial(Almacen $registro): bool
    {
        return $registro->inventarios()->withTrashed()->exists();
    }

    public function tieneExistencias(Almacen $registro): bool
    {
        return DB::table('lotes_inventario')->join('inventarios', 'inventarios.id', '=', 'lotes_inventario.inventario_id')
            ->where('inventarios.almacen_id', $registro->id)->where('cantidad_disponible', '>', 0)->exists();
    }
}
