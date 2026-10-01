<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El código de 6 dígitos para recuperar la clave. Solo se envía si el correo
 * pertenece a una cuenta activa; quien lo pide no sabe si se envió o no.
 */
class CodigoRecuperacionClave extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $codigo,
        public int $minutosVigencia,
        public string $urlRecuperacion,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu código para recuperar la clave');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.codigo-recuperacion-clave');
    }

    public function attachments(): array
    {
        return [];
    }
}
