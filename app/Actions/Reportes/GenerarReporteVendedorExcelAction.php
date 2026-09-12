<?php

namespace App\Actions\Reportes;

use InvalidArgumentException;

class GenerarReporteVendedorExcelAction
{
    /**
     * Renderiza la plantilla HTML para exportación a Excel (.xls).
     *
     * @param  string  $seccion  ('ventas_fisicas' | 'recojos_sucursal')
     * @param  array<string, mixed>  $datos
     */
    public function render(string $seccion, array $datos): string
    {
        $vista = match ($seccion) {
            'ventas_fisicas' => 'reportes.excel.vendedor-ventas-excel',
            'recojos_sucursal' => 'reportes.excel.vendedor-recojos-excel',
            default => throw new InvalidArgumentException("Sección de reporte inválida: {$seccion}"),
        };

        return view($vista, $datos)->render();
    }
}
