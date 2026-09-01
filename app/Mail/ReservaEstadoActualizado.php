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

    public string $nombreNegocio;

    public string $estadoReserva;

    public function __construct(array $reserva, string $nombreNegocio, string $estadoReserva)
    {
        $this->reserva = $reserva;
        $this->nombreNegocio = $nombreNegocio;
        $this->estadoReserva = $estadoReserva;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Actualización de tu reserva en '.$this->nombreNegocio,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reserva-estado-actualizado',
            with: [
                'reserva' => $this->reserva,
                'nombreNegocio' => $this->nombreNegocio,
                'estadoReserva' => $this->estadoReserva,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
