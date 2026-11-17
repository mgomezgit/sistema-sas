<?php

namespace App\Support;

use App\Models\ModuloPlataforma;

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

    /**
     * El WhatsApp de la plataforma normalizado a SOLO dígitos (lo que exige
     * wa.me). Un número guardado como "+57 300 123 4567" sale "573001234567".
     * Vacío si no hay WhatsApp configurado.
     */
    public static function whatsappDigitos(): string
    {
        return preg_replace('/\D+/', '', (string) config('plataforma.contacto_whatsapp', ''));
    }

    /**
     * Enlace wa.me con un mensaje precargado, o cadena vacía si no hay WhatsApp
     * configurado. El mensaje se codifica para URL; quien pinta el enlace en
     * una vista debe escaparlo igual que cualquier atributo.
     */
    public static function whatsappLink(string $mensaje = ''): string
    {
        $digitos = self::whatsappDigitos();

        if ($digitos === '') {
            return '';
        }

        $url = 'https://wa.me/'.$digitos;

        if ($mensaje !== '') {
            $url .= '?text='.rawurlencode($mensaje);
        }

        return $url;
    }

    /**
     * Mensaje precargado del WhatsApp para pedir un módulo de pago. Lo usan la
     * pantalla "no disponible" y la tarjeta de Correos en Configuración, para
     * que el spa mande siempre el mismo texto. Texto plano sin codificar
     * (whatsappLink lo codifica); el plan va solo si existe.
     */
    public static function mensajeWhatsappModulo(string $nombreNegocio, ?string $plan, string $nombreModulo): string
    {
        return $plan !== null
            ? 'Hola, soy del spa '.$nombreNegocio.' y quiero el plan '.$plan.' ('.$nombreModulo.').'
            : 'Hola, soy del spa '.$nombreNegocio.' y quiero activar '.$nombreModulo.'.';
    }

    /**
     * Todo lo que pinta un candado de módulo de pago en el panel (tarjetas de
     * Configuración, pestaña Banners, campo de Recursos): nombre del catálogo,
     * plan, WhatsApp con el mensaje compartido, correo de contacto y el enlace
     * a la pantalla "no disponible". Solo textos de la plataforma y el nombre
     * del propio negocio (que viaja codificado en el enlace de WhatsApp).
     *
     * @return array{clave:string, nombre:string, plan:?string, whatsapp_link:string, correo:string, tiene_contacto:bool, url_no_disponible:string}
     */
    public static function datosCandado(string $clave, ?string $nombreNegocio): array
    {
        $textos = self::modulo($clave);
        $contacto = self::contacto();
        $nombre = (string) (ModuloPlataforma::where('clave', $clave)->value('nombre') ?? $clave);

        return [
            'clave' => $clave,
            'nombre' => $nombre,
            'plan' => $textos['plan'],
            'whatsapp_link' => self::whatsappLink(self::mensajeWhatsappModulo($nombreNegocio ?? 'mi negocio', $textos['plan'], $nombre)),
            'correo' => $contacto['correo'],
            'tiene_contacto' => $contacto['tiene'],
            'url_no_disponible' => url('backoffice/modulo/'.$clave),
        ];
    }

    /**
     * Textos de presentación de un módulo (plan, descripción, destino) desde
     * config/plataforma.php. Devuelve valores por defecto seguros si la clave
     * no está configurada.
     *
     * @return array{plan:?string, descripcion:string, destino:?string}
     */
    public static function modulo(string $clave): array
    {
        $datos = config('plataforma.modulos.'.$clave, []);

        return [
            'plan' => $datos['plan'] ?? null,
            'descripcion' => $datos['descripcion'] ?? '',
            'destino' => $datos['destino'] ?? null,
        ];
    }
}
