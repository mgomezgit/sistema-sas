<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Logging\ProcesadorSinDatosPersonales;
use App\Support\IdPeticion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActivaModulos;
use Tests\TestCase;

/**
 * El código de soporte que ve el usuario (CLI-CREAR-7F3A) lleva el id corto de
 * la petición y se escribe en el canal "database" en ese mismo momento. El
 * procesador central pone ese id, el negocio, el usuario y la ruta en el
 * "extra" de cada línea, así que la línea del código y la de la excepción de
 * la misma petición comparten el id. Nada de eso puede llevar datos personales.
 *
 * Todo pasa por HTTP real (withSession) y escribe en archivos de registro
 * TEMPORALES fuera de storage/logs, que se borran al terminar.
 *
 * ================= MUTACIONES (resultado en el reporte de la tarea) =================
 * M1: se quitó la línea Log::channel('database')->warning('CODIGO DE SOPORTE ...')
 *     de Controller::codigoDeSoporte(): fallaron las pruebas que buscan la
 *     línea del código. Restaurado, pasan.
 * M2: se quitó el id_peticion de ProcesadorSinDatosPersonales::identificadoresDePeticion():
 *     fallaron las pruebas que cruzan el código con la excepción. Restaurado, pasan.
 */
class CodigoSoporteRastreableTest extends TestCase
{
    use ActivaModulos;
    use RefreshDatabase;

    /** Valores conocidos que nunca pueden aparecer en el registro. */
    const NOMBRE = 'Clienta Secreta Del Registro';

    const CORREO = 'clienta.secreta@registro-soporte.test';

    const TELEFONO = '3009998877';

    const CLAVE = 'ClaveSecretaDeSoporte2026';

    /** Formato del id: 4 caracteres del alfabeto sin 0, O, 1, I ni L. */
    const PATRON_ID = '[2-9A-HJKMNP-Z]{4}';

    private string $base;

    private int $negocioA;

    private int $negocioB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'soporte-'.uniqid();

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

        Mail::fake();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = $this->crearNegocio('Negocio A');
        $this->negocioB = $this->crearNegocio('Negocio B');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        foreach (glob($this->base.'*') ?: [] as $archivo) {
            @unlink($archivo);
        }

        parent::tearDown();
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre, 'rubro' => 'spa', 'usuario_registra' => 'test',
            'fecha_registro' => now(), 'estado' => 1,
        ]);
    }

    private function sesionAdmin(int $tenantId): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 999999,
            'usuario' => 'admin.test',
            'nombre_usuario' => 'Admin Test',
            'tenant_id' => $tenantId,
            'id_rol' => 1,
        ];
    }

    /** Hace fallar a propósito todo insert en esa tabla (trigger de SQLite). */
    private function romperInsertsEn(string $tabla): void
    {
        DB::unprepared("CREATE TRIGGER falla_{$tabla} BEFORE INSERT ON {$tabla} BEGIN SELECT RAISE(ABORT, 'fallo provocado por la prueba'); END;");
    }

    private function crearClienteQueFalla()
    {
        return $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/cliente/crear', [
                'nombre' => self::NOMBRE, 'telefono' => self::TELEFONO, 'email' => self::CORREO,
            ]);
    }

    /** El id del código que vio el usuario (falla si el mensaje no lo trae). */
    private function idDelMensaje(string $mensaje, string $codigo): string
    {
        $this->assertMatchesRegularExpression(
            '/indica este código: '.preg_quote($codigo, '/').'-('.self::PATRON_ID.')$/u',
            $mensaje,
            'El código mostrado debe ser '.$codigo.'-ID con un id de 4 caracteres del alfabeto permitido'
        );

        preg_match('/-('.self::PATRON_ID.')$/', $mensaje, $m);

        return $m[1];
    }

    /** Las entradas del registro (una por línea con fecha; la traza va dentro de su entrada). */
    private function entradas(string $sufijo = 'database'): array
    {
        $texto = (string) @file_get_contents($this->base.'-'.$sufijo.'.log');

        return array_values(array_filter(preg_split('/^(?=\[\d{4}-\d{2}-\d{2})/m', $texto), fn ($e) => trim($e) !== ''));
    }

    /** El "extra" (JSON al final de la entrada) que agregó el procesador. */
    private function extra(string $entrada): array
    {
        preg_match('/(\{[^{}]*\})\s*$/', $entrada, $m);

        return isset($m[1]) ? (json_decode($m[1], true) ?? []) : [];
    }

    private function entradaQueContiene(string $texto): ?string
    {
        foreach ($this->entradas() as $entrada) {
            if (str_contains($entrada, $texto)) {
                return $entrada;
            }
        }

        return null;
    }

    private function assertSinDatosPersonales(string $entrada, string $donde): void
    {
        foreach ([self::NOMBRE, self::CORREO, self::TELEFONO, self::CLAVE, '$2y$', 'SQL:'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $entrada, "$donde: el registro contiene un dato que no debe ($prohibido)");
        }
    }

    /* ================= 1) EL CÓDIGO LLEVA EL ID Y SE CRUZA CON LA EXCEPCIÓN ================= */

    public function test_el_codigo_de_sistema_lleva_el_id_y_comparte_id_con_la_excepcion(): void
    {
        $this->romperInsertsEn('clientes');

        $respuesta = $this->crearClienteQueFalla()->assertJsonPath('error', 1);

        $mensaje = $respuesta->json('mensaje');
        $id = $this->idDelMensaje($mensaje, 'CLI-CREAR');

        // El resto del mensaje al usuario no cambió.
        $this->assertStringStartsWith('Ocurrió un problema técnico y la acción no se completó. Vuelve a intentarlo en unos segundos. Si el problema continúa, comunícate con soporte e indica este código: ', $mensaje);

        // (2) La línea del código, con nivel warning y sus identificadores.
        $lineaCodigo = $this->entradaQueContiene('CODIGO DE SOPORTE CLI-CREAR-'.$id);
        $this->assertNotNull($lineaCodigo, 'El registro debe tener la línea "CODIGO DE SOPORTE CLI-CREAR-'.$id.'"');
        $this->assertStringContainsString('.WARNING: CODIGO DE SOPORTE CLI-CREAR-'.$id, $lineaCodigo);
        $this->assertSame([
            'id_peticion' => $id,
            'tenant_id' => $this->negocioA,
            'id_usuario' => 999999,
            'ruta' => 'POST request/cliente/crear',
        ], $this->extra($lineaCodigo), 'El extra lleva SOLO identificadores y la ruta');

        // La línea de la excepción de la MISMA petición tiene el mismo id.
        $lineaExcepcion = $this->entradaQueContiene('fallo provocado por la prueba');
        $this->assertNotNull($lineaExcepcion, 'La excepción debe estar en el registro');
        $this->assertStringContainsString('QueryException', $lineaExcepcion);
        $this->assertSame($id, $this->extra($lineaExcepcion)['id_peticion'] ?? null, 'La excepción y el código deben compartir el id');

        // (3) Ninguna de las dos lleva datos personales.
        $this->assertSinDatosPersonales($lineaCodigo, 'Línea del código');
        $this->assertSinDatosPersonales($lineaExcepcion, 'Línea de la excepción');
    }

    public function test_el_fallo_al_crear_un_usuario_no_deja_correo_nombre_clave_ni_hash(): void
    {
        $this->romperInsertsEn('usuarios');

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/usuario/crear', [
                'usuario' => 'usuario.soporte', 'nombre' => self::NOMBRE, 'email' => self::CORREO,
                'clave' => self::CLAVE, 'tenant_id' => $this->negocioA, 'id_rol' => 1,
            ])
            ->assertJsonPath('error', 1);

        $id = $this->idDelMensaje($respuesta->json('mensaje'), 'USR-CREAR');

        $lineaCodigo = $this->entradaQueContiene('CODIGO DE SOPORTE USR-CREAR-'.$id);
        $lineaExcepcion = $this->entradaQueContiene('fallo provocado por la prueba');

        $this->assertNotNull($lineaCodigo);
        $this->assertNotNull($lineaExcepcion);
        $this->assertSame($id, $this->extra($lineaExcepcion)['id_peticion'] ?? null);

        $this->assertSinDatosPersonales($lineaCodigo, 'Código de usuario');
        $this->assertSinDatosPersonales($lineaExcepcion, 'Excepción de usuario');
        $this->assertStringNotContainsString('usuario.soporte', $lineaExcepcion);
    }

    /* ================= 2) agregarErrorNoDisponible ================= */

    public function test_el_codigo_de_no_disponible_tambien_lleva_id_y_se_registra(): void
    {
        // Un cliente del negocio B, editado desde el A: para A no existe.
        $idClienteB = DB::table('clientes')->insertGetId([
            'tenant_id' => $this->negocioB, 'nombre' => 'De B', 'telefono' => '3000000000',
            'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ]);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/cliente/editar', [
                'id_cliente' => $idClienteB, 'nombre' => self::NOMBRE, 'telefono' => self::TELEFONO,
                'email' => self::CORREO, 'estado' => 1,
            ])
            ->assertJsonPath('error', 1);

        $mensaje = $respuesta->json('mensaje');
        $id = $this->idDelMensaje($mensaje, 'CLI-EDIT');
        $this->assertStringStartsWith('No se pudo completar la acción porque el cliente ya no está disponible. ', $mensaje);

        $lineaCodigo = $this->entradaQueContiene('CODIGO DE SOPORTE CLI-EDIT-'.$id);
        $this->assertNotNull($lineaCodigo);
        $this->assertSame([
            'id_peticion' => $id,
            'tenant_id' => $this->negocioA,
            'id_usuario' => 999999,
            'ruta' => 'POST request/cliente/editar',
        ], $this->extra($lineaCodigo));
        $this->assertSinDatosPersonales($lineaCodigo, 'No disponible');

        // El cliente de B no se tocó.
        $this->assertSame('De B', DB::table('clientes')->where('id_cliente', $idClienteB)->value('nombre'));
    }

    /* ================= 3) EL ID CAMBIA ENTRE PETICIONES ================= */

    public function test_el_id_cambia_entre_peticiones(): void
    {
        $this->romperInsertsEn('clientes');

        $ids = [];

        // Cinco peticiones: que dos ids coincidan por azar es posible (1 en
        // ~900 000), que coincidan los cinco no. Basta con que no sean todos iguales.
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->idDelMensaje($this->crearClienteQueFalla()->json('mensaje'), 'CLI-CREAR');
        }

        $this->assertGreaterThan(1, count(array_unique($ids)), 'Cada petición debe llevar su propio id');

        // Cada código quedó registrado con su propio id.
        foreach ($ids as $id) {
            $this->assertNotNull($this->entradaQueContiene('CODIGO DE SOPORTE CLI-CREAR-'.$id));
        }
    }

    /* ================= 4) ENDPOINT PÚBLICO SIN SESIÓN ================= */

    public function test_un_endpoint_publico_sin_sesion_registra_el_codigo_sin_negocio_ni_usuario(): void
    {
        $this->romperInsertsEn('registros_pendientes');

        $respuesta = $this->postJson('request/registro-publico/crear', [
            'nombre_negocio' => 'Spa Soporte',
            'telefono_contacto' => self::TELEFONO,
            'rubro' => 'spa',
            'nombre' => self::NOMBRE,
            'email' => self::CORREO,
            'clave' => self::CLAVE,
            'confirmar_clave' => self::CLAVE,
        ])->assertOk()->assertJsonPath('error', 1);

        $id = $this->idDelMensaje($respuesta->json('mensaje'), 'REG-PENDIENTE');

        $lineaCodigo = $this->entradaQueContiene('CODIGO DE SOPORTE REG-PENDIENTE-'.$id);
        $this->assertNotNull($lineaCodigo);
        $this->assertSame([
            'id_peticion' => $id,
            'ruta' => 'POST request/registro-publico/crear',
        ], $this->extra($lineaCodigo), 'Sin sesión no hay negocio ni usuario en el extra');

        $lineaExcepcion = $this->entradaQueContiene('fallo provocado por la prueba');
        $this->assertNotNull($lineaExcepcion);
        $this->assertSame($id, $this->extra($lineaExcepcion)['id_peticion'] ?? null);

        $this->assertSinDatosPersonales($lineaCodigo, 'Público, código');
        $this->assertSinDatosPersonales($lineaExcepcion, 'Público, excepción');
        $this->assertStringNotContainsString('Spa Soporte', $lineaExcepcion);
    }

    /* ================= 5) UN COMANDO NO AGREGA ID ================= */

    public function test_un_comando_no_agrega_id_ni_ruta(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 06:30:00'));

        // Un módulo vencido ayer: el comando lo apaga y lo deja en el registro.
        $this->activarModulo($this->negocioA, 'reportes', '2026-11-16');

        Artisan::call('modulos:procesar-vencimientos');

        $linea = $this->entradaQueContiene('VENCIMIENTO: sistema APAGO el modulo "reportes"');
        $this->assertNotNull($linea, 'El comando debía dejar su línea de auditoría');
        $this->assertSame([], $this->extra($linea), 'Un comando no tiene petición: no lleva id, sesión ni ruta');
        $this->assertNull(IdPeticion::actual());
    }

    /* ================= 6) EL ALFABETO ================= */

    public function test_el_id_usa_solo_el_alfabeto_sin_caracteres_ambiguos(): void
    {
        foreach (['0', 'O', '1', 'I', 'L'] as $ambiguo) {
            $this->assertStringNotContainsString($ambiguo, IdPeticion::ALFABETO);
        }

        for ($i = 0; $i < 200; $i++) {
            $this->assertMatchesRegularExpression('/^'.self::PATRON_ID.'$/', IdPeticion::generar());
        }

        // Fuera de una petición, el procesador no agrega nada.
        $this->assertSame([], ProcesadorSinDatosPersonales::identificadoresDePeticion());
    }
}
