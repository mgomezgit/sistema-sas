<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso AL NEGOCIO de que llegó una solicitud desde su página pública.
 *
 * Nótese a quién va: al administrador, no al cliente. El cliente NO recibe
 * ningún correo al pedir la cita, porque todavía no hay nada confirmado; se
 * entera cuando el negocio acepta o rechaza la solicitud, y de eso ya se
 * encarga ReservaEstadoActualizado al cambiarle el estado.
 */
class NuevaSolicitudPublica extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $solicitud;

    public string $nombreNegocio;

    /**
     * @param  array  $solicitud  Datos ya resueltos de la solicitud
     *                            (nombre_cliente, telefono_cliente, nombre_recurso,
     *                            fecha_reserva, hora_inicio, hora_fin, notas).
     */
    public function __construct(array $solicitud, string $nombreNegocio)
    {
        $this->solicitud = $solicitud;
        $this->nombreNegocio = $nombreNegocio;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nueva solicitud de cita en '.$this->nombreNegocio,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.nueva-solicitud-publica',
            with: [
                'solicitud' => $this->solicitud,
                'nombreNegocio' => $this->nombreNegocio,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
