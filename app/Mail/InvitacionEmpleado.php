<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitacionEmpleado extends Mailable
{
    public function __construct(
        public string $nombre,
        public string $correo,
        public string $rol,
        public string $urlActivacion,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu invitación a MundiToys · '.$this->rol);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invitacion-empleado', text: 'mail.invitacion-empleado-texto');
    }
}
