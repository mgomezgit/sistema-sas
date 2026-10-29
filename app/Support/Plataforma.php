<?php

namespace App\Support;

/**
 * Datos de contacto de la plataforma para correos y mensajes de bloqueo.
 *
 * Salen de config/plataforma.php, que a su vez los lee del .env (vacíos por
 * defecto). Si no hay ningún contacto configurado, "tiene" es false y quien
 * pinta usa un texto genérico ("comunícate con soporte"). Nunca hay datos
 * personales en el repositorio.
 */
class Plataforma
{
    /**
     * @return array{nombre:string, whatsapp:string, correo:string, tiene:bool}
     */
    public static function contacto(): array
    {
        $whatsapp = trim((string) config('plataforma.contacto_whatsapp', ''));
        $correo = trim((string) config('plataforma.contacto_correo', ''));

        return [
            'nombre' => (string) config('plataforma.nombre', 'la plataforma'),
            'whatsapp' => $whatsapp,
            'correo' => $correo,
            'tiene' => $whatsapp !== '' || $correo !== '',
        ];
    }

    /**
     * Frase de contacto lista para pegar en un mensaje de bloqueo (texto plano).
     * Con contacto: "comunícate con ... al WhatsApp X o al correo Y". Sin
     * contacto: "comunícate con soporte".
     */
    public static function textoContacto(): string
    {
        $c = self::contacto();

        if (! $c['tiene']) {
            return 'comunícate con soporte';
        }

        $medios = [];

        if ($c['whatsapp'] !== '') {
            $medios[] = 'al WhatsApp '.$c['whatsapp'];
        }

        if ($c['correo'] !== '') {
            $medios[] = 'al correo '.$c['correo'];
        }

        return 'comunícate con '.$c['nombre'].' '.implode(' o ', $medios);
    }
}
