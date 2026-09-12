<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Pedidos para Recojo en Sucursal - MundiToys</title>
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
            margin-bottom: 14px;
            border-collapse: collapse;
        }
        .kpi-cell {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            padding: 8px;
            text-align: center;
            width: 25%;
        }
        .kpi-val {
            font-size: 15px;
            font-weight: 800;
            color: #15803d;
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
        .badge-green {
            background-color: #dcfce7;
            color: #15803d;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
        }
        .badge-yellow {
            background-color: #fef9c3;
            color: #854d0e;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="vertical-align: top;">
                <div class="logo-box">Mundi<span>Toys</span> ★</div>
                <div class="report-sub">Gestión de Entregas en Tienda | Sucursal: {{ $sucursal?->nombre ?? 'Sucursal' }}</div>
            </td>
            <td class="text-right" style="vertical-align: top;">
                <div class="report-title">Reporte de Recojos en Sucursal</div>
                <div class="report-sub">Personal: <strong>{{ $vendedor?->name ?? 'Todo el personal' }}</strong> | Generado: {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td style="width: 25%;"><strong>Rango de Fechas:</strong> {{ $fecha_desde }} al {{ $fecha_hasta }}</td>
            <td style="width: 25%;"><strong>Sucursal:</strong> {{ $sucursal?->nombre ?? 'Sucursal' }}</td>
            <td style="width: 25%;"><strong>Modalidad:</strong> Recojo en Tienda Online</td>
            <td style="width: 25%;"><strong>Tasa Efectividad:</strong> {{ $tasa_efectividad }}%</td>
        </tr>
    </table>

    <!-- KPIS -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-val">{{ $total_pedidos }}</div>
                <div class="kpi-label">Pedidos para Recojo</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-val">{{ $pedidos_entregados }}</div>
                <div class="kpi-label">Pedidos Entregados</div>
            </td>
            <td class="kpi-cell" style="background-color: #fefce8; border-color: #fef08a;">
                <div class="kpi-val" style="color: #a16207;">{{ $pedidos_pendientes }}</div>
                <div class="kpi-label">Pendientes de Retiro</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-val">Bs. {{ number_format($total_monto_entregado, 2) }}</div>
                <div class="kpi-label">Monto Total Entregado</div>
            </td>
        </tr>
    </table>

    <!-- LISTA DE PEDIDOS -->
    <div style="font-weight: bold; font-size: 10px; margin-bottom: 4px; color: #005ca8;">DETALLE DE PEDIDOS PARA RECOJO EN SUCURSAL</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 12%;">Nro. Venta</th>
                <th style="width: 15%;">Fecha Compra</th>
                <th style="width: 22%;">Comprador</th>
                <th style="width: 12%;">Estado Entrega</th>
                <th style="width: 15%;">Fecha / Hora Recojo</th>
                <th style="width: 14%;">Atendido Por</th>
                <th style="width: 10%;" class="text-right">Monto (Bs.)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pedidos as $p)
                @php
                    $estadoEntrega = $p->entrega?->estado ?? 'pendiente';
                @endphp
                <tr>
                    <td><strong style="color: #005ca8;">{{ $p->numero_venta }}</strong></td>
                    <td>{{ $p->fecha_venta?->format('d/m/Y H:i') ?? $p->created_at->format('d/m/Y H:i') }}</td>
                    <td>
                        <strong>{{ $p->cliente?->name ?? 'Cliente Web' }}</strong>
                        @if($p->cliente?->telefono)
                            <div style="font-size: 8px; color: #64748b;">Cel: {{ $p->cliente->telefono }}</div>
                        @endif
                    </td>
                    <td>
                        @if($estadoEntrega === 'entregado')
                            <span class="badge-green">Entregado</span>
                        @else
                            <span class="badge-yellow">Listo / Pendiente</span>
                        @endif
                    </td>
                    <td>
                        @if($estadoEntrega === 'entregado')
                            {{ $p->entrega?->fecha_programada?->format('d/m/Y') ?? '' }} {{ $p->entrega?->hora_fin ?? '' }}
                        @else
                            <span style="color: #94a3b8;">Sin retirar</span>
                        @endif
                    </td>
                    <td>{{ $p->entrega?->empleado?->name ?? 'Por asignar' }}</td>
                    <td class="text-right" style="font-weight: bold;">Bs. {{ number_format($p->total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="color: #94a3b8; padding: 10px;">No se encontraron pedidos de recojo para los filtros seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

