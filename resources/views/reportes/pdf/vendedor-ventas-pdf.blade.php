<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas Físicas de Mostrador - {{ $vendedor?->name ?? 'Vendedor' }}</title>
    <style>
        @page {
            margin: 20px 25px;
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
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .logo-box {
            font-size: 20px;
            font-weight: 800;
            color: #005ca8;
        }
        .logo-box span {
            color: #ea580c;
        }
        .report-title {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
        }
        .report-sub {
            font-size: 9px;
            color: #64748b;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .meta-table td {
            padding: 5px 8px;
            font-size: 9px;
            border-bottom: 1px solid #e2e8f0;
        }
        .kpi-table {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .kpi-cell {
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            padding: 8px;
            text-align: center;
            width: 25%;
        }
        .kpi-val {
            font-size: 15px;
            font-weight: 800;
            color: #0369a1;
        }
        .kpi-label {
            font-size: 8.5px;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .data-table th {
            background-color: #005ca8;
            color: #ffffff;
            font-size: 8.5px;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #005ca8;
            text-align: left;
        }
        .data-table td {
            padding: 5px 8px;
            font-size: 9px;
            border: 1px solid #e2e8f0;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="vertical-align: top;">
                <div class="logo-box">Mundi<span>Toys</span> ★</div>
                <div class="report-sub">Personal de Ventas | Sucursal: {{ $sucursal?->nombre ?? 'Todas' }}</div>
            </td>
            <td class="text-right" style="vertical-align: top;">
                <div class="report-title">Reporte Personal: Ventas Físicas (POS)</div>
                <div class="report-sub">Vendedor: <strong>{{ $vendedor?->name ?? 'Vendedor' }}</strong> | Generado: {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td style="width: 20%;"><strong>Rango de Fechas:</strong> {{ $fecha_desde }} al {{ $fecha_hasta }}</td>
            <td style="width: 25%;"><strong>Sucursal:</strong> {{ $sucursal?->nombre ?? 'Sucursal asignada' }}</td>
            <td style="width: 25%;"><strong>Canal:</strong> Mostrador Presencial (POS)</td>
            <td style="width: 30%;"><strong>Estado Ventas:</strong> Completadas</td>
        </tr>
    </table>

    <!-- KPIS -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-val">Bs. {{ number_format($total_ingresos, 2) }}</div>
                <div class="kpi-label">Total Recaudado</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-val">{{ $total_ventas }}</div>
                <div class="kpi-label">Tickets Emitidos</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-val">Bs. {{ number_format($ticket_promedio, 2) }}</div>
                <div class="kpi-label">Ticket Promedio</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-val">{{ $unidades_vendidas }}</div>
                <div class="kpi-label">Juguetes Vendidos</div>
            </td>
        </tr>
    </table>

    <!-- MÉTODOS DE PAGO Y TOP JUGUETES -->
    <table style="width: 100%; margin-bottom: 12px; border-collapse: collapse;">
        <tr>
            <td style="width: 48%; vertical-align: top; padding-right: 10px;">
                <div style="font-weight: bold; font-size: 10px; margin-bottom: 4px; color: #005ca8;">DESGLOSE POR MÉTODO DE COBRO</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Método</th>
                            <th class="text-center">Transacciones</th>
                            <th class="text-right">Monto (Bs.)</th>
                            <th class="text-right">% Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($desglose_pagos as $pago)
                            @if($pago['conteo'] > 0)
                            <tr>
                                <td><strong>{{ $pago['nombre'] }}</strong></td>
                                <td class="text-center">{{ $pago['conteo'] }}</td>
                                <td class="text-right">Bs. {{ number_format($pago['monto'], 2) }}</td>
                                <td class="text-right">{{ $pago['porcentaje'] }}%</td>
                            </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td style="width: 52%; vertical-align: top;">
                <div style="font-weight: bold; font-size: 10px; margin-bottom: 4px; color: #005ca8;">TOP JUGUETES MÁS VENDIDOS</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Juguete</th>
                            <th>Categoría</th>
                            <th class="text-center">Unidades</th>
                            <th class="text-right">Recaudación (Bs.)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($top_productos as $top)
                            <tr>
                                <td><strong>{{ $top['nombre'] }}</strong></td>
                                <td>{{ $top['categoria'] }}</td>
                                <td class="text-center" style="font-weight: bold;">{{ $top['unidades'] }}</td>
                                <td class="text-right">Bs. {{ number_format($top['total_generado'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center" style="color: #94a3b8;">Sin ventas registradas en el período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <!-- LISTA DE TRANSACCIONES -->
    <div style="font-weight: bold; font-size: 10px; margin-bottom: 4px; color: #005ca8;">HISTORIAL DE VENTAS EMITIDAS</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 15%;">Fecha / Hora</th>
                <th style="width: 15%;">Nro. Venta</th>
                <th style="width: 25%;">Cliente</th>
                <th style="width: 20%;">Método de Pago</th>
                <th style="width: 10%;" class="text-center">Artículos</th>
                <th style="width: 15%;" class="text-right">Total (Bs.)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ventas as $v)
                <tr>
                    <td>{{ $v->fecha_venta?->format('d/m/Y H:i') ?? $v->created_at->format('d/m/Y H:i') }}</td>
                    <td><strong style="color: #005ca8;">{{ $v->numero_venta }}</strong></td>
                    <td>{{ $v->cliente?->name ?? 'Cliente Mostrador' }}</td>
                    <td>{{ ucfirst($v->pago?->metodo_pago ?? 'Efectivo') }}</td>
                    <td class="text-center">{{ $v->detalles->sum('cantidad') }}</td>
                    <td class="text-right" style="font-weight: bold;">Bs. {{ number_format($v->total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="color: #94a3b8; padding: 10px;">No se encontraron ventas para los filtros seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

