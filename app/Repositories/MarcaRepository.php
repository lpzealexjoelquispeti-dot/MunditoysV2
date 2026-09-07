<?php

namespace App\Repositories;

use App\Models\Marca;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class MarcaRepository
{
    /** @param array<string, mixed> $filtros */
    public function paginar(array $filtros, int $porPagina = 12): LengthAwarePaginator
    {
        $consulta = Marca::query()->withCount('productos');
        $buscar = Str::lower(trim($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $consulta->where(function (Builder $query) use ($buscar): void {
                $query->whereRaw('LOWER(nombre) LIKE ?', ['%'.$buscar.'%'])
                    ->orWhereRaw('LOWER(descripcion) LIKE ?', ['%'.$buscar.'%']);
            });
        }
        $estado = $filtros['estado'] ?? 'activo';
        if ($estado === 'eliminados') {
            $consulta->onlyTrashed();
        } elseif ($estado !== 'todos') {
            $consulta->where('estado', $estado === 'activo');
        }
        [$campo, $direccion] = match ($filtros['orden'] ?? 'recientes') {
            'antiguos' => ['created_at', 'asc'],
            'nombre_asc' => ['nombre', 'asc'],
            'nombre_desc' => ['nombre', 'desc'],
            default => ['created_at', 'desc'],
        };

        return $consulta->orderBy($campo, $direccion)->orderBy('id', $direccion)->paginate($porPagina);
    }

    public function buscarPorId(int $id, bool $incluirEliminados = false): Marca
    {
        return Marca::query()->when($incluirEliminados, fn (Builder $query) => $query->withTrashed())
            ->withCount('productos')->findOrFail($id);
    }

    public function nombreExiste(string $nombre, ?int $excepto = null): bool
    {
        return Marca::withTrashed()->whereRaw('LOWER(TRIM(nombre)) = ?', [Str::lower(trim($nombre))])
            ->when($excepto !== null, fn (Builder $query) => $query->where('id', '!=', $excepto))->exists();
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Marca
    {
        return Marca::query()->create($datos);
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(Marca $registro, array $datos): Marca
    {
        $registro->update($datos);

        return $registro->refresh();
    }

    public function tieneProductos(Marca $registro): bool
    {
        return $registro->productos()->exists();
    }

    public function eliminar(Marca $registro): bool
    {
        return (bool) $registro->delete();
    }

    public function restaurar(Marca $registro): bool
    {
        return (bool) $registro->restore();
    }
}
