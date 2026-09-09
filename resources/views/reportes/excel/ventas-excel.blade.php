<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Ventas y Rentabilidad - MundiToys</title>
</head>
<body>
    <table style="font-family: Arial, sans-serif; font-size: 11pt; border-collapse: collapse;">
        <!-- ENCABEZADO CORPORATIVO -->
        <tr>
            <th colspan="9" style="font-size: 16pt; font-weight: bold; color: #005ca8; text-align: left; height: 35px;">
                MUNDITOYS - REPORTE EJECUTIVO DE VENTAS Y RENTABILIDAD
            </th>
        </tr>
        <tr>
            <td colspan="9" style="font-size: 10pt; color: #64748b; font-style: italic;">
                Generado el: {{ now()->format('d/m/Y H:i:s') }} | Usuario: {{ auth()->user()?->name ?? 'Administrador' }}
            </td>
        </tr>
        <tr><td colspan="9"></td></tr>

        <!-- PARÁMETROS -->
        <tr style="background-color: #f1f5f9;">
            <th colspan="2" style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Rango de Fechas:</th>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $filtros_aplicados['fecha_desde'] }} al {{ $filtros_aplicados['fecha_hasta'] }}</td>
            <th colspan="2" style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Sucursal:</th>
            <td colspan="3" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $filtros_aplicados['sucursal'] }}</td>
        </tr>
        <tr style="background-color: #f1f5f9;">
            <th colspan="2" style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Canal Comercial:</th>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px;">{{ ucfirst($filtros_aplicados['tipo_venta']) }}</td>
            <th colspan="2" style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Categoría:</th>
            <td colspan="3" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $filtros_aplicados['categoria'] }}</td>
        </tr>
        <tr><td colspan="9"></td></tr>

        <!-- RESUMEN EJECUTIVO (KPIS) -->
        <tr>
            <th colspan="9" style="font-size: 12pt; font-weight: bold; background-color: #005ca8; color: #ffffff; padding: 6px; border: 1px solid #005ca8;">
                RESUMEN DE INDICADORES CLAVE DE DESEMPEÑO (KPIS)
            </th>
        </tr>
        <tr style="background-color: #e2e8f0; font-weight: bold; text-align: center;">
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Ventas Brutas</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Costo FIFO Lotes</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Ganancia Neta Real</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">% Margen</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Pedidos</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Ticket Promedio</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Unidades</td>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 8px;">Descuentos Aplicados</td>
        </tr>
        <tr style="text-align: center; font-size: 11pt;">
            <td style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold; color: #005ca8;">Bs. {{ number_format($kpis['total_ingresos'], 2) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Bs. {{ number_format($kpis['total_costo'], 2) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold; color: #16a34a;">Bs. {{ number_format($kpis['ganancia_bruta'], 2) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold;">{{ $kpis['margen_promedio'] }}%</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">{{ number_format($kpis['total_pedidos']) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Bs. {{ number_format($kpis['ticket_promedio'], 2) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">{{ number_format($kpis['unidades_vendidas']) }}</td>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 8px; color: #dc2626;">Bs. {{ number_format($kpis['descuentos_aplicados'], 2) }}</td>
        </tr>
        <tr><td colspan="9"></td></tr>

        <!-- TOP PRODUCTOS POR RENTABILIDAD -->
        <tr>
            <th colspan="9" style="font-size: 12pt; font-weight: bold; background-color: #0f172a; color: #ffffff; padding: 6px; border: 1px solid #0f172a;">
                TOP JUGUETES CON MAYOR GANANCIA REAL
            </th>
        </tr>
        <tr style="background-color: #334155; color: #ffffff; font-weight: bold; font-size: 9.5pt;">
            <th style="border: 1px solid #475569; padding: 6px; text-align: left;">ID</th>
            <th colspan="2" style="border: 1px solid #475569; padding: 6px; text-align: left;">Producto</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: left;">Categoría</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: center;">Unidades</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: right;">Ingreso (Bs.)</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: right;">Costo (Bs.)</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: right;">Ganancia (Bs.)</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: right;">% Rentabilidad</th>
        </tr>
        @foreach ($top_productos as $p)
            <tr>
                <td style="border: 1px solid #cbd5e1; padding: 5px;">#{{ $p['id'] }}</td>
                <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px; font-weight: bold;">{{ $p['nombre'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px;">{{ $p['categoria'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: center;">{{ $p['unidades'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right;">{{ number_format($p['ingresos'], 2) }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right;">{{ number_format($p['costos'], 2) }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right; font-weight: bold; color: #16a34a;">{{ number_format($p['ganancia'], 2) }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right; font-weight: bold;">{{ $p['margen_pct'] }}%</td>
            </tr>
        @endforeach
        <tr><td colspan="9"></td></tr>

        <!-- REGISTRO DE VENTAS -->
        <tr>
            <th colspan="9" style="font-size: 12pt; font-weight: bold; background-color: #005ca8; color: #ffffff; padding: 6px; border: 1px solid #005ca8;">
                DETALLE DE ÓRDENES DE VENTA AUDITADAS
            </th>
        </tr>
        <tr style="background-color: #1e293b; color: #ffffff; font-weight: bold; font-size: 9.5pt;">
            <th style="border: 1px solid #334155; padding: 6px;">N° Venta</th>
            <th style="border: 1px solid #334155; padding: 6px;">Fecha y Hora</th>
            <th colspan="2" style="border: 1px solid #334155; padding: 6px;">Cliente</th>
            <th style="border: 1px solid #334155; padding: 6px;">Canal</th>
            <th style="border: 1px solid #334155; padding: 6px;">Método Pago</th>
            <th style="border: 1px solid #334155; padding: 6px; text-align: right;">Subtotal (Bs.)</th>
            <th style="border: 1px solid #334155; padding: 6px; text-align: right;">Descuento (Bs.)</th>
            <th style="border: 1px solid #334155; padding: 6px; text-align: right;">Total (Bs.)</th>
        </tr>
        @foreach ($ventas_registros as $v)
            <tr>
                <td style="border: 1px solid #cbd5e1; padding: 5px; font-weight: bold;">{{ $v->numero_venta }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px;">{{ $v->fecha_venta ? $v->fecha_venta->format('d/m/Y H:i') : $v->created_at->format('d/m/Y H:i') }}</td>
                <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $v->cliente?->name ?? 'Cliente Mostrador' }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: center;">{{ ucfirst($v->tipo_venta) }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px;">{{ $v->pago ? ucfirst($v->pago->metodo_pago) : 'N/A' }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right;">{{ number_format($v->subtotal, 2) }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right;">{{ number_format($v->descuento, 2) }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right; font-weight: bold; color: #005ca8;">{{ number_format($v->total, 2) }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>

