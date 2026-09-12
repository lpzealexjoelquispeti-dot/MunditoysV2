<?php

namespace App\Actions\Inventario;

use App\Models\LoteInventario;
use App\Repositories\InventarioRepository;

class RegistrarLoteAction
{
    public function __construct(private InventarioRepository $repository) {}

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos, int $usuarioId): LoteInventario
    {
        $lote = $this->repository->crearLote($datos);
        $this->repository->registrarMovimiento([
            'lote_id' => $lote->id, 'usuario_id' => $usuarioId, 'tipo_movimiento' => 'entrada',
            'cantidad' => $lote->cantidad_inicial, 'cantidad_anterior' => 0,
            'cantidad_nueva' => $lote->cantidad_inicial, 'motivo' => 'Ingreso de nuevo lote de inventario.',
            'referencia_tipo' => 'ingreso_lote', 'referencia_id' => $lote->id,
        ]);

        if (! empty($datos['precio_venta'])) {
            $lote->loadMissing('inventario.producto');
            if ($lote->inventario?->producto) {
                $lote->inventario->producto->update([
                    'precio_venta' => $datos['precio_venta'],
                ]);
            }
        }

        return $lote;
    }
}
