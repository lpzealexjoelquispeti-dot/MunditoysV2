<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Ejecutivo de Logística y Despachos - MundiToys</title>
    <style>
        @page {
            margin: 20px 25px 30px 25px;
            size: a4 landscape;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .header {
            width: 100%;
            border-bottom: 2px solid #005ca8;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .logo-box {
            font-size: 22px;
            font-weight: 800;
            color: #005ca8;
            letter-spacing: -0.5px;
        }
        .logo-box span {
            color: #ea580c;
        }
        .report-title {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            margin: 0;
        }
        .report-subtitle {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 14px;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .meta-table td {
            padding: 5px 10px;
            font-size: 9px;
            border-bottom: 1px solid #e2e8f0;
        }
        .meta-label {
            font-weight: bold;
            color: #475569;
            width: 16%;
        }
        .meta-value {
            color: #0f172a;
            width: 34%;
        }
        .kpi-container {
            width: 100%;
            margin-bottom: 15px;
        }
        .kpi-card {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-top: 3px solid #005ca8;
            padding: 8px 10px;
            text-align: center;
        }
        .kpi-title {
            font-size: 8.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }
        .kpi-number {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 4px;
        }
        .kpi-badge {
            font-size: 8px;
            font-weight: 600;
            margin-top: 2px;
        }
        .section-heading {
            font-size: 11px;
            font-weight: 700;
            color: #005ca8;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 14px;
            margin-bottom: 8px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .data-table th {
            background-color: #005ca8;
            color: #ffffff;
            font-weight: 700;
            text-align: left;
            padding: 6px 8px;
            font-size: 8.5px;
            text-transform: uppercase;
        }
        .data-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 8.5px;
        }
        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }
        .font-bold {
            font-weight: bold;
        }
        .tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .tag-entregado {
            background-color: #dcfce7;
            color: #15803d;
        }
        .tag-camino {
            background-color: #fef3c7;
            color: #b45309;
        }
        .tag-pendiente {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        .tag-cancelado {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 20px;
            border-top: 1px solid #cbd5e1;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            line-height: 20px;
        }
    </style>
</head>
<body>

    {{-- ENCABEZADO CORPORATIVO --}}
    <table class="header">
        <tr>
            <td style="width: 50%; vertical-align: middle;">
                <div class="logo-box">★ Mundi<span>Toys</span></div>
                <div style="font-size: 9px; color: #64748b; margin-top: 2px;">Juguetería Interactiva &bull; Módulo de Despacho y Logística</div>
            </td>
            <td style="width: 50%; text-align: right; vertical-align: middle;">
                <div class="report-title">Reporte Ejecutivo de Logística y Rutas</div>
                <div class="report-subtitle">Generado el {{ now()->format('d/m/Y H:i:s') }} &bull; Usuario: {{ auth()->user()->name ?? 'Administrador' }}</div>
            </td>
        </tr>
    </table>

    {{-- METADATOS Y PARÁMETROS DE FILTRADO --}}
    <table class="meta-table">
        <tr>
            <td class="meta-label">Rango de Consulta:</td>
            <td class="meta-value">{{ $filtros_aplicados['fecha_desde'] }} al {{ $filtros_aplicados['fecha_hasta'] }}</td>
            <td class="meta-label">Sucursal Origen:</td>
            <td class="meta-value">{{ $filtros_aplicados['sucursal'] }}</td>
        </tr>
        <tr>
            <td class="meta-label">Chofer Asignado:</td>
            <td class="meta-value">{{ $filtros_aplicados['chofer'] }}</td>
            <td class="meta-label">Turno Horario:</td>
            <td class="meta-value">{{ $filtros_aplicados['turno'] }}</td>
        </tr>
        <tr>
            <td class="meta-label">Estado de Entrega:</td>
            <td class="meta-value">{{ $filtros_aplicados['estado'] }}</td>
            <td class="meta-label">Entorno Operativo:</td>
            <td class="meta-value">Ciudad de La Paz &bull; Intervalos de 90 min (12:00 a 18:00)</td>
        </tr>
    </table>

    {{-- TARJETAS KPI DE LOGÍSTICA --}}
    <table class="kpi-container" style="border-spacing: 6px; border-collapse: separate;">
        <tr>
            <td class="kpi-card" style="border-top-color: #005ca8; width: 25%;">
                <div class="kpi-title">Despachos Totales</div>
                <div class="kpi-number">{{ number_format($kpis['total_despachos']) }}</div>
                <div class="kpi-badge" style="color: #005ca8;">Volumen asignado</div>
            </td>
            <td class="kpi-card" style="border-top-color: #16a34a; width: 25%;">
                <div class="kpi-title">Entregas Exitosas</div>
                <div class="kpi-number" style="color: #16a34a;">{{ number_format($kpis['total_entregados']) }}</div>
                <div class="kpi-badge" style="color: #16a34a;">Efectividad: {{ $kpis['tasa_efectividad'] }}%</div>
            </td>
            <td class="kpi-card" style="border-top-color: #f59e0b; width: 25%;">
                <div class="kpi-title">En Tránsito / Pendientes</div>
                <div class="kpi-number" style="color: #b45309;">{{ number_format($kpis['total_en_camino'] + $kpis['total_pendientes']) }}</div>
                <div class="kpi-badge" style="color: #b45309;">En camino: {{ $kpis['total_en_camino'] }} | En espera: {{ $kpis['total_pendientes'] }}</div>
            </td>
            <td class="kpi-card" style="border-top-color: #dc2626; width: 25%;">
                <div class="kpi-title">Canceladas / Incidencias</div>
                <div class="kpi-number" style="color: #dc2626;">{{ number_format($kpis['total_cancelados']) }}</div>
                <div class="kpi-badge" style="color: #dc2626;">Tasa cancelación: {{ $kpis['tasa_cancelacion'] }}%</div>
            </td>
        </tr>
    </table>

    {{-- TABLA RESUMEN POR TURNOS Y ZONAS (2 COLUMNAS) --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
        <tr>
            {{-- Columna 1: Desglose por Turno de 90 min --}}
            <td style="width: 50%; vertical-align: top; padding-right: 8px;">
                <div class="section-heading">Desglose por Turnos de 90 Minutos</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Turno Horario</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Entregados</th>
                            <th class="text-center">Cancelados</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($despachos_por_turno as $turno)
                            @if ($turno['conteo'] > 0)
                                <tr>
                                    <td class="font-bold">{{ $turno['etiqueta'] }}</td>
                                    <td class="text-center font-bold">{{ $turno['conteo'] }}</td>
                                    <td class="text-center" style="color: #16a34a; font-weight: bold;">{{ $turno['entregados'] }}</td>
                                    <td class="text-center" style="color: #dc2626; font-weight: bold;">{{ $turno['cancelados'] }}</td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="4" class="text-center" style="color: #94a3b8;">Sin registros de turnos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>

            {{-- Columna 2: Distribución por Zonas de La Paz --}}
            <td style="width: 50%; vertical-align: top; padding-left: 8px;">
                <div class="section-heading">Distribución Territorial (Zonas de La Paz)</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Zona / Distrito</th>
                            <th class="text-center">Despachos</th>
                            <th class="text-center">Efectividad</th>
                            <th class="text-right">% Flota</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($despachos_por_zona as $zona)
                            @php
                                $efectividadZona = $zona['total'] > 0 ? round(($zona['entregados'] / $zona['total']) * 100, 1) : 0;
                            @endphp
                            <tr>
                                <td class="font-bold">{{ $zona['zona'] }}</td>
                                <td class="text-center font-bold">{{ $zona['total'] }}</td>
                                <td class="text-center" style="color: #16a34a; font-weight: bold;">{{ $efectividadZona }}%</td>
                                <td class="text-right font-bold">{{ $zona['porcentaje'] }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center" style="color: #94a3b8;">Sin registros de zonas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    {{-- DESEMPEÑO POR CHOFER --}}
    <div class="section-heading">Rendimiento Operativo por Chofer / Repartidor</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Chofer / Repartidor</th>
                <th class="text-center">Total Asignadas</th>
                <th class="text-center">Entregadas</th>
                <th class="text-center">En Camino</th>
                <th class="text-center">Pendientes</th>
                <th class="text-center">Canceladas</th>
                <th class="text-right">Tasa de Éxito</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($despachos_por_chofer as $chofer)
                <tr>
                    <td class="font-bold">{{ $chofer['nombre'] }}</td>
                    <td class="text-center font-bold">{{ $chofer['total'] }}</td>
                    <td class="text-center" style="color: #16a34a; font-weight: bold;">{{ $chofer['entregados'] }}</td>
                    <td class="text-center" style="color: #b45309;">{{ $chofer['total'] - $chofer['entregados'] - $chofer['cancelados'] - $chofer['pendientes'] }}</td>
                    <td class="text-center" style="color: #0369a1;">{{ $chofer['pendientes'] }}</td>
                    <td class="text-center" style="color: #dc2626; font-weight: bold;">{{ $chofer['cancelados'] }}</td>
                    <td class="text-right font-bold" style="color: {{ $chofer['tasa_exito'] >= 80 ? '#16a34a' : '#dc2626' }};">
                        {{ $chofer['tasa_exito'] }}%
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="color: #94a3b8;">No se encontraron choferes con entregas en este rango.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- LISTADO DETALLADO DE DESPACHOS --}}
    <div class="section-heading">Detalle de Despachos y Entregas Registradas</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 12%;">Nº Venta</th>
                <th style="width: 9%;">Fecha Prog.</th>
                <th style="width: 10%;">Turno</th>
                <th style="width: 15%;">Cliente</th>
                <th style="width: 12%;">Zona</th>
                <th style="width: 14%;">Chofer</th>
                <th style="width: 10%;" class="text-center">Estado</th>
                <th style="width: 18%;">Motivo / Observaciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entregas_registros as $entrega)
                <tr>
                    <td class="font-bold">{{ $entrega->venta?->numero_venta ?? 'N/A' }}</td>
                    <td>{{ $entrega->fecha_programada ? \Carbon\Carbon::parse($entrega->fecha_programada)->format('d/m/Y') : 'Inmediata' }}</td>
                    <td>
                        @if ($entrega->hora_inicio)
                            {{ substr((string) $entrega->hora_inicio, 0, 5) }} - {{ substr((string) $entrega->hora_fin, 0, 5) }}
                        @else
                            Mostrador
                        @endif
                    </td>
                    <td>
                        <div class="font-bold">{{ $entrega->venta?->cliente?->name ?? 'Cliente Mostrador' }}</div>
                        <div style="font-size: 7.5px; color: #64748b;">{{ $entrega->venta?->cliente?->telefono ?? '' }}</div>
                    </td>
                    <td>{{ $entrega->direccion?->zona ?? ($entrega->sucursal?->zona ?? 'N/A') }}</td>
                    <td>{{ $entrega->empleado?->name ?? 'Sin asignar' }}</td>
                    <td class="text-center">
                        <span class="tag tag-{{ $entrega->estado }}">
                            {{ ucfirst($entrega->estado) }}
                        </span>
                    </td>
                    <td style="font-size: 7.5px; color: #475569;">
                        @if ($entrega->estado === 'cancelado')
                            <strong style="color: #dc2626;">Cancelado:</strong> {{ $entrega->motivo_cancelacion }}
                        @elseif ($entrega->notas_despacho)
                            {{ $entrega->notas_despacho }}
                        @elseif ($entrega->entregado_at)
                            <span style="color: #16a34a;">Entregado a las {{ \Carbon\Carbon::parse($entrega->entregado_at)->format('H:i') }}</span>
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="color: #94a3b8; padding: 12px;">No se encontraron registros de despachos para los filtros seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        MundiToys &bull; Sistema Oficial de Logística &bull; Documento generado de forma confidencial para uso administrativo y operativo
    </div>

</body>
</html>

