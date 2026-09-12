<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Ventas Físicas POS - MundiToys</title>
</head>
<body>
    <table style="font-family: Arial, sans-serif; font-size: 11pt; border-collapse: collapse;">
        <tr>
            <th colspan="7" style="font-size: 16pt; font-weight: bold; color: #005ca8; text-align: left; height: 35px;">
                MUNDITOYS - REPORTE DE VENTAS FÍSICAS DE MOSTRADOR (POS)
            </th>
        </tr>
        <tr>
            <td colspan="7" style="font-size: 10pt; color: #64748b; font-style: italic;">
                Generado: {{ now()->format('d/m/Y H:i:s') }} | Vendedor: {{ $vendedor?->name ?? 'Vendedor' }} | Sucursal: {{ $sucursal?->nombre ?? 'Asignada' }}
            </td>
        </tr>
        <tr><td colspan="7"></td></tr>

        <!-- PARÁMETROS -->
        <tr style="background-color: #f1f5f9;">
            <th colspan="2" style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Rango de Fechas:</th>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $fecha_desde }} al {{ $fecha_hasta }}</td>
            <th style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Canal:</th>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px;">Mostrador Presencial (POS)</td>
        </tr>
        <tr><td colspan="7"></td></tr>

        <!-- KPIS -->
        <tr>
            <th colspan="7" style="font-size: 12pt; font-weight: bold; background-color: #005ca8; color: #ffffff; padding: 6px; border: 1px solid #005ca8;">
                RESUMEN DE INDICADORES (KPIS)
            </th>
        </tr>
        <tr style="background-color: #e2e8f0; font-weight: bold; text-align: center;">
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Total Recaudado (Bs.)</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Tickets Emitidos</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Ticket Promedio (Bs.)</td>
            <td colspan="4" style="border: 1px solid #cbd5e1; padding: 8px;">Juguetes Despachados</td>
        </tr>
        <tr style="text-align: center; font-weight: bold;">
            <td style="border: 1px solid #cbd5e1; padding: 8px; color: #005ca8;">Bs. {{ number_format($total_ingresos, 2) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">{{ $total_ventas }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Bs. {{ number_format($ticket_promedio, 2) }}</td>
            <td colspan="4" style="border: 1px solid #cbd5e1; padding: 8px; color: #ea580c;">{{ $unidades_vendidas }}</td>
        </tr>
        <tr><td colspan="7"></td></tr>

        <!-- DETALLE DE VENTAS -->
        <tr style="background-color: #005ca8; color: #ffffff; font-weight: bold;">
            <th style="border: 1px solid #005ca8; padding: 6px;">Fecha / Hora</th>
            <th style="border: 1px solid #005ca8; padding: 6px;">Nro. Venta</th>
            <th style="border: 1px solid #005ca8; padding: 6px;">Cliente</th>
            <th style="border: 1px solid #005ca8; padding: 6px;">CI / NIT</th>
            <th style="border: 1px solid #005ca8; padding: 6px;">Método de Pago</th>
            <th style="border: 1px solid #005ca8; padding: 6px;">Artículos</th>
            <th style="border: 1px solid #005ca8; padding: 6px;">Total Pagado (Bs.)</th>
        </tr>
        @forelse($ventas as $v)
            <tr>
                <td style="border: 1px solid #e2e8f0; padding: 5px;">{{ $v->fecha_venta?->format('d/m/Y H:i') ?? $v->created_at->format('d/m/Y H:i') }}</td>
                <td style="border: 1px solid #e2e8f0; padding: 5px; font-weight: bold;">{{ $v->numero_venta }}</td>
                <td style="border: 1px solid #e2e8f0; padding: 5px;">{{ $v->cliente?->name ?? 'Consumidor Final' }}</td>
                <td style="border: 1px solid #e2e8f0; padding: 5px;">{{ $v->cliente?->ci_nit ?? '0' }}</td>
                <td style="border: 1px solid #e2e8f0; padding: 5px;">{{ ucfirst($v->pago?->metodo_pago ?? 'Efectivo') }}</td>
                <td style="border: 1px solid #e2e8f0; padding: 5px; text-align: center;">{{ $v->detalles->sum('cantidad') }}</td>
                <td style="border: 1px solid #e2e8f0; padding: 5px; text-align: right; font-weight: bold;">{{ number_format($v->total, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="border: 1px solid #e2e8f0; text-align: center; padding: 10px; color: #94a3b8;">No se registraron ventas en el periodo.</td>
            </tr>
        @endforelse
    </table>
</body>
</html>

