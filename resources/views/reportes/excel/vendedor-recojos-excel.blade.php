<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Recojos en Sucursal - MundiToys</title>
</head>
<body>
    <table style="font-family: Arial, sans-serif; font-size: 11pt; border-collapse: collapse;">
        <tr>
            <th colspan="7" style="font-size: 16pt; font-weight: bold; color: #005ca8; text-align: left; height: 35px;">
                MUNDITOYS - REPORTE DE ENTREGAS Y RECOJOS EN SUCURSAL
            </th>
        </tr>
        <tr>
            <td colspan="7" style="font-size: 10pt; color: #64748b; font-style: italic;">
                Generado: {{ now()->format('d/m/Y H:i:s') }} | Sucursal: {{ $sucursal?->nombre ?? 'Sucursal' }} | Personal: {{ $vendedor?->name ?? 'Todo el personal' }}
            </td>
        </tr>
        <tr><td colspan="7"></td></tr>

        <!-- PARÁMETROS -->
        <tr style="background-color: #f1f5f9;">
            <th colspan="2" style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Rango de Fechas:</th>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $fecha_desde }} al {{ $fecha_hasta }}</td>
            <th style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Tasa Efectividad:</th>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $tasa_efectividad }}%</td>
        </tr>
        <tr><td colspan="7"></td></tr>

        <!-- KPIS -->
        <tr>
            <th colspan="7" style="font-size: 12pt; font-weight: bold; background-color: #005ca8; color: #ffffff; padding: 6px; border: 1px solid #005ca8;">
                RESUMEN DE ENTREGAS
            </th>
        </tr>
        <tr style="background-color: #e2e8f0; font-weight: bold; text-align: center;">
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Total Pedidos para Recojo</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Pedidos Entregados</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Pendientes en Tienda</td>
            <td colspan="4" style="border: 1px solid #cbd5e1; padding: 8px;">Monto Total Entregado (Bs.)</td>
        </tr>
        <tr style="text-align: center; font-weight: bold;">
            <td style="border: 1px solid #cbd5e1; padding: 8px;">{{ $total_pedidos }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; color: #15803d;">{{ $pedidos_entregados }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; color: #b45309;">{{ $pedidos_pendientes }}</td>
            <td colspan="4" style="border: 1px solid #cbd5e1; padding: 8px; color: #005ca8;">Bs. {{ number_format($total_monto_entregado, 2) }}</td>
        </tr>
        <tr><td colspan="7"></td></tr>

        <!-- DETALLE DE PEDIDOS -->
        <tr style="background-color: #005ca8; color: #ffffff; font-weight: bold;">
            <th style="border: 1px solid #005ca8; padding: 6px;">Nro. Venta</th>
            <th style="border: 1px solid #005ca8; padding: 6px;">Fecha Compra</th>
            <th style="border: 1px solid #005ca8; padding: 6px;">Comprador</th>
            <th style="border: 1px solid #005ca8; padding: 6px;">Estado Entrega</th>
            <th style="border: 1px solid #005ca8; padding: 6px;">Fecha / Hora Entrega</th>
            <th style="border: 1px solid #005ca8; padding: 6px;">Entregado Por</th>
            <th style="border: 1px solid #005ca8; padding: 6px;">Total Pagado (Bs.)</th>
        </tr>
        @forelse($pedidos as $p)
            <tr>
                <td style="border: 1px solid #e2e8f0; padding: 5px; font-weight: bold;">{{ $p->numero_venta }}</td>
                <td style="border: 1px solid #e2e8f0; padding: 5px;">{{ $p->fecha_venta?->format('d/m/Y H:i') ?? $p->created_at->format('d/m/Y H:i') }}</td>
                <td style="border: 1px solid #e2e8f0; padding: 5px;">{{ $p->cliente?->name ?? 'Cliente Web' }}</td>
                <td style="border: 1px solid #e2e8f0; padding: 5px;">{{ strtoupper($p->entrega?->estado ?? 'pendiente') }}</td>
                <td style="border: 1px solid #e2e8f0; padding: 5px;">
                    @if($p->entrega?->estado === 'entregado')
                        {{ $p->entrega?->fecha_programada?->format('d/m/Y') }} {{ $p->entrega?->hora_fin }}
                    @else
                        Pendiente
                    @endif
                </td>
                <td style="border: 1px solid #e2e8f0; padding: 5px;">{{ $p->entrega?->empleado?->name ?? '-' }}</td>
                <td style="border: 1px solid #e2e8f0; padding: 5px; text-align: right; font-weight: bold;">{{ number_format($p->total, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="border: 1px solid #e2e8f0; text-align: center; padding: 10px; color: #94a3b8;">No se registraron pedidos de recojo en el periodo.</td>
            </tr>
        @endforelse
    </table>
</body>
</html>

