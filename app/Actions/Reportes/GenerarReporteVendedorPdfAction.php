<?php

namespace App\Actions\Reportes;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use InvalidArgumentException;

class GenerarReporteVendedorPdfAction
{
    /**
     * Genera un reporte PDF para el vendedor en orientación horizontal.
     *
     * @param  string  $seccion  ('ventas_fisicas' | 'recojos_sucursal')
     * @param  array<string, mixed>  $datos
     */
    public function execute(string $seccion, array $datos): DomPdfWrapper
    {
        $vista = match ($seccion) {
            'ventas_fisicas' => 'reportes.pdf.vendedor-ventas-pdf',
            'recojos_sucursal' => 'reportes.pdf.vendedor-recojos-pdf',
            default => throw new InvalidArgumentException("Sección de reporte inválida: {$seccion}"),
        };

        return Pdf::loadView($vista, $datos)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Helvetica',
            ]);
    }
}
