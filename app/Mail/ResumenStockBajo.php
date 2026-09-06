<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResumenStockBajo extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $nombreNegocio;

    public array $productos;

    public function __construct(string $nombreNegocio, array $productos)
    {
        $this->nombreNegocio = $nombreNegocio;
        $this->productos = $productos;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Resumen de inventario: productos con stock bajo en '.$this->nombreNegocio,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.resumen-stock-bajo',
            with: [
                'nombreNegocio' => $this->nombreNegocio,
                'productos' => $this->productos,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
