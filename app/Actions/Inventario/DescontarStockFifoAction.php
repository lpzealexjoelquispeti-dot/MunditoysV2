<?php

namespace App\Actions\Inventario;

use App\Models\Inventario;
use App\Repositories\InventarioRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DescontarStockFifoAction
{
    public function __construct(private InventarioRepository $repository) {}

    /**
     * @return array<int, array{lote_id: int, descontado: int, restante_lote: int}>
     */
    public function ejecutar(Inventario $inventario, int $cantidad, string $motivo, int $usuarioId): array
    {
        return DB::transaction(function () use ($inventario, $cantidad, $motivo, $usuarioId): array {
            $lotes = $this->repository->lotesFifo($inventario->id);
            $totalDisponible = (int) $lotes->sum('cantidad_disponible');

            if ($cantidad > $totalDisponible) {
                throw ValidationException::withMessages([
                    'cantidadDescuento' => "No hay suficientes existencias disponibles ({$totalDisponible} disponibles) para descontar {$cantidad} unidades.",
                ]);
            }

            $porDescontar = $cantidad;
            $afectados = [];

            foreach ($lotes as $lote) {
                if ($porDescontar <= 0) {
                    break;
                }

                $disponible = (int) $lote->cantidad_disponible;
                if ($disponible <= 0) {
                    continue;
                }

                $descontarDeLote = min($disponible, $porDescontar);
                $nuevaCantidad = $disponible - $descontarDeLote;

                $this->repository->actualizarLote($lote, [
                    'cantidad_disponible' => $nuevaCantidad,
                ]);

                $this->repository->registrarMovimiento([
                    'lote_id' => $lote->id,
                    'usuario_id' => $usuarioId,
                    'tipo_movimiento' => 'salida',
                    'cantidad' => $descontarDeLote,
                    'cantidad_anterior' => $disponible,
                    'cantidad_nueva' => $nuevaCantidad,
                    'motivo' => "Salida FIFO ({$motivo}): Se descontaron {$descontarDeLote} unidades.",
                    'referencia_tipo' => 'descuento_fifo',
                    'referencia_id' => $inventario->id,
                ]);

                $afectados[] = [
                    'lote_id' => $lote->id,
                    'descontado' => $descontarDeLote,
                    'restante_lote' => $nuevaCantidad,
                ];

                $porDescontar -= $descontarDeLote;
            }

            return $afectados;
        });
    }
}
