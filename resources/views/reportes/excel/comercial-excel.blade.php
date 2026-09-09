<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Rendimiento Comercial - MundiToys</title>
</head>
<body>
    <table style="font-family: Arial, sans-serif; font-size: 11pt; border-collapse: collapse;">
        <tr>
            <th colspan="7" style="font-size: 16pt; font-weight: bold; color: #8b5cf6; text-align: left; height: 35px;">
                MUNDITOYS - REPORTE DE RENDIMIENTO COMERCIAL, SUCURSALES Y LOGÍSTICA
            </th>
        </tr>
        <tr>
            <td colspan="7" style="font-size: 10pt; color: #64748b; font-style: italic;">
                Generado el: {{ now()->format('d/m/Y H:i:s') }} | Emisor: {{ auth()->user()?->name ?? 'Administrador' }}
            </td>
        </tr>
        <tr><td colspan="7"></td></tr>

        <!-- PARÁMETROS -->
        <tr style="background-color: #f1f5f9;">
            <th colspan="2" style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Periodo Evaluado:</th>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $filtros_aplicados['fecha_desde'] }} al {{ $filtros_aplicados['fecha_hasta'] }}</td>
            <th style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Sucursal:</th>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $filtros_aplicados['sucursal'] }}</td>
        </tr>
        <tr><td colspan="7"></td></tr>

        <!-- KPIS -->
        <tr>
            <th colspan="7" style="font-size: 12pt; font-weight: bold; background-color: #8b5cf6; color: #ffffff; padding: 6px; border: 1px solid #8b5cf6;">
                INDICADORES GENERALES DE GESTIÓN COMERCIAL
            </th>
        </tr>
        <tr style="background-color: #e2e8f0; font-weight: bold; text-align: center;">
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 8px;">Sucursal Líder</td>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 8px;">Asesor Destacado</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Total Envíos</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">% Éxito Entregas</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">CSAT (1 a 5★)</td>
        </tr>
        <tr style="text-align: center; font-size: 11pt;">
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold; color: #005ca8;">{{ $kpis['sucursal_top'] }}</td>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold; color: #16a34a;">{{ $kpis['vendedor_top'] }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">{{ number_format($kpis['total_entregas']) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold; color: #16a34a;">{{ $kpis['tasa_exito_entrega'] }}%</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold; color: #f59e0b;">{{ $kpis['calificacion_promedio'] }} ★</td>
        </tr>
        <tr><td colspan="7"></td></tr>

        <!-- SUCURSALES -->
        <tr>
            <th colspan="7" style="font-size: 12pt; font-weight: bold; background-color: #0f172a; color: #ffffff; padding: 6px; border: 1px solid #0f172a;">
                FACTURACIÓN POR SUCURSAL
            </th>
        </tr>
        <tr style="background-color: #334155; color: #ffffff; font-weight: bold; font-size: 9.5pt;">
            <th colspan="3" style="border: 1px solid #475569; padding: 6px;">Sucursal</th>
            <th style="border: 1px solid #475569; padding: 6px;">Ciudad</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: center;">Pedidos</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: right;">Facturación Total (Bs.)</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: right;">Ticket Promedio (Bs.)</th>
        </tr>
        @foreach ($rendimiento_sucursales as $rs)
            <tr>
                <td colspan="3" style="border: 1px solid #cbd5e1; padding: 5px; font-weight: bold;">{{ $rs['nombre'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px;">{{ $rs['ciudad'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: center;">{{ $rs['pedidos'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right; font-weight: bold; color: #005ca8;">{{ number_format($rs['facturacion'], 2) }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right;">{{ number_format($rs['ticket_promedio'], 2) }}</td>
            </tr>
        @endforeach
        <tr><td colspan="7"></td></tr>

        <!-- VENDEDORES -->
        <tr>
            <th colspan="7" style="font-size: 12pt; font-weight: bold; background-color: #16a34a; color: #ffffff; padding: 6px; border: 1px solid #16a34a;">
                RANKING DE ASESORES COMERCIALES
            </th>
        </tr>
        <tr style="background-color: #14532d; color: #ffffff; font-weight: bold; font-size: 9.5pt;">
            <th colspan="3" style="border: 1px solid #15803d; padding: 6px;">Asesor</th>
            <th style="border: 1px solid #15803d; padding: 6px;">Correo</th>
            <th style="border: 1px solid #15803d; padding: 6px; text-align: center;">Ventas Atendidas</th>
            <th style="border: 1px solid #15803d; padding: 6px; text-align: right;">Total Vendido (Bs.)</th>
            <th style="border: 1px solid #15803d; padding: 6px; text-align: right;">Ticket Promedio (Bs.)</th>
        </tr>
        @foreach ($ranking_vendedores as $rv)
            <tr>
                <td colspan="3" style="border: 1px solid #cbd5e1; padding: 5px; font-weight: bold;">{{ $rv['nombre'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px;">{{ $rv['email'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: center;">{{ $rv['ventas_conteo'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right; font-weight: bold; color: #16a34a;">{{ number_format($rv['total_vendido'], 2) }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right;">{{ number_format($rv['ticket_promedio'], 2) }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>

