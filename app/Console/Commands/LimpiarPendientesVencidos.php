<?php

namespace App\Console\Commands;

use App\Service\SvcRecuperacionClave;
use App\Service\SvcRegistroPendiente;
use Illuminate\Console\Command;

/**
 * PROCESO DE SISTEMA, sin sesión ni tenant: tablas de plataforma.
 *
 * Borra lo que venció sin usarse y ya no sirve para nada:
 * - registros_pendientes vencidos y sin confirmar (con su clave hasheada);
 * - codigos_recuperacion_clave vencidos y sin usar.
 * Lo vigente, lo confirmado y lo usado no se toca. Corre una vez al día
 * (ver routes/console.php).
 */
class LimpiarPendientesVencidos extends Command
{
    protected $signature = 'auth:limpiar-pendientes-vencidos';

    protected $description = 'Borra registros públicos pendientes y códigos de recuperación de clave vencidos sin usar';

    public function handle(): int
    {
        $registros = (new SvcRegistroPendiente)->borrarVencidos();
        $codigos = (new SvcRecuperacionClave)->borrarVencidos();

        if ($registros === false || $codigos === false) {
            $this->error('No se pudo completar la limpieza. Revisa el log del canal database.');

            return self::FAILURE;
        }

        $this->info('Registros pendientes vencidos borrados: '.$registros);
        $this->info('Códigos de recuperación vencidos borrados: '.$codigos);

        return self::SUCCESS;
    }
}
