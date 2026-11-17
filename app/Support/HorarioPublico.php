<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Formateo del horario y del WhatsApp de la página pública, en UN solo lugar
 * para la página completa (publico/pagina) y la básica (publico/pagina-basica):
 * las dos dicen el horario con las mismas palabras.
 *
 * Solo PRESENTACIÓN de datos que ya llegaron en su lista blanca desde
 * SvcPaginaPublica; aquí no se consulta nada.
 */
class HorarioPublico
{
    const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    const DIAS_CORTOS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    /** Días de atención como números 1..7, únicos y ordenados. */
    public static function diasActivos(?string $diasAtencion): array
    {
        if (empty($diasAtencion)) {
            return [];
        }

        $dias = array_values(array_unique(array_filter(
            array_map('intval', explode(',', $diasAtencion)),
            fn ($dia) => $dia >= 1 && $dia <= 7
        )));
        sort($dias);

        return $dias;
    }

    /** "Lunes a sábado" si son consecutivos; si no, la lista corta de cada uno. */
    public static function resumenDias(array $diasActivos): ?string
    {
        if (count($diasActivos) === 1) {
            return self::DIAS[$diasActivos[0]];
        }

        if (count($diasActivos) > 1) {
            $esConsecutivo = $diasActivos === range($diasActivos[0], end($diasActivos));

            return $esConsecutivo
                ? self::DIAS[$diasActivos[0]].' a '.self::DIAS[end($diasActivos)]
                : implode(', ', array_map(fn ($dia) => self::DIAS_CORTOS[$dia], $diasActivos));
        }

        return null;
    }

    /** Hora "g:i A" (9:00 AM), o null si falta o no se entiende. */
    public static function hora(?string $hora): ?string
    {
        if (empty($hora)) {
            return null;
        }

        try {
            return Carbon::parse($hora)->format('g:i A');
        } catch (\Exception $e) {
            return null;
        }
    }

    /** Enlace wa.me con solo dígitos y el mensaje precargado, o null sin número. */
    public static function whatsappLink(?string $numero, string $mensaje): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $numero);

        return $digitos === '' ? null : 'https://wa.me/'.$digitos.'?text='.rawurlencode($mensaje);
    }
}
