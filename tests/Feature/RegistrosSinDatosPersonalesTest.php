<?php

namespace Tests\Feature;

use App\Logging\ProcesadorSinDatosPersonales;
use App\Service\SvcUsuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Los registros nunca guardan valores de columnas, SQL con bindings ni
 * argumentos de la traza (App\Logging\ProcesadorSinDatosPersonales, aplicado
 * con "tap" a todos los canales en config/logging.php).
 *
 * Cada prueba escribe en archivos de registro TEMPORALES fuera de storage/logs
 * y los borra al terminar.
 *
 * ================= MUTACIÓN (resultado en el reporte de la tarea) =================
 * Se quitó el tap de config/logging.php: fallaron las pruebas del fallo desde
 * el Service, del mensaje de MySQL y de la petición HTTP (el registro volvió a
 * contener correo, nombre, hash y "SQL:"). Restaurado, pasan.
 */
class RegistrosSinDatosPersonalesTest extends TestCase
{
    use RefreshDatabase;

    const CORREO = 'dato.personal@registro-prueba.test';

    const NOMBRE = 'Nombre Personal De Prueba';

    private string $base;

    private string $hash;

    private int $negocio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'registro-'.uniqid();

        // Canal database y canal por defecto (stack -> single) a archivos
        // temporales; el "tap" de config/logging.php se conserva.
        config([
            'logging.channels.database.driver' => 'single',
            'logging.channels.database.path' => $this->base.'-database.log',
            'logging.default' => 'stack',
            'logging.channels.stack.channels' => ['single'],
            'logging.channels.single.path' => $this->base.'-app.log',
        ]);

        foreach (['database', 'single', 'stack'] as $canal) {
            Log::forgetChannel($canal);
        }

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocio = DB::table('negocios')->insertGetId([
            'nombre_negocio' => 'Negocio', 'rubro' => 'spa', 'usuario_registra' => 'test',
            'fecha_registro' => now(), 'estado' => 1,
        ]);

        $this->hash = Hash::make('clave-de-prueba-registro');

        // Cuenta activa con ese correo: el siguiente insert con el mismo correo
        // viola la unicidad entre cuentas activas.
        DB::table('usuarios')->insert([
            'tenant_id' => $this->negocio, 'id_rol' => 1, 'usuario' => 'previo', 'nombre' => 'Previo',
            'email' => self::CORREO, 'clave' => $this->hash, 'usuario_registra' => 'test',
            'fecha_registro' => now(), 'estado' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->base.'*') ?: [] as $archivo) {
            @unlink($archivo);
        }

        parent::tearDown();
    }

    private function leer(string $sufijo): string
    {
        return (string) @file_get_contents($this->base.'-'.$sufijo.'.log');
    }

    private function assertSinDatosPersonales(string $registro, string $donde): void
    {
        $this->assertNotSame('', $registro, "$donde: el registro debía escribirse");
        $this->assertStringNotContainsString(self::CORREO, $registro, "$donde: contiene el correo");
        $this->assertStringNotContainsString(self::NOMBRE, $registro, "$donde: contiene el nombre");
        $this->assertStringNotContainsString($this->hash, $registro, "$donde: contiene el hash de la clave");
        $this->assertStringNotContainsString('SQL:', $registro, "$donde: contiene el SQL");
        $this->assertDoesNotMatchRegularExpression("/^#\d+ .*\('/m", $registro, "$donde: la traza trae argumentos");
    }

    /* ================= 1) FALLO CAPTURADO EN UN SERVICE (canal database) ================= */

    public function test_un_insert_fallido_en_el_service_no_deja_datos_personales_en_el_registro(): void
    {
        $id = app(SvcUsuario::class)->crearConClaveHasheada([
            'tenant_id' => $this->negocio, 'id_rol' => 1, 'usuario' => 'otro', 'nombre' => self::NOMBRE,
            'email' => self::CORREO, 'clave' => $this->hash, 'estado' => 1,
        ]);

        $this->assertFalse($id, 'El insert debía fallar por la unicidad del correo');

        $registro = $this->leer('database');

        $this->assertSinDatosPersonales($registro, 'Service');

        // Se conserva lo útil para diagnosticar. La clase real es la subclase
        // de QueryException que Laravel usa para las violaciones de unicidad.
        $this->assertStringContainsString('Illuminate\Database\UniqueConstraintViolationException', $registro);
        $this->assertStringContainsString('SQLSTATE[23000]', $registro);
        $this->assertStringContainsString('UNIQUE constraint failed: usuarios.email_activo_unico', $registro, 'Se conserva la restricción');
        $this->assertStringContainsString('Connection.php', $registro, 'Se conserva el archivo de la excepción');
        $this->assertMatchesRegularExpression('/SvcUsuario\.php\(\d+\)/', $registro, 'Se conserva el archivo y la línea del Service en la traza');
    }

    public function test_el_mensaje_de_mysql_conserva_codigo_y_llave_pero_no_el_valor(): void
    {
        $driver = new \PDOException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '".self::CORREO."' for key 'usuarios.usuarios_email_activo_unico_unique'"
        );

        Log::channel('database')->info(new QueryException(
            'mysql',
            'insert into `usuarios` (`nombre`, `email`, `clave`) values (?, ?, ?)',
            [self::NOMBRE, self::CORREO, $this->hash],
            $driver
        ));

        $registro = $this->leer('database');

        $this->assertSinDatosPersonales($registro, 'MySQL');
        $this->assertStringContainsString("1062 Duplicate entry '[omitido]' for key 'usuarios.usuarios_email_activo_unico_unique'", $registro);
        $this->assertStringContainsString('SQLSTATE[23000]', $registro);
        $this->assertStringContainsString('Illuminate\Database\QueryException', $registro);
    }

    /* ================= 2) FALLO NO CAPTURADO EN UNA PETICIÓN HTTP (canal por defecto) ================= */

    public function test_una_excepcion_no_capturada_en_http_no_deja_datos_personales_en_el_registro(): void
    {
        Route::get('/__prueba_registro_fallo', function () {
            DB::table('usuarios')->insert([
                'tenant_id' => $this->negocio, 'id_rol' => 1, 'usuario' => 'otro', 'nombre' => self::NOMBRE,
                'email' => self::CORREO, 'clave' => $this->hash, 'estado' => 1,
            ]);
        });

        $this->get('/__prueba_registro_fallo')->assertStatus(500);

        $registro = $this->leer('app');

        $this->assertSinDatosPersonales($registro, 'HTTP');
        // En el canal por defecto la excepción va dentro del JSON del contexto,
        // con las barras invertidas escapadas; se busca el nombre corto.
        $this->assertStringContainsString('UniqueConstraintViolationException(code: 23000)', $registro);
        $this->assertStringContainsString('SQLSTATE[23000]', $registro);
        $this->assertStringContainsString('(code: 23000)', $registro);
        $this->assertStringContainsString('Connection.php', $registro);
    }

    /* ================= 3) LOS REGISTROS QUE NO SON EXCEPCIONES SALEN IGUAL ================= */

    public function test_las_lineas_de_auditoria_salen_intactas(): void
    {
        $lineas = [
            'SUPERADMIN: modulo "comisiones" ACTIVADO en el negocio 7 por super (id 1) el 2026-11-17 10:00:00',
            'Cascada de baja: al desactivar el usuario 12 se desactivó automáticamente su empleado 4 (negocio 7).',
            'VENCIMIENTO: sistema APAGO el modulo "ia" del negocio 3 (vigente_hasta 2026-11-16) el 2026-11-17 06:30:00',
            "Baja de cliente 'Ana' (id 5) en el negocio 7: 2 reservas futuras canceladas.",
        ];

        foreach ($lineas as $linea) {
            Log::channel('database')->info($linea);
        }

        $registro = $this->leer('database');

        foreach ($lineas as $linea) {
            $this->assertStringContainsString($linea, $registro, 'La línea de auditoría debe salir sin cambios');
        }
    }

    /* ================= REGLAS DEL PROCESADOR ================= */

    public function test_el_procesador_quita_argumentos_de_la_traza_y_valores_de_otros_drivers(): void
    {
        $texto = implode("\n", [
            "#0 C:\\app\\Connection.php(838): Illuminate\\Database\\Connection->runQueryCallback('insert into `us...', Array, Object(Closure))",
            "#1 [internal function]: App\\Foo->bar('dato.personal@...')",
            "#2 C:\\app\\X.php(10): {closure:App\\Foo::baz():12}('secreto')",
            '#3 {main}',
            "SQLSTATE[22007]: Invalid datetime format: 1366 Incorrect integer value: 'texto-secreto' for column 'edad' at row 1",
            'SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint "u_email" DETAIL:  Key (email)=(dato.personal@x.test) already exists.',
        ]);

        $limpio = ProcesadorSinDatosPersonales::limpiarTexto($texto);

        $this->assertStringContainsString('#0 C:\\app\\Connection.php(838): Illuminate\\Database\\Connection->runQueryCallback()', $limpio);
        $this->assertStringContainsString('#1 [internal function]: App\\Foo->bar()', $limpio);
        $this->assertStringContainsString('#3 {main}', $limpio);
        $this->assertStringNotContainsString('secreto', $limpio);
        $this->assertStringNotContainsString('dato.personal', $limpio);
        $this->assertStringContainsString("Incorrect integer value: '[omitido]' for column 'edad'", $limpio);
        $this->assertStringContainsString('Key (email)=([omitido]) already exists', $limpio);
    }
}
