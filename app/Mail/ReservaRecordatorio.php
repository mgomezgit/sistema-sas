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

    public string $nombreNegocio;

    public function __construct(array $reserva, string $nombreNegocio)
    {
        $this->reserva = $reserva;
        $this->nombreNegocio = $nombreNegocio;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio: tienes una reserva mañana en '.$this->nombreNegocio,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reserva-recordatorio',
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
