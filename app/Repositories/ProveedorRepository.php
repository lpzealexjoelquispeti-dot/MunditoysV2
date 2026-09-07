<?php

namespace App\Repositories;

use App\Models\Proveedor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProveedorRepository
{
    /** @param array<string, mixed> $filtros */
    public function paginar(array $filtros, int $porPagina = 12): LengthAwarePaginator
    {
        $consulta = Proveedor::query()->withCount('lotes');
        $buscar = Str::lower(trim($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $consulta->where(function (Builder $query) use ($buscar): void {
                $query->whereRaw('LOWER(empresa) LIKE ?', ['%'.$buscar.'%'])
                    ->orWhereRaw('LOWER(nombre_contacto) LIKE ?', ['%'.$buscar.'%'])
                    ->orWhereRaw('LOWER(telefono) LIKE ?', ['%'.$buscar.'%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$buscar.'%'])
                    ->orWhereRaw('LOWER(nit) LIKE ?', ['%'.$buscar.'%']);
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
            'nombre_asc' => ['empresa', 'asc'],
            'nombre_desc' => ['empresa', 'desc'],
            default => ['created_at', 'desc'],
        };

        return $consulta->orderBy($campo, $direccion)->orderBy('id', $direccion)->paginate($porPagina);
    }

    public function buscarPorId(int $id, bool $incluirEliminados = false, bool $bloquear = false): Proveedor
    {
        return Proveedor::query()->when($incluirEliminados, fn (Builder $query) => $query->withTrashed())
            ->when($bloquear, fn (Builder $query) => $query->lockForUpdate())
            ->withCount('lotes')->findOrFail($id);
    }

    public function nombreExiste(string $nombre, ?int $excepto = null): bool
    {
        return Proveedor::withTrashed()->whereRaw('LOWER(TRIM(empresa)) = ?', [Str::lower(trim($nombre))])
            ->when($excepto !== null, fn (Builder $query) => $query->where('id', '!=', $excepto))->exists();
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Proveedor
    {
        return Proveedor::query()->create($datos);
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(Proveedor $registro, array $datos): Proveedor
    {
        $registro->update($datos);

        return $registro->refresh();
    }

    public function eliminar(Proveedor $registro): bool
    {
        return (bool) $registro->delete();
    }

    public function restaurar(Proveedor $registro): bool
    {
        return (bool) $registro->restore();
    }

    public function nitExiste(string $nit, ?int $excepto = null): bool
    {
        return Proveedor::withTrashed()->where('nit', $nit)
            ->when($excepto !== null, fn (Builder $query) => $query->where('id', '!=', $excepto))->exists();
    }
}
