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

    public string $nombreNegocio;

    /**
     * @param  array  $reserva  Datos de la reserva ya resueltos (nombre_cliente,
     *                          nombre_recurso, fecha_reserva, hora_inicio, hora_fin).
     */
    public function __construct(array $reserva, string $nombreNegocio)
    {
        $this->reserva = $reserva;
        $this->nombreNegocio = $nombreNegocio;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmación de tu reserva en '.$this->nombreNegocio,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reserva-confirmada',
            with: [
                'reserva' => $this->reserva,
                'nombreNegocio' => $this->nombreNegocio,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
