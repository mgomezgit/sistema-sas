<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservaEstadoActualizado extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $reserva;

    public array $negocio;

    public string $estadoReserva;

    /**
     * @param  array  $negocio  nombre_negocio, color_acento, slug, politica_cancelacion.
     *                          Ver ReservaConfirmada para el detalle de cada campo.
     */
    public function __construct(array $reserva, array $negocio, string $estadoReserva)
    {
        $this->reserva = $reserva;
        $this->negocio = $negocio;
        $this->estadoReserva = $estadoReserva;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Actualización de tu reserva en '.($this->negocio['nombre_negocio'] ?? ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reserva-estado-actualizado',
            with: [
                'reserva' => $this->reserva,
                'negocio' => $this->negocio,
                'estadoReserva' => $this->estadoReserva,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
