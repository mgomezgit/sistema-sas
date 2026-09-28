<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * El canal "database" es DOBLE USO: ahí caen tanto los catch() de los
 * Services como el registro de auditoría del panel del super admin (quién
 * activó un módulo, quién suspendió un negocio, y cuándo). Antes de esta
 * prueba, correr la suite completa escribía cientos de líneas de prueba
 * mezcladas con ese registro real (se veía literalmente "SUPERADMIN: ...
 * por prueba (id 99)" en storage/logs/database-<fecha>.log).
 *
 * config/logging.php separa el canal por entorno: en testing escribe en
 * storage/logs/testing/, nunca en el archivo real.
 *
 * ================= PRUEBA DE MUTACIÓN =================
 *
 * 1. En config/logging.php se cambió la condición del canal 'database' de
 *        env('APP_ENV') === 'testing' ? 'logs/testing/database.log' : 'logs/database.log'
 *    a simplemente 'logs/database.log' (como si testing escribiera al real).
 * 2. php artisan test --filter=AuditoriaSeparadaTest
 *    Resultado: 2 tests, 0 passed, 2 FAILED — las dos: la ruta ya no
 *    contenía "logs/testing/database.log", y el archivo real SÍ creció
 *    ("Failed asserting that 673105 is identical to 673037"). La marca de
 *    la prueba quedó escrita en el log real de verdad.
 * 3. Se restauró la línea tal cual estaba, y se retiró a mano la línea que
 *    la mutación alcanzó a escribir en el log real, para no dejar rastro.
 * 4. Se volvió a ejecutar: 2 passed.
 */
class AuditoriaSeparadaTest extends TestCase
{
    /** Ruta del archivo de auditoría REAL (el que lee el panel del super admin). */
    private function rutaLogReal(): string
    {
        return storage_path('logs/database-'.date('Y-m-d').'.log');
    }

    /** Ruta del archivo de log de ESTA ejecución de pruebas. */
    private function rutaLogTesting(): string
    {
        $base = pathinfo(config('logging.channels.database.path'));

        return $base['dirname'].'/'.$base['filename'].'-'.date('Y-m-d').'.'.$base['extension'];
    }

    private function tamano(string $ruta): int
    {
        return file_exists($ruta) ? filesize($ruta) : 0;
    }

    public function test_el_canal_database_esta_configurado_para_escribir_fuera_del_log_real(): void
    {
        // Normalizado a "/": storage_path() mezcla separadores en Windows
        // (la base con "\", lo que se le concatena con "/"), y lo único que
        // importa aquí es a qué carpeta apunta, no el estilo de barra.
        $ruta = str_replace('\\', '/', config('logging.channels.database.path'));

        $this->assertStringContainsString(
            'logs/testing/database.log',
            $ruta,
            'En el entorno de pruebas (APP_ENV=testing, fijado en phpunit.xml) el canal database debe apuntar a logs/testing/'
        );
    }

    /**
     * Ver la nota de MUTACIÓN en el encabezado de la clase.
     */
    public function test_una_prueba_que_loguea_no_toca_el_archivo_real(): void
    {
        $tamanoRealAntes = $this->tamano($this->rutaLogReal());

        $marca = 'MARCA_DE_PRUEBA_'.uniqid();
        Log::channel('database')->info($marca);

        $tamanoRealDespues = $this->tamano($this->rutaLogReal());

        $this->assertSame(
            $tamanoRealAntes,
            $tamanoRealDespues,
            'El archivo real de auditoría no puede crecer al correr pruebas'
        );

        $contenidoReal = file_exists($this->rutaLogReal()) ? file_get_contents($this->rutaLogReal()) : '';
        $this->assertStringNotContainsString($marca, $contenidoReal, 'La marca de la prueba no puede aparecer en el log real');

        $contenidoTesting = file_get_contents($this->rutaLogTesting());
        $this->assertStringContainsString($marca, $contenidoTesting, 'La marca sí debe quedar en el log de testing');
    }
}
