<?php

namespace App\Actions\Inventario;

use App\Models\LoteInventario;
use App\Repositories\InventarioRepository;
use Illuminate\Support\Facades\DB;

class ActualizarLoteAction
{
    public function __construct(private InventarioRepository $repository) {}

    /**
     * @param array{
     *     costo_unitario: string,
     *     margen_porcentaje: string,
     *     precio_venta: string,
     *     cantidad_inicial: int,
     *     cantidad_disponible: int,
     *     proveedor_id?: ?int,
     *     fecha_ingreso: string,
     *     fecha_vencimiento?: ?string,
     *     motivo?: ?string
     * } $datos
     */
    public function ejecutar(LoteInventario $lote, array $datos, int $usuarioId): LoteInventario
    {
        return DB::transaction(function () use ($lote, $datos, $usuarioId): LoteInventario {
            $cantidadAnterior = $lote->cantidad_disponible;
            $cantidadNueva = $datos['cantidad_disponible'];
            $motivo = $datos['motivo'] ?? 'Corrección de datos del lote de inventario.';

            $loteActualizado = $this->repository->actualizarLote($lote, [
                'costo_unitario' => $datos['costo_unitario'],
                'margen_porcentaje' => $datos['margen_porcentaje'],
                'precio_venta' => $datos['precio_venta'],
                'cantidad_inicial' => $datos['cantidad_inicial'],
                'cantidad_disponible' => $cantidadNueva,
                'proveedor_id' => $datos['proveedor_id'] ?? null,
                'fecha_ingreso' => $datos['fecha_ingreso'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
            ]);

            $this->repository->registrarMovimiento([
                'lote_id' => $lote->id,
                'usuario_id' => $usuarioId,
                'tipo_movimiento' => 'ajuste',
                'cantidad' => abs($cantidadNueva - $cantidadAnterior),
                'cantidad_anterior' => $cantidadAnterior,
                'cantidad_nueva' => $cantidadNueva,
                'motivo' => $motivo,
                'referencia_tipo' => 'edicion_lote',
                'referencia_id' => $lote->id,
            ]);

            return $loteActualizado;
        });
    }
}
