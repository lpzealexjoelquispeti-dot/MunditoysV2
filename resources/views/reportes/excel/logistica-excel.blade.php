<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Logística y Despachos - MundiToys</title>
</head>
<body>
    <table style="font-family: Arial, sans-serif; font-size: 11pt; border-collapse: collapse;">
        <!-- ENCABEZADO CORPORATIVO -->
        <tr>
            <th colspan="9" style="font-size: 16pt; font-weight: bold; color: #005ca8; text-align: left; height: 35px;">
                MUNDITOYS - REPORTE EJECUTIVO DE LOGÍSTICA Y DESPACHOS
            </th>
        </tr>
        <tr>
            <td colspan="9" style="font-size: 10pt; color: #64748b; font-style: italic;">
                Generado el: {{ now()->format('d/m/Y H:i:s') }} | Usuario: {{ auth()->user()?->name ?? 'Administrador' }}
            </td>
        </tr>
        <tr><td colspan="9"></td></tr>

        <!-- PARÁMETROS DE FILTRADO -->
        <tr style="background-color: #f1f5f9;">
            <th colspan="2" style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Rango de Fechas:</th>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $filtros_aplicados['fecha_desde'] }} al {{ $filtros_aplicados['fecha_hasta'] }}</td>
            <th colspan="2" style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Sucursal:</th>
            <td colspan="3" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $filtros_aplicados['sucursal'] }}</td>
        </tr>
        <tr style="background-color: #f1f5f9;">
            <th colspan="2" style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Chofer:</th>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $filtros_aplicados['chofer'] }}</td>
            <th colspan="2" style="font-weight: bold; border: 1px solid #cbd5e1; text-align: left; padding: 5px;">Turno Horario:</th>
            <td colspan="3" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $filtros_aplicados['turno'] }}</td>
        </tr>
        <tr><td colspan="9"></td></tr>

        <!-- RESUMEN EJECUTIVO (KPIS) -->
        <tr>
            <th colspan="9" style="font-size: 12pt; font-weight: bold; background-color: #005ca8; color: #ffffff; padding: 6px; border: 1px solid #005ca8;">
                INDICADORES CLAVE DE DESEMPEÑO LOGÍSTICO (KPIS)
            </th>
        </tr>
        <tr style="background-color: #e2e8f0; font-weight: bold; text-align: center;">
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Despachos Totales</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Entregados</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">En Camino</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Pendientes</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Cancelados</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Tasa Efectividad</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">Tasa Cancelación</td>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 8px;">Monto Total Despachado</td>
        </tr>
        <tr style="text-align: center; font-size: 11pt;">
            <td style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold; color: #005ca8;">{{ number_format($kpis['total_despachos']) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold; color: #16a34a;">{{ number_format($kpis['total_entregados']) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; color: #b45309;">{{ number_format($kpis['total_en_camino']) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; color: #0369a1;">{{ number_format($kpis['total_pendientes']) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold; color: #dc2626;">{{ number_format($kpis['total_cancelados']) }}</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold;">{{ $kpis['tasa_efectividad'] }}%</td>
            <td style="border: 1px solid #cbd5e1; padding: 8px;">{{ $kpis['tasa_cancelacion'] }}%</td>
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 8px; font-weight: bold; color: #16a34a;">Bs. {{ number_format($kpis['monto_total_despachado'], 2) }}</td>
        </tr>
        <tr><td colspan="9"></td></tr>

        <!-- DESGLOSE POR TURNOS DE 90 MINUTOS -->
        <tr>
            <th colspan="9" style="font-size: 11pt; font-weight: bold; background-color: #334155; color: #ffffff; padding: 5px;">
                DISTRIBUCIÓN POR TURNOS DE 90 MINUTOS (12:00 A 18:00)
            </th>
        </tr>
        <tr style="background-color: #f8fafc; font-weight: bold;">
            <th colspan="4" style="border: 1px solid #cbd5e1; padding: 5px;">Turno Horario</th>
            <th colspan="2" style="border: 1px solid #cbd5e1; padding: 5px; text-align: center;">Total Despachos</th>
            <th colspan="2" style="border: 1px solid #cbd5e1; padding: 5px; text-align: center;">Entregados</th>
            <th style="border: 1px solid #cbd5e1; padding: 5px; text-align: center;">Cancelados</th>
        </tr>
        @foreach ($despachos_por_turno as $turno)
            @if ($turno['conteo'] > 0)
                <tr>
                    <td colspan="4" style="border: 1px solid #cbd5e1; padding: 5px;">{{ $turno['etiqueta'] }}</td>
                    <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px; text-align: center; font-weight: bold;">{{ $turno['conteo'] }}</td>
                    <td colspan="2" style="border: 1px solid #cbd5e1; padding: 5px; text-align: center; color: #16a34a; font-weight: bold;">{{ $turno['entregados'] }}</td>
                    <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: center; color: #dc2626; font-weight: bold;">{{ $turno['cancelados'] }}</td>
                </tr>
            @endif
        @endforeach
        <tr><td colspan="9"></td></tr>

        <!-- REGISTRO DETALLADO DE DESPACHOS -->
        <tr>
            <th colspan="9" style="font-size: 11pt; font-weight: bold; background-color: #005ca8; color: #ffffff; padding: 6px;">
                REGISTRO DETALLADO DE DESPACHOS Y ENTREGAS
            </th>
        </tr>
        <tr style="background-color: #e2e8f0; font-weight: bold; text-align: center;">
            <th style="border: 1px solid #cbd5e1; padding: 6px;">Nº Venta</th>
            <th style="border: 1px solid #cbd5e1; padding: 6px;">Fecha Prog.</th>
            <th style="border: 1px solid #cbd5e1; padding: 6px;">Turno</th>
            <th style="border: 1px solid #cbd5e1; padding: 6px;">Cliente</th>
            <th style="border: 1px solid #cbd5e1; padding: 6px;">Teléfono</th>
            <th style="border: 1px solid #cbd5e1; padding: 6px;">Zona (La Paz)</th>
            <th style="border: 1px solid #cbd5e1; padding: 6px;">Chofer</th>
            <th style="border: 1px solid #cbd5e1; padding: 6px;">Estado</th>
            <th style="border: 1px solid #cbd5e1; padding: 6px;">Total Venta</th>
        </tr>
        @forelse ($entregas_registros as $entrega)
            <tr>
                <td style="border: 1px solid #cbd5e1; padding: 5px; font-weight: bold;">{{ $entrega->venta?->numero_venta ?? 'N/A' }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: center;">{{ $entrega->fecha_programada ? \Carbon\Carbon::parse($entrega->fecha_programada)->format('d/m/Y') : '-' }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: center;">
                    @if ($entrega->hora_inicio)
                        {{ substr((string) $entrega->hora_inicio, 0, 5) }} - {{ substr((string) $entrega->hora_fin, 0, 5) }}
                    @else
                        Mostrador
                    @endif
                </td>
                <td style="border: 1px solid #cbd5e1; padding: 5px;">{{ $entrega->venta?->cliente?->name ?? 'Cliente Mostrador' }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: center;">{{ $entrega->venta?->cliente?->telefono ?? '-' }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px;">{{ $entrega->direccion?->zona ?? ($entrega->sucursal?->zona ?? 'N/A') }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px;">{{ $entrega->empleado?->name ?? 'Sin chofer asignado' }}</td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: center; font-weight: bold;">
                    @if ($entrega->estado === 'entregado')
                        ENTREGADO
                    @elseif ($entrega->estado === 'cancelado')
                        CANCELADO ({{ $entrega->motivo_cancelacion }})
                    @elseif ($entrega->estado === 'en_camino')
                        EN CAMINO
                    @else
                        PENDIENTE
                    @endif
                </td>
                <td style="border: 1px solid #cbd5e1; padding: 5px; text-align: right; font-weight: bold;">Bs. {{ number_format((float) ($entrega->venta?->total ?? 0), 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="9" style="border: 1px solid #cbd5e1; padding: 10px; text-align: center; color: #64748b;">No se encontraron registros de despachos.</td>
            </tr>
        @endforelse
    </table>
</body>
</html>

