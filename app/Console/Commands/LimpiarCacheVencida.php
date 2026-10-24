<?php

namespace App\Console\Commands;

use App\Service\SvcChatbot;
use Illuminate\Console\Command;

/**
 * PROCESO DE SISTEMA, sin sesión ni tenant: tablas de caché de la plataforma.
 *
 * Borra de cache y cache_locks las filas ya vencidas (expiración anterior a
 * ahora). Lo vigente —conversaciones vivas del chatbot, contadores de los
 * limitadores de peticiones— no se toca. Existe porque la caché tipo database
 * solo borra una fila vencida cuando alguien lee esa misma clave, y una
 * conversación abandonada nunca se vuelve a leer: sin esto, los mensajes de
 * los visitantes quedarían guardados más tiempo del previsto. Corre cada hora
 * (ver routes/console.php).
 */
class LimpiarCacheVencida extends Command
{
    protected $signature = 'chatbot:limpiar-cache-vencida';

    protected $description = 'Borra de la caché y de los bloqueos las filas ya vencidas (conversaciones del chatbot abandonadas)';

    public function handle(): int
    {
        $borradas = (new SvcChatbot)->limpiarCacheVencida();

        if ($borradas === false) {
            $this->error('No se pudo completar la limpieza. Revisa el log del canal database.');

            return self::FAILURE;
        }

        $this->info('Filas vencidas borradas de cache: '.$borradas['cache']);
        $this->info('Filas vencidas borradas de cache_locks: '.$borradas['locks']);

        return self::SUCCESS;
    }
}
