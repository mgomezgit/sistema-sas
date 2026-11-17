<?php

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * "tap" de config/logging.php: agrega a un canal el procesador que quita los
 * valores de columnas, el SQL con bindings y los argumentos de la traza.
 * Se aplica a TODOS los canales de la aplicación (el canal database y el
 * canal por defecto con su stack), en un único punto central.
 */
class SanitizarRegistros
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(new ProcesadorSinDatosPersonales);
    }
}
