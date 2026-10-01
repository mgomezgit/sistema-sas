<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservaConfirmada extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $reserva;

    public array $negocio;

    /**
     * @param  array  $reserva  Datos de la reserva ya resueltos (nombre_cliente,
     *                          nombre_recurso, fecha_reserva, hora_inicio, hora_fin).
     * @param  array  $negocio  Datos del negocio que ya se resolvieron para pintar el
     *                          correo: nombre_negocio, color_acento (el nombre guardado,
     *                          lo traduce ColorAcento en la vista), slug (para el botón;
     *                          si falta, la vista omite el botón) y politica_cancelacion.
     */
    public function __construct(array $reserva, array $negocio)
    {
        $this->reserva = $reserva;
        $this->negocio = $negocio;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmación de tu reserva en '.($this->negocio['nombre_negocio'] ?? ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reserva-confirmada',
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
