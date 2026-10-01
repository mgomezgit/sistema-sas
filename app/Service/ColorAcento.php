<?php

namespace App\Service;

/**
 * Traduce el nombre de acento que un negocio guardó en negocios.color_acento
 * (uno de SvcNegocio::ACENTOS_VALIDOS) a su valor hexadecimal.
 *
 * Existe porque los correos NO pueden leer las variables CSS del backoffice
 * (var(--accent) no funciona en Outlook de escritorio ni en casi ningún
 * cliente de correo): el color tiene que llegar ya resuelto a un hex plano,
 * calculado en PHP antes de pintar la vista.
 *
 * Los 7 hex de HEX son EXACTAMENTE los mismos --accent que ya definen los
 * bloques body.acento-* en resources/views/layout/backoffice.blade.php. Si
 * ese archivo cambia un tono, este mapa se actualiza a mano — no hay una
 * fuente de verdad única compartida entre CSS y PHP sin agregar un paso de
 * build, y duplicar 7 líneas es más simple que eso.
 *
 * No es un Svc (no toca base de datos, no recibe tenant_id, no hay nada que
 * loguear en un catch): es una tabla de traducción pura, así que vive fuera
 * del patrón Svc+Entidad del resto de esta carpeta.
 */
class ColorAcento
{
    /** Acento principal — igual al --accent de cada body.acento-* del backoffice. */
    private const HEX = [
        'oro_rosa' => '#b76e79',
        'dorado' => '#c9a227',
        'amarillo' => '#eab308',
        'naranja' => '#ea580c',
        'rojo' => '#e11d2e',
        'azul' => '#2563eb',
        'verde' => '#16a34a',
    ];

    /**
     * Tono suave, para el fondo del círculo del icono.
     *
     * En el backoffice el equivalente es --accent-soft: rgba(accent, 0.12).
     * Un fondo de celda con rgba() no es confiable en Outlook de escritorio,
     * así que aquí se guarda como un hex SÓLIDO: el resultado exacto de
     * mezclar ese mismo 12% de accent sobre blanco (255,255,255), que es el
     * fondo real del correo — matemáticamente el mismo color que se vería
     * con la capa rgba(), sin depender de que el cliente la soporte.
     * Fórmula por canal: round(canal_accent * 0.12 + 255 * 0.88).
     */
    private const HEX_SUAVE = [
        'oro_rosa' => '#f6eeef',
        'dorado' => '#f9f4e5',
        'amarillo' => '#fcf6e1',
        'naranja' => '#fcebe2',
        'rojo' => '#fbe4e6',
        'azul' => '#e5ecfd',
        'verde' => '#e3f4e9',
    ];

    /** Si llega un nombre que no está en la lista blanca (dato viejo, o null), cae aquí. */
    private const ACENTO_RESPALDO = 'oro_rosa';

    public static function hex(?string $nombreAcento): string
    {
        return self::HEX[$nombreAcento] ?? self::HEX[self::ACENTO_RESPALDO];
    }

    public static function hexSuave(?string $nombreAcento): string
    {
        return self::HEX_SUAVE[$nombreAcento] ?? self::HEX_SUAVE[self::ACENTO_RESPALDO];
    }
}
