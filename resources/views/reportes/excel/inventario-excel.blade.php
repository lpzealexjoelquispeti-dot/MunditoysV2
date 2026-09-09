<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Inventario y Valuación - MundiToys</title>
</head>
<body>
    <table style="font-family: Arial, sans-serif; font-size: 11pt; border-collapse: collapse;">
        <tr>
            <th colspan="9" style="font-size: 16pt; font-weight: bold; color: #ea580c; text-align: left; height: 35px;">
                MUNDITOYS - REPORTE DE INVENTARIO, VALUACIÓN FIFO Y ROTACIÓN
            </th>
        </tr>
        <tr>
            <td colspan="9" style="font-size: 10pt; color: #64748b; font-style: italic;">
                Generado el: {{ now()->format('d/m/Y H:i:s') }} | Emisor: {{ auth()->user()?->name ?? 'Administrador' }}
            </td>
        </tr>
        <tr><td colspan="9"></td></tr>

        <!-- PARÁMETROS -->
        <tr style="background-color: #f1f5f9;">
            <th colspan="2" style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Sucursal:</th>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $filtros_aplicados['sucursal'] }}</td>
            <th colspan="2" style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Categoría:</th>
            <td colspan="3" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $filtros_aplicados['categoria'] }}</td>
        </tr>
        <tr><td colspan="9"></td></tr>

        <!-- KPIS -->
        <tr>
            <th colspan="9" style="font-size: 12pt; font-weight: bold; background-color: #ea580c; color: #ffffff; padding: 6px; border: 1px solid #ea580c;">
                VALORIZACIÓN PATRIMONIAL Y CONTROL DE EXISTENCIAS
            </th>
        </tr>
        <tr style="background-color: #e2e8f0; font-weight: bold; text-align: center;">
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Unidades Físicas</td>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 8px;">Valuación Costo FIFO</td>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 8px;">Valuación Venta Catálogo</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Margen Potencial</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">% Retorno</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Agotados</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Bajo Mínimo</td>
        </tr>
        <tr style="text-align: center; font-size: 11pt;">
            <td style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold;">{{ number_format($kpis['unidades_stock_total']) }}</td>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 8px;">Bs. {{ number_format($kpis['valor_costo_total'], 2) }}</td>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold; color: #005ca8;">Bs. {{ number_format($kpis['valor_venta_total'], 2) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold; color: #16a34a;">Bs. {{ number_format($kpis['margen_potencial'], 2) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold;">{{ $kpis['margen_porcentaje'] }}%</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; color: #dc2626; font-weight: bold;">{{ $kpis['productos_agotados'] }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; color: #d97706; font-weight: bold;">{{ $kpis['productos_bajo_minimo'] }}</td>
        </tr>
        <tr><td colspan="9"></td></tr>

        <!-- DETALLE INVENTARIO -->
        <tr>
            <th colspan="9" style="font-size: 12pt; font-weight: bold; background-color: #1e293b; color: #ffffff; padding: 6px; border: 1px solid #1e293b;">
                DETALLE DE ARTÍCULOS EN INVENTARIO
            </th>
        </tr>
        <tr style="background-color: #334155; color: #ffffff; font-weight: bold; font-size: 9.5pt;">
            <th style="border: 1px solid #475569; padding: 6px;">Código</th>
            <th colspan="2" style="border: 1px solid #475569; padding: 6px;">Producto</th>
            <th style="border: 1px solid #475569; padding: 6px;">Categoría</th>
            <th style="border: 1px solid #475569; padding: 6px;">Sucursal - Almacén</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: center;">Stock</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: center;">Mínimo</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: right;">Val. Costo (Bs.)</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: right;">Val. Venta (Bs.)</th>
        </tr>
        @foreach ($inventario_registros as $inv)
            <tr>
                <td style="border: 1px solid #cbd5e1; padding: 5px;">{{ $inv['codigo'] }}</td>
                <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px; font-weight: bold;">{{ $inv['producto'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px;">{{ $inv['categoria'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px;">{{ $inv['sucursal'] }} - {{ $inv['almacen'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: center; font-weight: bold;">{{ $inv['stock_actual'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: center;">{{ $inv['stock_minimo'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right;">{{ number_format($inv['valor_costo'], 2) }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right; font-weight: bold; color: #005ca8;">{{ number_format($inv['valor_venta'], 2) }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>

