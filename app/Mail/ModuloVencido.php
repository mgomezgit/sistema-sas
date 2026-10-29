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
 * Aviso al administrador: un módulo de pago venció y quedó inactivo.
 *
 * No menciona precios. El texto se pinta escapado en la vista Blade. El contacto
 * para reactivar sale de config/plataforma.php (vacío => texto genérico).
 */
class ModuloVencido extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nombreNegocio,
        public string $nombreModulo,
        public string $vigenteHasta,
        public ?string $colorAcento = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu modulo "'.$this->nombreModulo.'" vencio',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.modulo-vencido',
            with: [
                'nombreNegocio' => $this->nombreNegocio,
                'nombreModulo' => $this->nombreModulo,
                'vigenteHasta' => $this->vigenteHasta,
                'acentoHex' => ColorAcento::hex($this->colorAcento),
                'contacto' => Plataforma::contacto(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
