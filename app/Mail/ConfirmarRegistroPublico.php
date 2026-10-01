<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Paso 1 del registro público: el link para confirmar el correo. Hasta que se
 * abre, no existe ni el negocio ni su usuario.
 */
class ConfirmarRegistroPublico extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nombreAdmin,
        public string $nombreNegocio,
        public string $urlConfirmacion,
        public int $horasVigencia,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirma tu correo para crear tu cuenta');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.confirmar-registro-publico');
    }

    public function attachments(): array
    {
        return [];
    }
}
