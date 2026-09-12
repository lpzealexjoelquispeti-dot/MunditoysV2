<?php

namespace App\Actions\Ventas;

use App\Models\Venta;
use App\Services\NumeroALetrasService;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;

class GenerarFacturaPdfAction
{
    public function __construct(
        private QrCodeService $qrCodeService
    ) {}

    /**
     * Genera la Factura Oficial de Venta para el cliente en PDF.
     */
    public function execute(
        Venta $venta,
        ?string $facturaNombre = null,
        ?string $facturaNitCi = null
    ): DomPdfWrapper {
        $venta->loadMissing([
            'cliente',
            'pago',
            'entrega.sucursal',
            'detalles.producto.categoria',
        ]);

        $contenidoQr = "MUNDITOYS-RECOJO:{$venta->numero_venta}";
        $qrDataUri = $this->qrCodeService->generarDataUri($contenidoQr, 220);

        $montoLiteral = NumeroALetrasService::convertir((float) $venta->total);

        // Generar datos fiscales bolivianos coherentes
        $numeroFactura = sprintf('%06d', $venta->id);
        $codigoControl = strtoupper(substr(md5($venta->numero_venta.$venta->created_at), 0, 2).'-'.
            substr(md5($venta->id.'ctrl'), 0, 2).'-'.
            substr(md5($venta->total.'bol'), 0, 2).'-'.
            substr(md5($venta->numero_venta.'end'), 0, 2));

        return Pdf::loadView('facturacion.pdf.factura-cliente', [
            'venta' => $venta,
            'entrega' => $venta->entrega,
            'cliente' => $venta->cliente,
            'pago' => $venta->pago,
            'detalles' => $venta->detalles,
            'sucursal' => $venta->entrega?->sucursal,
            'qrDataUri' => $qrDataUri,
            'montoLiteral' => $montoLiteral,
            'fechaEmision' => ($venta->fecha_venta ?? $venta->created_at ?? now())->format('d/m/Y H:i'),
            'numeroFactura' => $numeroFactura,
            'numeroAutorizacion' => '29040011007',
            'nitEmisor' => '10283749012',
            'codigoControl' => $codigoControl,
            'facturaNombre' => $facturaNombre ?: ($venta->cliente?->name ?? 'Consumidor Final'),
            'facturaNitCi' => $facturaNitCi ?: ($venta->cliente?->ci_nit ?? '0'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Helvetica',
            ]);
    }
}
