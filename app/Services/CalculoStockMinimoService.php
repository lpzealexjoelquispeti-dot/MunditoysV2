<?php

namespace App\Services;

use App\Models\DetalleVenta;
use App\Models\ProductoProveedor;

class CalculoStockMinimoService
{
    public const NIVEL_SERVICIO_Z = 1.65; // 95% de nivel de servicio (Z de Gauss)

    public const DIAS_LEAD_TIME_DEFECTO = 7;

    /**
     * Calcula el stock mínimo óptimo utilizando Investigación de Operaciones (IO).
     *
     * @return array{
     *     stock_minimo: int,
     *     metodo: string,
     *     lead_time_dias: int,
     *     demanda_diaria_promedio: float,
     *     desviacion_estandar: float,
     *     explicacion: string
     * }
     */
    public function calcular(int $productoId, int $cantidadLote, ?int $proveedorId = null): array
    {
        $leadTime = $this->obtenerLeadTime($productoId, $proveedorId);
        $historialVentas = $this->obtenerHistorialVentas($productoId);

        if (count($historialVentas) >= 3) {
            return $this->calcularProbabilistico($historialVentas, $leadTime, $cantidadLote);
        }

        return $this->calcularHeuristico($cantidadLote, $leadTime);
    }

    /**
     * Obtiene el tiempo de entrega del proveedor en días (Lead Time).
     */
    public function obtenerLeadTime(int $productoId, ?int $proveedorId = null): int
    {
        $dias = null;
        if ($proveedorId !== null && $proveedorId > 0) {
            $dias = ProductoProveedor::query()
                ->where('producto_id', $productoId)
                ->where('proveedor_id', $proveedorId)
                ->value('tiempo_entrega_dias');
        }

        if ($dias === null || $dias <= 0) {
            $dias = (int) ProductoProveedor::query()
                ->where('producto_id', $productoId)
                ->whereNotNull('tiempo_entrega_dias')
                ->where('tiempo_entrega_dias', '>', 0)
                ->avg('tiempo_entrega_dias');
        }

        return $dias > 0 ? (int) $dias : self::DIAS_LEAD_TIME_DEFECTO;
    }

    /**
     * @return array<string, int> Mapa de 'YYYY-MM-DD' => cantidad_vendida
     */
    private function obtenerHistorialVentas(int $productoId): array
    {
        return DetalleVenta::query()
            ->join('ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->where('detalle_ventas.producto_id', $productoId)
            ->where(function ($query): void {
                $query->where('ventas.fecha_venta', '>=', now()->subDays(90))
                    ->orWhere('ventas.created_at', '>=', now()->subDays(90));
            })
            ->selectRaw('COALESCE(DATE(ventas.fecha_venta), DATE(ventas.created_at)) as fecha, SUM(detalle_ventas.cantidad) as total_dia')
            ->groupBy('fecha')
            ->pluck('total_dia', 'fecha')
            ->filter(fn ($val, $key) => $key !== null && $key !== '')
            ->map(fn ($val) => (int) $val)
            ->all();
    }

    /**
     * Modelo Probabilístico de IO: Stock de Seguridad = Z * σ_d * sqrt(L).
     *
     * @param  array<string, int>  $ventasPorDia
     * @return array{
     *     stock_minimo: int,
     *     metodo: string,
     *     lead_time_dias: int,
     *     demanda_diaria_promedio: float,
     *     desviacion_estandar: float,
     *     explicacion: string
     * }
     */
    private function calcularProbabilistico(array $ventasPorDia, int $leadTime, int $cantidadLote): array
    {
        $cantidades = array_values($ventasPorDia);
        $totalDias = count($cantidades);
        $suma = array_sum($cantidades);
        $media = $suma / max($totalDias, 1);

        // Varianza y desviación estándar muestral de la demanda diaria
        $sumaCuadrados = 0.0;
        foreach ($cantidades as $cant) {
            $sumaCuadrados += pow($cant - $media, 2);
        }
        $desviacion = sqrt($sumaCuadrados / max($totalDias - 1, 1));

        // Stock de seguridad: Z * sigma_d * sqrt(L)
        $stockSeguridad = (int) ceil(self::NIVEL_SERVICIO_Z * $desviacion * sqrt($leadTime));

        // Punto de reorden / stock mínimo: d * L + SS
        $stockMinimo = (int) ceil(($media * $leadTime) + $stockSeguridad);

        // Limitar razonablemente a no superar el tamaño del lote si el lote es pequeño
        $stockMinimo = max(1, min($stockMinimo, max(1, $cantidadLote)));

        return [
            'stock_minimo' => $stockMinimo,
            'metodo' => 'probabilistico',
            'lead_time_dias' => $leadTime,
            'demanda_diaria_promedio' => round($media, 2),
            'desviacion_estandar' => round($desviacion, 2),
            'explicacion' => sprintf(
                'Modelo probabilístico de IO: SS = Z(1.65) × σ(%.2f) × √L(%d días) + demanda media (%.2f u/día).',
                $desviacion,
                $leadTime,
                $media
            ),
        ];
    }

    /**
     * Modelo Heurístico/Determinístico de IO para productos sin histórico suficiente.
     *
     * @return array{
     *     stock_minimo: int,
     *     metodo: string,
     *     lead_time_dias: int,
     *     demanda_diaria_promedio: float,
     *     desviacion_estandar: float,
     *     explicacion: string
     * }
     */
    private function calcularHeuristico(int $cantidadLote, int $leadTime): array
    {
        // Asumiendo un ciclo de consumo mensual estimado (30 días)
        $demandaDiariaEstimada = max(0.1, $cantidadLote / 30.0);
        $consumoReposicion = $demandaDiariaEstimada * $leadTime;
        $amortiguacionSeguridad = ceil($cantidadLote * 0.15); // 15% del lote como margen de seguridad

        $stockMinimo = (int) ceil(max($consumoReposicion, $amortiguacionSeguridad));
        $stockMinimo = max(1, min($stockMinimo, max(1, $cantidadLote)));

        return [
            'stock_minimo' => $stockMinimo,
            'metodo' => 'heuristico_io',
            'lead_time_dias' => $leadTime,
            'demanda_diaria_promedio' => round($demandaDiariaEstimada, 2),
            'desviacion_estandar' => 0.0,
            'explicacion' => sprintf(
                'Modelo determinístico de IO: 15%% de seguridad sobre lote (%d u) amortiguando reposición de %d días.',
                $cantidadLote,
                $leadTime
            ),
        ];
    }
}
