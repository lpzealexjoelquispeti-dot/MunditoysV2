<?php

namespace App\Services;

use App\Models\DetalleVenta;
use App\Models\LoteInventario;
use App\Models\MovimientoInventario;
use App\Models\Sucursal;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StockCheckoutService
{
    public function lotes(int $sucursalId, int $productoId): Builder
    {
        return LoteInventario::query()->where('estado', 'activo')->where('cantidad_disponible', '>', 0)
            ->where(fn (Builder $q) => $q->whereNull('fecha_vencimiento')->orWhereDate('fecha_vencimiento', '>=', today()))
            ->whereHas('inventario', fn (Builder $q) => $q->whereNull('inventarios.deleted_at')->where('producto_id', $productoId)
                ->whereHas('producto', fn (Builder $p) => $p->where('estado', 'activo')->whereNull('productos.deleted_at'))
                ->whereHas('almacen', fn (Builder $a) => $a->where('sucursal_id', $sucursalId)->where('estado', true)->whereNull('almacenes.deleted_at')))
            ->orderBy('fecha_ingreso')->orderBy('id');
    }

    public function disponible(Sucursal $sucursal, Collection $items): bool
    {
        if (! $sucursal->estado || $items->isEmpty()) {
            return false;
        }
        foreach ($items as $item) {
            if ($item['cantidad'] < 1 || $this->lotes($sucursal->id, $item['producto_id'])->sum('cantidad_disponible') < $item['cantidad']) {
                return false;
            }
        }

        return true;
    }

    /** El llamador debe abrir una transacción que incluya la venta y el pago. */
    public function descontar(Venta $venta, Sucursal $sucursal, Collection $items, int $usuarioId): void
    {
        foreach ($items->sortBy('producto_id') as $item) {
            $lotes = $this->lotes($sucursal->id, $item['producto_id'])->lockForUpdate()->get();
            $pendiente = (int) $item['cantidad'];
            if ($pendiente < 1 || $lotes->sum('cantidad_disponible') < $pendiente) {
                throw ValidationException::withMessages(['carrito' => 'La sucursal ya no tiene todas las unidades de '.$item['producto']->nombre.'. Revisa tu carrito o elige otra sucursal.']);
            }
            foreach ($lotes as $lote) {
                if ($pendiente === 0) {
                    break;
                }
                $cantidad = min($pendiente, $lote->cantidad_disponible);
                $anterior = $lote->cantidad_disponible;
                $lote->update(['cantidad_disponible' => $anterior - $cantidad]);
                DetalleVenta::query()->create([
                    'venta_id' => $venta->id, 'producto_id' => $item['producto_id'], 'lote_id' => $lote->id,
                    'cantidad' => $cantidad, 'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => round($cantidad * $item['precio_unitario'], 2),
                ]);
                MovimientoInventario::query()->create([
                    'lote_id' => $lote->id, 'usuario_id' => $usuarioId, 'tipo_movimiento' => 'salida',
                    'cantidad' => $cantidad, 'cantidad_anterior' => $anterior, 'cantidad_nueva' => $anterior - $cantidad,
                    'motivo' => 'Compra online '.$venta->numero_venta, 'referencia_tipo' => 'venta', 'referencia_id' => $venta->id,
                ]);
                $pendiente -= $cantidad;
            }
        }
    }
}
