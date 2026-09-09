<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Interacciones y Topics IA - MundiToys</title>
</head>
<body>
    <table style="font-family: Arial, sans-serif; font-size: 11pt; border-collapse: collapse;">
        <tr>
            <th colspan="7" style="font-size: 16pt; font-weight: bold; color: #0284c7; text-align: left; height: 35px;">
                MUNDITOYS - REPORTE DE INTERACCIONES, MODELOS 3D Y TOPICS IA
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
            <th colspan="2" style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Rango Analizado:</th>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $filtros_aplicados['fecha_desde'] }} al {{ $filtros_aplicados['fecha_hasta'] }}</td>
            <th style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Categoría:</th>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $filtros_aplicados['categoria'] }}</td>
        </tr>
        <tr><td colspan="7"></td></tr>

        <!-- KPIS -->
        <tr>
            <th colspan="7" style="font-size: 12pt; font-weight: bold; background-color: #0284c7; color: #ffffff; padding: 6px; border: 1px solid #0284c7;">
                MÉTRICAS CLAVE DE INTERACCIÓN Y CONVERSIÓN
            </th>
        </tr>
        <tr style="background-color: #e2e8f0; font-weight: bold; text-align: center;">
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Total Eventos</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Vistas Catálogo</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Visor 3D</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Añadidos Carrito</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">% Conversión</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Sesiones IA</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Mensajes IA</td>
        </tr>
        <tr style="text-align: center; font-size: 11pt;">
            <td style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold;">{{ number_format($kpis['total_interacciones']) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; color: #0284c7; font-weight: bold;">{{ number_format($kpis['total_vistas_producto']) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; color: #8b5cf6; font-weight: bold;">{{ number_format($kpis['total_interacciones_3d']) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; color: #16a34a; font-weight: bold;">{{ number_format($kpis['total_agregados_carrito']) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold; color: #ea580c;">{{ $kpis['tasa_conversion_visual'] }}%</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">{{ number_format($kpis['total_conversaciones_ia']) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">{{ number_format($kpis['total_mensajes_ia']) }}</td>
        </tr>
        <tr><td colspan="7"></td></tr>

        <!-- TOP JUGUETES -->
        <tr>
            <th colspan="7" style="font-size: 12pt; font-weight: bold; background-color: #0f172a; color: #ffffff; padding: 6px; border: 1px solid #0f172a;">
                TOP JUGUETES CON MAYOR INTERACCIÓN Y VISTAS 3D
            </th>
        </tr>
        <tr style="background-color: #334155; color: #ffffff; font-weight: bold; font-size: 9.5pt;">
            <th style="border: 1px solid #475569; padding: 6px; text-align: left;">ID</th>
            <th colspan="2" style="border: 1px solid #475569; padding: 6px; text-align: left;">Juguete</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: left;">Categoría</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: center;">Vistas</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: center;">Interacciones 3D</th>
            <th style="border: 1px solid #475569; padding: 6px; text-align: center;">Añadidos Carrito</th>
        </tr>
        @foreach ($top_juguetes as $tj)
            <tr>
                <td style="border: 1px solid #cbd5e1; padding: 5px;">#{{ $tj['id'] }}</td>
                <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px; font-weight: bold;">{{ $tj['nombre'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px;">{{ $tj['categoria'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: center;">{{ $tj['vistas'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: center; font-weight: bold; color: #8b5cf6;">{{ $tj['interacciones_3d'] }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: center; font-weight: bold; color: #16a34a;">{{ $tj['carritos'] }}</td>
            </tr>
        @endforeach
        <tr><td colspan="7"></td></tr>

        <!-- TOPICS IA Y BÚSQUEDAS -->
        <tr>
            <th colspan="4" style="font-size: 11pt; font-weight: bold; background-color: #6366f1; color: #ffffff; padding: 6px; border: 1px solid #6366f1;">
                TOPICS RECURRENTES DEL ASISTENTE IA
            </th>
            <th colspan="3" style="font-size: 11pt; font-weight: bold; background-color: #0284c7; color: #ffffff; padding: 6px; border: 1px solid #0284c7;">
                BÚSQUEDAS FRECUENTES EN CATÁLOGO
            </th>
        </tr>
        @php
            $maxRows = max(count($topicos_ia), count($top_busquedas));
            $topicsKeys = array_keys($topicos_ia);
            $topicsVals = array_values($topicos_ia);
            $searchKeys = array_keys($top_busquedas);
            $searchVals = array_values($top_busquedas);
        @endphp
        @for ($i = 0; $i < $maxRows; $i++)
            <tr>
                <td colspan="3" style="border: 1px solid #cbd5e1; padding: 5px;">
                    {{ $topicsKeys[$i] ?? '' }}
                </td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right; font-weight: bold;">
                    {{ isset($topicsVals[$i]) ? $topicsVals[$i] . ' consultas' : '' }}
                </td>
                <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px;">
                    {{ isset($searchKeys[$i]) ? '"' . $searchKeys[$i] . '"' : '' }}
                </td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right; font-weight: bold;">
                    {{ isset($searchVals[$i]) ? $searchVals[$i] . ' veces' : '' }}
                </td>
            </tr>
        @endfor
    </table>
</body>
</html>

