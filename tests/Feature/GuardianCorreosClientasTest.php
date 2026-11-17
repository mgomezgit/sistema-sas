<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guardián de los correos a la clienta (módulo de pago "correos").
 *
 * La decisión de si un correo a la clienta sale vive en DOS puntos: los
 * correos por evento en SvcNotificacionReserva y el recordatorio en el comando
 * reservas:enviar-recordatorios, los dos con SvcModulo::estaActivo. Un tercer
 * punto que despache ReservaConfirmada, ReservaEstadoActualizado o
 * ReservaRecordatorio se saltaría el módulo. Esta prueba recorre app/ y
 * routes/ y falla si cualquier otro archivo los menciona en CÓDIGO (los
 * comentarios se ignoran con el tokenizador de PHP); las propias clases de
 * app/Mail quedan fuera.
 *
 * ================= MUTACIÓN (resultado en el reporte de la tarea) =================
 * M5: se agregó Mail::to(...)->queue(new ReservaConfirmada(...)) directo en
 * ReservaController: falló test_solo_los_dos_puntos_conocidos_despachan_correos_a_la_clienta
 * nombrando ese archivo. Restaurado, pasa.
 */
class GuardianCorreosClientasTest extends TestCase
{
    const MAILABLES = ['ReservaConfirmada', 'ReservaEstadoActualizado', 'ReservaRecordatorio'];

    /** Únicos archivos que pueden despacharlos, con su motivo. */
    const PUNTOS_DE_DESPACHO = [
        'app/Service/SvcNotificacionReserva.php' => 'Correos por evento (reserva creada y cambio de estado), con el control del módulo.',
        'app/Console/Commands/EnviarRecordatoriosReservas.php' => 'Recordatorio del día anterior, con el control del módulo por negocio.',
    ];

    private function codigoSinComentarios(string $relativo): string
    {
        $codigo = '';

        foreach (token_get_all(file_get_contents(base_path($relativo))) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $codigo .= $token[1];
            } else {
                $codigo .= $token;
            }
        }

        return $codigo;
    }

    private function archivos(): array
    {
        $archivos = [];

        foreach (['app', 'routes'] as $dir) {
            foreach (File::allFiles(base_path($dir)) as $archivo) {
                $relativo = $dir.'/'.str_replace('\\', '/', $archivo->getRelativePathname());

                if ($archivo->getExtension() === 'php' && ! str_starts_with($relativo, 'app/Mail/')) {
                    $archivos[] = $relativo;
                }
            }
        }

        return $archivos;
    }

    public function test_solo_los_dos_puntos_conocidos_despachan_correos_a_la_clienta(): void
    {
        $patron = '/\b(?:'.implode('|', self::MAILABLES).')\b/';
        $indebidos = [];

        foreach ($this->archivos() as $archivo) {
            if (array_key_exists($archivo, self::PUNTOS_DE_DESPACHO)) {
                continue;
            }

            if (preg_match($patron, $this->codigoSinComentarios($archivo))) {
                $indebidos[] = $archivo;
            }
        }

        $this->assertSame([], $indebidos, 'Los correos a la clienta solo se despachan desde SvcNotificacionReserva y el comando de recordatorios (control del módulo "correos"). Los despacha además: '.implode(', ', $indebidos));
    }

    public function test_los_dos_puntos_siguen_despachando_y_comprobando_el_modulo(): void
    {
        $servicio = $this->codigoSinComentarios('app/Service/SvcNotificacionReserva.php');
        $this->assertStringContainsString('new ReservaConfirmada(', $servicio);
        $this->assertStringContainsString('new ReservaEstadoActualizado(', $servicio);
        $this->assertSame(2, substr_count($servicio, 'if (! $this->correosActivos($tenantId))'), 'Cada correo por evento comprueba el módulo');

        $comando = $this->codigoSinComentarios('app/Console/Commands/EnviarRecordatoriosReservas.php');
        $this->assertStringContainsString('new ReservaRecordatorio(', $comando);
        $this->assertStringContainsString('estaActivo(', $comando);
    }
}
