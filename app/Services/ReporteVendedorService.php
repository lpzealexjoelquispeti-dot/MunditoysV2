<?php

namespace App\Services;

use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ReporteVendedorService
{
    /**
     * Genera las métricas y datos para el reporte de Ventas Físicas (Mostrador / POS).
     *
     * @return array<string, mixed>
     */
    public function obtenerReporteVentasFisicas(
        int $vendedorId,
        int $sucursalId,
        string $fechaDesde,
        string $fechaHasta
    ): array {
        $desdeCarbon = Carbon::parse($fechaDesde)->startOfDay();
        $hastaCarbon = Carbon::parse($fechaHasta)->endOfDay();

        $vendedor = User::query()->find($vendedorId);
        $sucursal = Sucursal::query()->find($sucursalId);

        $ventas = Venta::query()
            ->with([
                'cliente',
                'pago',
                'detalles.producto.categoria',
                'detalles.lote.inventario.almacen',
            ])
            ->where('tipo_venta', 'presencial')
            ->where('usuario_vendedor_id', $vendedorId)
            ->whereBetween('fecha_venta', [$desdeCarbon, $hastaCarbon])
            ->where('estado', '!=', 'cancelada')
            ->orderBy('fecha_venta', 'desc')
            ->get();

        $totalIngresos = 0.0;
        $unidadesVendidas = 0;
        $desglosePagos = [
            'efectivo' => ['nombre' => 'Efectivo', 'monto' => 0.0, 'conteo' => 0, 'porcentaje' => 0],
            'qr' => ['nombre' => 'Transferencia QR', 'monto' => 0.0, 'conteo' => 0, 'porcentaje' => 0],
            'tarjeta' => ['nombre' => 'Tarjeta POS', 'monto' => 0.0, 'conteo' => 0, 'porcentaje' => 0],
            'stripe' => ['nombre' => 'Pasarela Stripe', 'monto' => 0.0, 'conteo' => 0, 'porcentaje' => 0],
            'otro' => ['nombre' => 'Otros Métodos', 'monto' => 0.0, 'conteo' => 0, 'porcentaje' => 0],
        ];
        $ventasPorDia = [];
        $rankingProductos = [];

        foreach ($ventas as $venta) {
            $montoVenta = (float) $venta->total;
            $totalIngresos += $montoVenta;

            // Desglose por método de pago
            $metodo = $venta->pago?->metodo_pago ?? 'otro';
            if (! isset($desglosePagos[$metodo])) {
                $metodo = 'otro';
            }
            $desglosePagos[$metodo]['monto'] += $montoVenta;
            $desglosePagos[$metodo]['conteo']++;

            // Agrupación por día para gráfico SVG
            $diaClave = $venta->fecha_venta ? $venta->fecha_venta->format('Y-m-d') : $venta->created_at->format('Y-m-d');
            $diaEtiqueta = $venta->fecha_venta ? $venta->fecha_venta->format('d/m') : $venta->created_at->format('d/m');
            if (! isset($ventasPorDia[$diaClave])) {
                $ventasPorDia[$diaClave] = [
                    'fecha' => $diaClave,
                    'etiqueta' => $diaEtiqueta,
                    'monto' => 0.0,
                    'transacciones' => 0,
                ];
            }
            $ventasPorDia[$diaClave]['monto'] += $montoVenta;
            $ventasPorDia[$diaClave]['transacciones']++;

            // Desglose de juguetes
            foreach ($venta->detalles as $detalle) {
                $cant = (int) $detalle->cantidad;
                $unidadesVendidas += $cant;
                $prodId = $detalle->producto_id;
                if (! isset($rankingProductos[$prodId])) {
                    $rankingProductos[$prodId] = [
                        'id' => $prodId,
                        'nombre' => $detalle->producto?->nombre ?? "Producto #{$prodId}",
                        'codigo' => $detalle->producto?->codigo_producto ?? "JUG-{$prodId}",
                        'categoria' => $detalle->producto?->categoria?->nombre ?? 'General',
                        'unidades' => 0,
                        'total_generado' => 0.0,
                    ];
                }
                $rankingProductos[$prodId]['unidades'] += $cant;
                $rankingProductos[$prodId]['total_generado'] += (float) $detalle->subtotal;
            }
        }

        // Calcular porcentajes de pago
        foreach ($desglosePagos as $clave => $datos) {
            $desglosePagos[$clave]['porcentaje'] = $totalIngresos > 0
                ? round(($datos['monto'] / $totalIngresos) * 100, 1)
                : 0;
        }

        // Ordenar ranking de productos por unidades
        uasort($rankingProductos, fn ($a, $b) => $b['unidades'] <=> $a['unidades']);
        $topProductos = array_slice(array_values($rankingProductos), 0, 5);

        // Ordenar días cronológicamente
        ksort($ventasPorDia);

        $totalVentas = $ventas->count();
        $ticketPromedio = $totalVentas > 0 ? round($totalIngresos / $totalVentas, 2) : 0.0;

        return [
            'vendedor' => $vendedor,
            'sucursal' => $sucursal,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'total_ingresos' => $totalIngresos,
            'total_ventas' => $totalVentas,
            'ticket_promedio' => $ticketPromedio,
            'unidades_vendidas' => $unidadesVendidas,
            'desglose_pagos' => $desglosePagos,
            'ventas_por_dia' => array_values($ventasPorDia),
            'top_productos' => $topProductos,
            'ventas' => $ventas,
        ];
    }

    /**
     * Genera las métricas y datos para el reporte de Ventas de Recojo en Sucursal (Entregas Online).
     *
     * @return array<string, mixed>
     */
    public function obtenerReporteRecojosSucursal(
        int $sucursalId,
        ?int $vendedorId,
        string $fechaDesde,
        string $fechaHasta
    ): array {
        $desdeCarbon = Carbon::parse($fechaDesde)->startOfDay();
        $hastaCarbon = Carbon::parse($fechaHasta)->endOfDay();

        $vendedor = $vendedorId ? User::query()->find($vendedorId) : null;
        $sucursal = Sucursal::query()->find($sucursalId);

        $pedidos = Venta::query()
            ->with([
                'cliente',
                'pago',
                'entrega.sucursal',
                'entrega.empleado',
                'detalles.producto',
            ])
            ->where('tipo_entrega', 'recojo_tienda')
            ->whereHas('entrega', fn (Builder $q) => $q->where('sucursal_origen_id', $sucursalId))
            ->whereBetween('fecha_venta', [$desdeCarbon, $hastaCarbon])
            ->where('estado', '!=', 'cancelada')
            ->orderBy('fecha_venta', 'desc')
            ->get();

        $totalPedidos = $pedidos->count();
        $pedidosEntregados = 0;
        $pedidosPendientes = 0;
        $entregadosPorVendedor = 0;
        $totalMontoEntregado = 0.0;
        $recojosPorDia = [];

        foreach ($pedidos as $pedido) {
            $estadoEntrega = $pedido->entrega?->estado ?? 'pendiente';
            $esEntregado = ($estadoEntrega === 'entregado');
            $monto = (float) $pedido->total;

            if ($esEntregado) {
                $pedidosEntregados++;
                $totalMontoEntregado += $monto;

                if ($vendedorId && $pedido->entrega?->empleado_id === $vendedorId) {
                    $entregadosPorVendedor++;
                }
            } else {
                $pedidosPendientes++;
            }

            // Agrupación de actividad por día
            $diaClave = $pedido->fecha_venta ? $pedido->fecha_venta->format('Y-m-d') : $pedido->created_at->format('Y-m-d');
            $diaEtiqueta = $pedido->fecha_venta ? $pedido->fecha_venta->format('d/m') : $pedido->created_at->format('d/m');
            if (! isset($recojosPorDia[$diaClave])) {
                $recojosPorDia[$diaClave] = [
                    'fecha' => $diaClave,
                    'etiqueta' => $diaEtiqueta,
                    'total' => 0,
                    'entregados' => 0,
                    'pendientes' => 0,
                ];
            }
            $recojosPorDia[$diaClave]['total']++;
            if ($esEntregado) {
                $recojosPorDia[$diaClave]['entregados']++;
            } else {
                $recojosPorDia[$diaClave]['pendientes']++;
            }
        }

        ksort($recojosPorDia);

        $tasaEfectividad = $totalPedidos > 0 ? round(($pedidosEntregados / $totalPedidos) * 100, 1) : 0.0;

        return [
            'vendedor' => $vendedor,
            'sucursal' => $sucursal,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'total_pedidos' => $totalPedidos,
            'pedidos_entregados' => $pedidosEntregados,
            'pedidos_pendientes' => $pedidosPendientes,
            'entregados_por_vendedor' => $entregadosPorVendedor,
            'total_monto_entregado' => $totalMontoEntregado,
            'tasa_efectividad' => $tasaEfectividad,
            'recojos_por_dia' => array_values($recojosPorDia),
            'pedidos' => $pedidos,
        ];
    }
}
