<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura Oficial - {{ $venta->numero_venta }} - MundiToys</title>
    <style>
        @page {
            margin: 25px 32px;
            size: a4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #005ca8;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }
        .logo {
            font-size: 24px;
            font-weight: 900;
            color: #005ca8;
            letter-spacing: -0.5px;
        }
        .logo span {
            color: #ea580c;
        }
        .company-subtitle {
            font-size: 9px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 1px;
        }
        .company-meta {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 2px;
            line-height: 1.3;
        }
        .fiscal-box {
            border: 2px solid #005ca8;
            border-radius: 6px;
            padding: 8px 12px;
            text-align: center;
            background-color: #f8fafc;
        }
        .fiscal-box .nit-title {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
        }
        .fiscal-box .factura-title {
            font-size: 13px;
            font-weight: 900;
            color: #005ca8;
            margin: 3px 0;
            text-transform: uppercase;
        }
        .fiscal-box .autorizacion {
            font-size: 8px;
            color: #475569;
        }
        .actividad-box {
            text-align: center;
            font-size: 8px;
            color: #475569;
            margin-bottom: 12px;
            font-style: italic;
        }
        .datos-cliente {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background-color: #f8fafc;
            padding: 8px 12px;
            margin-bottom: 14px;
        }
        .datos-cliente table {
            width: 100%;
            border-collapse: collapse;
        }
        .datos-cliente td {
            font-size: 9.5px;
            padding: 2px 4px;
            vertical-align: top;
        }
        .label {
            font-weight: bold;
            color: #334155;
            width: 18%;
        }
        .val {
            color: #0f172a;
        }
        .badge-pickup {
            display: inline-block;
            background-color: #e0f2fe;
            color: #0369a1;
            font-size: 8.5px;
            font-weight: bold;
            padding: 1px 6px;
            border-radius: 3px;
            border: 1px solid #bae6fd;
            text-transform: uppercase;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 14px;
        }
        .items-table th {
            background-color: #005ca8;
            color: #ffffff;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #005ca8;
            text-align: left;
        }
        .items-table td {
            padding: 6px 8px;
            font-size: 9.5px;
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
        .totales-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .literal-cell {
            width: 60%;
            vertical-align: top;
            padding-right: 15px;
        }
        .literal-box {
            background-color: #f1f5f9;
            border: 1px dashed #cbd5e1;
            border-radius: 4px;
            padding: 8px 10px;
            font-size: 9px;
            color: #1e293b;
            margin-top: 4px;
        }
        .literal-box strong {
            color: #005ca8;
        }
        .totales-cell {
            width: 40%;
            vertical-align: top;
        }
        .totales-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totales-table td {
            padding: 4px 6px;
            font-size: 9.5px;
        }
        .total-highlight {
            border-top: 2px solid #005ca8;
            font-size: 11px;
            font-weight: 900;
            color: #005ca8;
            background-color: #eff6ff;
        }
        .qr-section {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
        }
        .qr-cell {
            width: 28%;
            text-align: center;
            vertical-align: middle;
        }
        .qr-cell img {
            width: 110px;
            height: 110px;
            border: 1px solid #cbd5e1;
            padding: 2px;
            border-radius: 4px;
        }
        .qr-caption {
            font-size: 7.5px;
            color: #64748b;
            margin-top: 3px;
        }
        .leyenda-cell {
            width: 72%;
            vertical-align: middle;
            padding-left: 15px;
        }
        .control-code {
            font-family: monospace;
            font-size: 9px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .leyenda-fiscal {
            font-size: 8px;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            line-height: 1.35;
            margin-bottom: 4px;
        }
        .leyenda-ley {
            font-size: 7.5px;
            color: #64748b;
            line-height: 1.3;
        }
        .recojo-banner {
            margin-top: 10px;
            background-color: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 4px;
            padding: 6px 10px;
            font-size: 8.5px;
            color: #92400e;
        }
    </style>
</head>
<body>
    <!-- CABECERA FISCAL -->
    <table class="header-table">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <div class="logo">Mundi<span>Toys</span> ★</div>
                <div class="company-subtitle">MUNDITOYS BOLIVIA S.R.L.</div>
                <div class="company-meta">
                    <strong>CASA MATRIZ:</strong> Av. 16 de Julio N° 1440, El Prado - La Paz, Bolivia<br>
                    <strong>SUCURSAL:</strong> {{ $sucursal?->nombre ?? 'Sucursal Central' }}<br>
                    {{ $sucursal?->direccion ?? 'Av. Principal N° 123' }} - {{ $sucursal?->ciudad ?? 'Bolivia' }}<br>
                    Teléfono: {{ $sucursal?->telefono ?? '(+591) 2-2440000' }} | Email: contacto@munditoys.bo
                </div>
            </td>
            <td style="width: 45%; vertical-align: top;">
                <div class="fiscal-box">
                    <div class="nit-title">NIT: {{ $nitEmisor ?? '10283749012' }}</div>
                    <div class="factura-title">FACTURA</div>
                    <div style="font-size: 11px; font-weight: bold; color: #0f172a;">
                        N° {{ $numeroFactura }}
                    </div>
                    <div class="autorizacion">
                        N° DE AUTORIZACIÓN: {{ $numeroAutorizacion ?? '29040011007' }}<br>
                        ORIGINAL
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="actividad-box">
        Actividad Económica: VENTA AL POR MENOR DE JUGUETES, ARTÍCULOS DIDÁCTICOS Y DE ENTRETENIMIENTO EN COMERCIO ELECTRÓNICO Y TIENDAS ESPECIALIZADAS
    </div>

    <!-- DATOS DEL CLIENTE Y FACTURACIÓN -->
    <div class="datos-cliente">
        <table>
            <tr>
                <td class="label">Fecha y Hora:</td>
                <td class="val" style="width: 32%;">{{ $fechaEmision }}</td>
                <td class="label">NIT / CI:</td>
                <td class="val">{{ $facturaNitCi ?: ($cliente?->ci_nit ?: '0') }}</td>
            </tr>
            <tr>
                <td class="label">Señor(es):</td>
                <td class="val">{{ $facturaNombre ?: ($cliente?->name ?: 'Consumidor Final') }}</td>
                <td class="label">N° Pedido Web:</td>
                <td class="val" style="font-weight: bold; color: #005ca8;">{{ $venta->numero_venta }}</td>
            </tr>
            <tr>
                <td class="label">Modalidad:</td>
                <td class="val" colspan="3">
                    <span class="badge-pickup">Recojo en Sucursal: {{ $sucursal?->nombre ?? 'Tienda Central' }}</span>
                    <span style="font-size: 8.5px; color: #64748b; margin-left: 6px;">
                        (Retiro en: {{ $sucursal?->direccion ?? 'Tienda' }})
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <!-- TABLA DE JUGUETES Y CONCEPTOS -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;" class="text-center">#</th>
                <th style="width: 15%;">CÓDIGO</th>
                <th style="width: 48%;">DESCRIPCIÓN DEL PRODUCTO</th>
                <th style="width: 10%;" class="text-center">CANTIDAD</th>
                <th style="width: 11%;" class="text-right">P. UNITARIO</th>
                <th style="width: 11%;" class="text-right">SUBTOTAL</th>
            </tr>
        </thead>
        <tbody>
            @forelse($detalles as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td style="font-weight: bold; color: #005ca8;">{{ $item->producto?->codigo_producto ?? 'JUG-'.$item->producto_id }}</td>
                    <td>
                        <strong>{{ $item->producto?->nombre ?? 'Juguete MundiToys' }}</strong>
                        @if($item->producto?->categoria)
                            <span style="font-size: 8px; color: #64748b;"> - {{ $item->producto->categoria->nombre }}</span>
                        @endif
                    </td>
                    <td class="text-center" style="font-weight: bold;">{{ $item->cantidad }}</td>
                    <td class="text-right">Bs. {{ number_format($item->precio_unitario, 2) }}</td>
                    <td class="text-right" style="font-weight: bold;">Bs. {{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 12px; color: #94a3b8;">No se registraron líneas de venta.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- TOTALES Y LITERAL -->
    <table class="totales-grid">
        <tr>
            <td class="literal-cell">
                <div style="font-size: 8.5px; font-weight: bold; color: #475569; text-transform: uppercase;">Monto en Literal:</div>
                <div class="literal-box">
                    SON: <strong>{{ $montoLiteral }}</strong>
                </div>

                <div class="recojo-banner">
                    <strong>📦 Indicaciones de Recojo:</strong> Para retirar tu compra en sucursal presenta tu documento de identidad (CI) o exhibe el código QR fiscal adjunto en ventanilla de despacho.
                </div>
            </td>
            <td class="totales-cell">
                <table class="totales-table">
                    <tr>
                        <td class="text-right">SUBTOTAL:</td>
                        <td class="text-right" style="font-weight: bold;">Bs. {{ number_format($venta->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-right">DESCUENTO:</td>
                        <td class="text-right">Bs. {{ number_format($venta->descuento, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-right">COSTO DE ENVÍO / RETIRO:</td>
                        <td class="text-right" style="color: #16a34a; font-weight: bold;">Bs. 0.00 (Gratis)</td>
                    </tr>
                    <tr class="total-highlight">
                        <td class="text-right" style="padding: 6px;">TOTAL A PAGAR:</td>
                        <td class="text-right" style="padding: 6px;">Bs. {{ number_format($venta->total, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-right" style="font-size: 8px; color: #64748b;">MÉTODO:</td>
                        <td class="text-right" style="font-size: 8px; color: #64748b;">
                            {{ ucfirst($pago?->metodo_pago ?? 'Stripe Online') }} (PAGADO)
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- QR CODE Y LEYENDAS FISCALES -->
    <table class="qr-section">
        <tr>
            <td class="qr-cell">
                @if($qrDataUri)
                    <img src="{{ $qrDataUri }}" alt="Código QR Factura y Recojo">
                @else
                    <div style="width: 100px; height: 100px; border: 1px solid #cbd5e1; line-height: 100px;">QR</div>
                @endif
                <div class="qr-caption">QR Válido para Recojo y Fiscalización</div>
            </td>
            <td class="leyenda-cell">
                <div class="control-code">
                    CÓDIGO DE CONTROL: {{ $codigoControl ?? 'B3-7A-4F-E2-99' }}
                </div>
                <div class="control-code" style="font-size: 8.5px; color: #64748b;">
                    FECHA LÍMITE DE EMISIÓN: {{ now()->addDays(180)->format('d/m/Y') }}
                </div>
                <div class="leyenda-fiscal">
                    "ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO SERÁ SANCIONADO PENALMENTE DE ACUERDO A LEY"
                </div>
                <div class="leyenda-ley">
                    Ley N° 453: El proveedor deberá suministrar el servicio en las modalidades y precios ofertados, garantizando la calidad y seguridad de los productos.
                </div>
            </td>
        </tr>
    </table>
</body>
</html>

