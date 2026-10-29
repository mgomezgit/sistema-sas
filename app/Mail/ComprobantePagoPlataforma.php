<?php

namespace App\Mail;

use App\Service\ColorAcento;
use App\Support\Plataforma;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Comprobante de pago de la plataforma. NO es una factura: el pie lo dice.
 *
 * Se envía a los administradores activos del negocio tras confirmar la
 * transacción. Todo el texto se pinta escapado en la vista. No lleva la palabra
 * "Factura" como título.
 *
 * @param  array  $pago  Datos ya formateados (SvcPagoPlataforma::paraComprobante).
 */
class ComprobantePagoPlataforma extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public array $pago)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Comprobante de pago '.$this->pago['consecutivo'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.comprobante-pago-plataforma',
            with: [
                'pago' => $this->pago,
                'acentoHex' => ColorAcento::hex($this->pago['color_acento'] ?? null),
                'contacto' => Plataforma::contacto(),
                'nombrePlataforma' => (string) config('plataforma.nombre', 'la plataforma'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
