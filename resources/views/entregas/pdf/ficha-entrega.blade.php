<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Entrega - {{ $venta->numero_venta }} - MundiToys</title>
    <style>
        @page {
            margin: 25px 30px;
            size: a4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            width: 100%;
            border-bottom: 2px solid #005ca8;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .logo {
            font-size: 24px;
            font-weight: 800;
            color: #005ca8;
            letter-spacing: -0.5px;
        }
        .logo span {
            color: #ea580c;
        }
        .company-meta {
            font-size: 9px;
            color: #64748b;
            margin-top: 3px;
        }
        .doc-title-box {
            text-align: right;
        }
        .doc-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            margin: 0;
        }
        .doc-number {
            font-size: 13px;
            font-weight: 700;
            color: #005ca8;
            margin-top: 2px;
        }
        .badge-delivered {
            display: inline-block;
            background-color: #dcfce7;
            color: #15803d;
            font-size: 9px;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid #86efac;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: collapse;
        }
        .info-cell {
            vertical-align: top;
            width: 50%;
            padding: 0;
        }
        .card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            margin-right: 6px;
        }
        .card-right {
            margin-right: 0;
            margin-left: 6px;
        }
        .card-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            color: #005ca8;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .field {
            font-size: 10px;
            margin-bottom: 3px;
        }
        .field-label {
            font-weight: 600;
            color: #475569;
        }
        .field-value {
            color: #0f172a;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 16px;
        }
        .items-table th {
            background-color: #005ca8;
            color: #ffffff;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 8px 10px;
            border: 1px solid #005ca8;
            text-align: left;
        }
        .items-table td {
            padding: 7px 10px;
            font-size: 10px;
            border: 1px solid #e2e8f0;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals-table {
            width: 40%;
            margin-left: auto;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .totals-table td {
            padding: 4px 8px;
            font-size: 10px;
        }
        .total-row {
            border-top: 2px solid #005ca8;
            font-size: 12px;
            font-weight: 800;
            color: #005ca8;
        }
        .signatures {
            width: 100%;
            margin-top: 40px;
            border-collapse: collapse;
        }
        .sig-box {
            width: 45%;
            text-align: center;
            vertical-align: top;
        }
        .sig-line {
            border-top: 1px dashed #64748b;
            margin: 45px 20px 6px 20px;
        }
        .sig-title {
            font-size: 10px;
            font-weight: bold;
            color: #1e293b;
        }
        .sig-sub {
            font-size: 9px;
            color: #64748b;
        }
        .footer-note {
            margin-top: 30px;
            font-size: 8.5px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- ENCABEZADO -->
    <table class="header">
        <tr>
            <td style="vertical-align: top;">
                <div class="logo">Mundi<span>Toys</span> ★</div>
                <div class="company-meta">
                    <strong>MundiToys Bolivia S.R.L.</strong> | NIT: 10283749012<br>
                    Sucursal: {{ $sucursal?->nombre ?? 'Sucursal Central' }}<br>
                    {{ $sucursal?->direccion ?? 'Av. Principal #123' }} - {{ $sucursal?->ciudad ?? 'Bolivia' }}<br>
                    Teléfono: {{ $sucursal?->telefono ?? '(+591) 4-4123456' }}
                </div>
            </td>
            <td class="doc-title-box" style="vertical-align: top;">
                <div class="doc-title">Ficha de Entrega</div>
                <div class="doc-number">{{ $venta->numero_venta }}</div>
                <div class="badge-delivered">
                    {{ $entrega?->estado === 'entregado' ? 'Entregado en Sucursal' : 'Pendiente de Retiro' }}
                </div>
                <div style="font-size: 9px; color: #64748b; margin-top: 4px;">
                    Emisión: {{ $fechaEmision }}
                </div>
            </td>
        </tr>
    </table>

    <!-- DATOS DEL CLIENTE Y DEL RECOJO -->
    <table class="info-grid">
        <tr>
            <td class="info-cell">
                <div class="card">
                    <div class="card-title">Datos del Comprador</div>
                    <div class="field">
                        <span class="field-label">Nombre / Razón Social:</span>
                        <span class="field-value">{{ $cliente?->name ?? 'Cliente General' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">CI / NIT:</span>
                        <span class="field-value">{{ $cliente?->ci_nit ?? 'Sin registrar' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Teléfono / Celular:</span>
                        <span class="field-value">{{ $cliente?->telefono ?? 'Sin registrar' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Correo Electrónico:</span>
                        <span class="field-value">{{ $cliente?->email ?? 'Sin correo' }}</span>
                    </div>
                </div>
            </td>
            <td class="info-cell">
                <div class="card card-right">
                    <div class="card-title">Datos de Recojo y Pago</div>
                    <div class="field">
                        <span class="field-label">Fecha de Compra Online:</span>
                        <span class="field-value">{{ $venta->fecha_venta?->format('d/m/Y H:i') ?? $venta->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Sucursal de Retiro:</span>
                        <span class="field-value">{{ $sucursal?->nombre ?? 'Sucursal' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Método de Pago:</span>
                        <span class="field-value">{{ ucfirst($pago?->metodo_pago ?? 'En línea') }} - {{ strtoupper($pago?->estado ?? 'Pagado') }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Personal que Entregó:</span>
                        <span class="field-value">{{ $empleado?->name ?? (auth()->user()?->name ?? 'Personal de ventas') }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Fecha y Hora Entrega:</span>
                        <span class="field-value">
                            {{ $entrega?->fecha_programada?->format('d/m/Y') ?? now()->format('d/m/Y') }}
                            @if($entrega?->hora_fin) ({{ $entrega->hora_fin }}) @endif
                        </span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- DETALLE DE JUGUETES ENTREGADOS -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;" class="text-center">#</th>
                <th style="width: 15%;">Código</th>
                <th style="width: 50%;">Descripción del Juguete</th>
                <th style="width: 10%;" class="text-center">Cantidad</th>
                <th style="width: 10%;" class="text-right">P. Unitario</th>
                <th style="width: 10%;" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($detalles as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td><strong style="color: #005ca8;">{{ $item->producto?->codigo_producto ?? 'JUG-'.$item->producto_id }}</strong></td>
                    <td>
                        <strong>{{ $item->producto?->nombre ?? 'Juguete' }}</strong>
                        @if($item->producto?->categoria)
                            <div style="font-size: 8.5px; color: #64748b;">Cat: {{ $item->producto->categoria->nombre }}</div>
                        @endif
                    </td>
                    <td class="text-center" style="font-weight: bold; font-size: 11px;">{{ $item->cantidad }}</td>
                    <td class="text-right">Bs. {{ number_format($item->precio_unitario, 2) }}</td>
                    <td class="text-right" style="font-weight: bold;">Bs. {{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 12px; color: #94a3b8;">No se registraron líneas de detalle.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- TOTALES -->
    <table class="totals-table">
        <tr>
            <td class="text-right"><strong>Subtotal:</strong></td>
            <td class="text-right">Bs. {{ number_format($venta->subtotal, 2) }}</td>
        </tr>
        @if($venta->descuento > 0)
        <tr>
            <td class="text-right" style="color: #dc2626;"><strong>Descuento:</strong></td>
            <td class="text-right" style="color: #dc2626;">- Bs. {{ number_format($venta->descuento, 2) }}</td>
        </tr>
        @endif
        <tr class="total-row">
            <td class="text-right">TOTAL PAGADO:</td>
            <td class="text-right">Bs. {{ number_format($venta->total, 2) }}</td>
        </tr>
    </table>

    <!-- SECCIÓN DE FIRMAS DE CONFORMIDAD -->
    <table class="signatures">
        <tr>
            <td class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-title">Firma del Cliente (Recibí Conforme)</div>
                <div class="sig-sub">Nombre: {{ $cliente?->name ?? '_________________________' }}</div>
                <div class="sig-sub">CI: {{ $cliente?->ci_nit ?? '_________________________' }}</div>
            </td>
            <td style="width: 10%;"></td>
            <td class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-title">Firma del Personal de Sucursal</div>
                <div class="sig-sub">Entregado por: {{ $empleado?->name ?? (auth()->user()?->name ?? 'Vendedor') }}</div>
                <div class="sig-sub">Sucursal: {{ $sucursal?->nombre ?? 'MundiToys' }}</div>
            </td>
        </tr>
    </table>

    <!-- PIE DE PÁGINA -->
    <div class="footer-note">
        Este documento es un comprobante oficial de retiro físico en tienda emitido por MundiToys. El cliente declara recibir los productos detallados en perfecto estado físico y embalaje adecuado. Para consultas o reclamos de garantía, conserve esta ficha.
    </div>
</body>
</html>

