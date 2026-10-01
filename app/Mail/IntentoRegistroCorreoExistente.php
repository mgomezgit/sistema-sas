<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alguien intentó registrarse con un correo que YA tiene cuenta activa. Va al
 * dueño real de ese correo (nunca a quien llenó el formulario, que recibe la
 * misma respuesta que un registro nuevo), con el camino para entrar.
 *
 * $urlRecuperarClave: la pantalla "Olvidé mi contraseña" con el correo del
 * dueño precargado (RegistroPublicoController siempre la manda). Sigue siendo
 * opcional solo como respaldo: sin URL, el correo indica que se pida a soporte.
 */
class IntentoRegistroCorreoExistente extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $urlLogin,
        public ?string $urlRecuperarClave = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Alguien intentó crear una cuenta con tu correo');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.intento-registro-correo-existente');
    }

    public function attachments(): array
    {
        return [];
    }
}
