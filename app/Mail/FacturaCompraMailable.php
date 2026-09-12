<?php

namespace App\Mail;

use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FacturaCompraMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Venta $venta,
        public string $pdfContenido,
        public ?string $qrDataUri = null,
        public ?string $facturaNombre = null,
        public ?string $facturaNitCi = null,
        public ?string $sucursalNombre = null,
        public ?string $sucursalDireccion = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "MundiToys · Factura y Recojo de Compra #{$this->venta->numero_venta}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.factura-compra',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContenido, "Factura_{$this->venta->numero_venta}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
