<?php

namespace App\Support;

use Illuminate\Http\Request;
use Throwable;

/**
 * Id corto de la petición HTTP: 4 caracteres en mayúscula de un alfabeto sin
 * caracteres ambiguos (sin 0, O, 1, I ni L), para que el spa lo pueda dictar
 * por teléfono o WhatsApp sin confundirse.
 *
 * Lo genera Controller::callAction en cada petición y vive como ATRIBUTO de esa
 * petición (no en el contenedor): así nunca se arrastra de una petición a otra
 * en un proceso de larga vida. En comandos y procesos sin petición no hay id.
 *
 * Lo usan el código de soporte que ve el usuario (USR-CREAR-7F3A) y el
 * procesador central de registros, que lo pone en el "extra" de cada línea:
 * así el código que reporta el spa lleva a la excepción de esa misma petición.
 */
class IdPeticion
{
    const ALFABETO = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    const LARGO = 4;

    const ATRIBUTO = 'id_peticion';

    public static function generar(): string
    {
        $id = '';

        for ($i = 0; $i < self::LARGO; $i++) {
            $id .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return $id;
    }

    /** Asigna un id nuevo a la petición y lo devuelve. */
    public static function asignar(Request $request): string
    {
        $id = self::generar();
        $request->attributes->set(self::ATRIBUTO, $id);

        return $id;
    }

    /** El id de la petición en curso, o null si no hay (comandos, procesos de sistema). */
    public static function actual(): ?string
    {
        try {
            if (! app()->bound('request')) {
                return null;
            }

            $id = app('request')->attributes->get(self::ATRIBUTO);

            return is_string($id) ? $id : null;
        } catch (Throwable) {
            return null;
        }
    }
}
