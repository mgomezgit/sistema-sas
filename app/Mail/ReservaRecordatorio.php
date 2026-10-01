<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservaRecordatorio extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $reserva;

    public array $negocio;

    /**
     * @param  array  $negocio  nombre_negocio, color_acento, slug, politica_cancelacion.
     *                          Ver ReservaConfirmada para el detalle de cada campo.
     */
    public function __construct(array $reserva, array $negocio)
    {
        $this->reserva = $reserva;
        $this->negocio = $negocio;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio: tienes una reserva mañana en '.($this->negocio['nombre_negocio'] ?? ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reserva-recordatorio',
            with: [
                'reserva' => $this->reserva,
                'negocio' => $this->negocio,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
