<?php

namespace App\Livewire\Cliente;

use App\Actions\Ventas\GenerarFacturaPdfAction;
use App\Models\Venta;
use App\Services\QrCodeService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('¡Compra Exitosa! - MundiToys')]
class CompraExitosa extends Component
{
    public string $numeroVenta;

    public bool $mostrarQr = true;

    public function mount(string $numeroVenta): void
    {
        $this->numeroVenta = $numeroVenta;
    }

    public function toggleQr(): void
    {
        $this->mostrarQr = ! $this->mostrarQr;
    }

    public function descargarFactura(GenerarFacturaPdfAction $action): StreamedResponse
    {
        $venta = Venta::query()->where('numero_venta', $this->numeroVenta)->firstOrFail();
        $pdf = $action->execute($venta);

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            "Factura_{$venta->numero_venta}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    public function render(QrCodeService $qrCodeService): View
    {
        $venta = Venta::query()
            ->with([
                'cliente',
                'pago',
                'entrega.sucursal',
                'detalles.producto.imagenes',
                'detalles.producto.categoria',
            ])
            ->where('numero_venta', $this->numeroVenta)
            ->firstOrFail();

        $codigoQrContenido = "MUNDITOYS-RECOJO:{$venta->numero_venta}";
        $qrDataUri = $qrCodeService->generarDataUri($codigoQrContenido, 280);

        return view('livewire.cliente.compra-exitosa', [
            'venta' => $venta,
            'entrega' => $venta->entrega,
            'sucursal' => $venta->entrega?->sucursal,
            'pago' => $venta->pago,
            'detalles' => $venta->detalles,
            'qrDataUri' => $qrDataUri,
        ]);
    }
}
