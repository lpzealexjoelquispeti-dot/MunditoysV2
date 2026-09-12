<?php

namespace App\Actions\Inventario;

use App\Models\Inventario;
use App\Models\LoteInventario;
use App\Repositories\InventarioRepository;
use Illuminate\Support\Facades\DB;

class AjustarPrecioLotesAnterioresAction
{
    public function __construct(private InventarioRepository $repository) {}

    /**
     * Ajusta el precio de venta de lotes anteriores con stock para proteger el capital de reposición.
     *
     * @return array<int, array{lote_id: int, precio_anterior: string, nuevo_precio: string}>
     */
    public function ejecutar(int $inventarioId, string $nuevoPrecioVenta, int $usuarioId, ?int $ignorarLoteId = null): array
    {
        return DB::transaction(function () use ($inventarioId, $nuevoPrecioVenta, $usuarioId, $ignorarLoteId): array {
            $lotes = $this->repository->lotesActivosConStock($inventarioId)
                ->when($ignorarLoteId, fn ($q) => $q->where('id', '!=', $ignorarLoteId))
                ->filter(fn (LoteInventario $lote) => (float) $lote->precio_venta < (float) $nuevoPrecioVenta);

            $ajustados = [];

            foreach ($lotes as $lote) {
                $precioAnterior = (string) $lote->precio_venta;

                $this->repository->actualizarLote($lote, [
                    'precio_venta' => $nuevoPrecioVenta,
                ]);

                $this->repository->registrarMovimiento([
                    'lote_id' => $lote->id,
                    'usuario_id' => $usuarioId,
                    'tipo_movimiento' => 'ajuste_precio',
                    'cantidad' => $lote->cantidad_disponible,
                    'cantidad_anterior' => $lote->cantidad_disponible,
                    'cantidad_nueva' => $lote->cantidad_disponible,
                    'motivo' => "Ajuste de precio por reposición: de Bs {$precioAnterior} a Bs {$nuevoPrecioVenta} (protección de capital).",
                    'referencia_tipo' => 'ajuste_reposicion',
                    'referencia_id' => $inventarioId,
                ]);

                $ajustados[] = [
                    'lote_id' => $lote->id,
                    'precio_anterior' => $precioAnterior,
                    'nuevo_precio' => $nuevoPrecioVenta,
                ];
            }

            $inventario = Inventario::with('producto')->find($inventarioId);
            if ($inventario?->producto) {
                $inventario->producto->update([
                    'precio_venta' => $nuevoPrecioVenta,
                ]);
            }

            return $ajustados;
        });
    }
}
