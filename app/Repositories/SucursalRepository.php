<?php

namespace App\Repositories;

use App\Models\Sucursal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SucursalRepository
{
    /** @param array<string, mixed> $filtros */
    public function paginar(array $filtros, int $porPagina = 12): LengthAwarePaginator
    {
        $consulta = Sucursal::query()->withCount('almacenes');
        $buscar = Str::lower(trim($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $consulta->where(function (Builder $query) use ($buscar): void {
                $query->whereRaw('LOWER(nombre) LIKE ?', ['%'.$buscar.'%'])
                    ->orWhereRaw('LOWER(zona) LIKE ?', ['%'.$buscar.'%'])
                    ->orWhereRaw('LOWER(direccion) LIKE ?', ['%'.$buscar.'%'])
                    ->orWhereRaw('LOWER(telefono) LIKE ?', ['%'.$buscar.'%']);
            });
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

    public function buscarPorId(int $id, bool $incluirEliminados = false, bool $bloquear = false): Sucursal
    {
        return Sucursal::query()->when($incluirEliminados, fn (Builder $query) => $query->withTrashed())
            ->when($bloquear, fn (Builder $query) => $query->lockForUpdate())
            ->withCount('almacenes')->findOrFail($id);
    }

    public function nombreExiste(string $nombre, ?int $excepto = null): bool
    {
        return Sucursal::withTrashed()->whereRaw('LOWER(TRIM(nombre)) = ?', [Str::lower(trim($nombre))])
            ->when($excepto !== null, fn (Builder $query) => $query->where('id', '!=', $excepto))->exists();
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Sucursal
    {
        return Sucursal::query()->create($datos);
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(Sucursal $registro, array $datos): Sucursal
    {
        $registro->update($datos);

        return $registro->refresh();
    }

    public function eliminar(Sucursal $registro): bool
    {
        return (bool) $registro->delete();
    }

    public function restaurar(Sucursal $registro): bool
    {
        return (bool) $registro->restore();
    }

    public function tieneDependenciasActivas(Sucursal $registro): bool
    {
        return $registro->almacenes()->where('estado', true)->exists()
            || $registro->entregas()->whereNotIn('estado', ['entregado', 'cancelado'])->exists()
            || DB::table('lotes_inventario')->join('inventarios', 'inventarios.id', '=', 'lotes_inventario.inventario_id')
                ->join('almacenes', 'almacenes.id', '=', 'inventarios.almacen_id')
                ->where('almacenes.sucursal_id', $registro->id)->where('cantidad_disponible', '>', 0)->exists();
    }
}
