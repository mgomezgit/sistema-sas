<?php

namespace Tests\Feature;

use App\Http\Controllers\Request\RecuperacionClaveController;
use App\Http\Middleware\VerificarSesion;
use App\Mail\CodigoRecuperacionClave;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Recuperación de clave con código de 6 dígitos.
 *
 * Nota de PHPUnit (ya documentada en SeguridadAccesoTest): postJson/getJson no
 * mandan cookies sin withCredentials(), y el almacén de sesión en memoria
 * sobrevive entre peticiones, así que las pruebas con cookie de sesión usan
 * flushSession() + withCredentials().
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 * Cada una: 16 tests, 15 passed, 1 FAILED; restaurado: 16 passed.
 *
 * MR1 — Sin el límite de intentos de código: el código CORRECTO entró en el
 *       intento 6, tras 5 equivocados ("0 is identical to 1").
 * MR2 — El código vigente buscado sin filtrar por usuario: el código de Ana
 *       cambió la clave de Beto.
 * MR3 — Sin filtrar los códigos ya usados: el mismo código sirvió 2 veces.
 * MR4 — Sin filtrar los vencidos: el código vencido cambió la clave.
 * MR5 — Sin incrementar version_sesion: la sesión abierta antes del cambio
 *       siguió entrando al dashboard (200 en vez de redirigir al login).
 * MR6 — Sin borrar los códigos anteriores al pedir uno nuevo: quedaron 2
 *       códigos sin usar. (El viejo igual se habría rechazado: confirmar()
 *       solo acepta el último pedido, una segunda barrera.)
 * MR7 — Respondiendo "No hay ninguna cuenta con ese correo": falló la
 *       comparación byte a byte.
 * MR8 — Sin throttle en la ruta de solicitar: la sexta solicitud pasó.
 */
class RecuperacionClaveTest extends TestCase
{
    use RefreshDatabase;

    const CLAVE_VIEJA = 'ClaveVieja2026';

    const CLAVE_NUEVA = 'ClaveNueva2026';

    private int $negocio;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocio = DB::table('negocios')->insertGetId([
            'nombre_negocio' => 'Spa Clave',
            'slug' => 'spa-clave',
            'rubro' => 'spa',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $this->crearUsuario('ana@clave.test', $this->negocio, 1);
        $this->crearUsuario('beto@clave.test', $this->negocio, 1);
    }

    /* ================= AYUDANTES ================= */

    private function crearUsuario(string $correo, ?int $tenantId, int $idRol, int $estado = 1): int
    {
        return DB::table('usuarios')->insertGetId([
            'tenant_id' => $tenantId,
            'id_rol' => $idRol,
            'usuario' => $correo,
            'nombre' => ucfirst(strtok($correo, '@')),
            'email' => $correo,
            'clave' => Hash::make(self::CLAVE_VIEJA),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => $estado,
        ]);
    }

    private function solicitar(string $correo)
    {
        return $this->postJson('request/recuperacion/solicitar', ['correo' => $correo]);
    }

    private function confirmar(string $correo, string $codigo, string $clave = self::CLAVE_NUEVA)
    {
        return $this->postJson('request/recuperacion/confirmar', [
            'correo' => $correo,
            'codigo' => $codigo,
            'clave' => $clave,
            'confirmar_clave' => $clave,
        ]);
    }

    /** El código real del último correo encolado a esa dirección. */
    private function codigoEnviadoA(string $correo): string
    {
        $correos = Mail::queued(CodigoRecuperacionClave::class, fn ($c) => $c->hasTo($correo));
        $this->assertNotEmpty($correos, "No se envió ningún código a $correo");

        return $correos->last()->codigo;
    }

    /** Un código de 6 dígitos que NO es el vigente. */
    private function codigoEquivocado(string $correcto): string
    {
        return $correcto === '000000' ? '111111' : '000000';
    }

    private function login(string $correo, string $clave)
    {
        $this->flushSession();

        return $this->postJson('request/autenticacion/login', ['email' => $correo, 'clave' => $clave]);
    }

    private function claveDe(string $correo): string
    {
        return DB::table('usuarios')->where('email', $correo)->value('clave');
    }

    /* ================= 1) EL FLUJO NORMAL ================= */

    public function test_un_codigo_valido_cambia_la_clave_y_se_entra_con_la_nueva(): void
    {
        $this->solicitar('ana@clave.test')->assertJsonPath('error', 0);

        $this->confirmar('ana@clave.test', $this->codigoEnviadoA('ana@clave.test'))
            ->assertJsonPath('error', 0);

        $this->login('ana@clave.test', self::CLAVE_NUEVA)->assertJsonPath('error', 0);
        $this->login('ana@clave.test', self::CLAVE_VIEJA)->assertJsonPath('error', 1);
    }

    public function test_el_codigo_solo_se_guarda_hasheado(): void
    {
        $this->solicitar('ana@clave.test');
        $codigo = $this->codigoEnviadoA('ana@clave.test');

        $fila = (array) DB::table('codigos_recuperacion_clave')->first();

        $this->assertTrue(Hash::check($codigo, $fila['codigo_hash']));
        foreach ($fila as $columna => $valor) {
            $this->assertNotSame($codigo, (string) $valor, "El código aparece en claro en $columna");
        }
    }

    public function test_funciona_tambien_para_un_super_admin(): void
    {
        $this->crearUsuario('super@clave.test', null, 3);

        $this->solicitar('super@clave.test');
        $this->confirmar('super@clave.test', $this->codigoEnviadoA('super@clave.test'))->assertJsonPath('error', 0);

        $this->login('super@clave.test', self::CLAVE_NUEVA)->assertJsonPath('error', 0);
    }

    public function test_la_clave_nueva_debe_tener_al_menos_8_caracteres(): void
    {
        $this->solicitar('ana@clave.test');

        $this->confirmar('ana@clave.test', $this->codigoEnviadoA('ana@clave.test'), 'corta12')
            ->assertJsonPath('error', 1);

        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $this->claveDe('ana@clave.test')));
    }

    /* ================= 2) CÓDIGO USADO, VENCIDO, REEMPLAZADO =================
     * (mutaciones: ver la cabecera del archivo)
     */

    public function test_un_codigo_usado_no_sirve_una_segunda_vez(): void
    {
        $this->solicitar('ana@clave.test');
        $codigo = $this->codigoEnviadoA('ana@clave.test');

        $this->confirmar('ana@clave.test', $codigo)->assertJsonPath('error', 0);
        $this->confirmar('ana@clave.test', $codigo, 'OtraClaveMas2026')->assertJsonPath('error', 1);

        $this->assertTrue(Hash::check(self::CLAVE_NUEVA, $this->claveDe('ana@clave.test')));
    }

    public function test_un_codigo_vencido_es_rechazado(): void
    {
        $this->solicitar('ana@clave.test');
        DB::table('codigos_recuperacion_clave')->update(['fecha_expiracion' => now()->subMinute()]);

        $this->confirmar('ana@clave.test', $this->codigoEnviadoA('ana@clave.test'))->assertJsonPath('error', 1);

        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $this->claveDe('ana@clave.test')));
    }

    public function test_pedir_un_codigo_nuevo_invalida_el_anterior(): void
    {
        $this->solicitar('ana@clave.test');
        $viejo = $this->codigoEnviadoA('ana@clave.test');
        $this->solicitar('ana@clave.test');
        $nuevo = $this->codigoEnviadoA('ana@clave.test');

        // Con 1 en un millón de probabilidad salen iguales: sin esto la prueba
        // no podría distinguirlos.
        if ($viejo === $nuevo) {
            $this->markTestSkipped('Los dos códigos aleatorios coincidieron.');
        }

        $this->assertSame(1, DB::table('codigos_recuperacion_clave')->whereNull('usado_en')->count());

        $this->confirmar('ana@clave.test', $viejo)->assertJsonPath('error', 1);
        $this->confirmar('ana@clave.test', $nuevo)->assertJsonPath('error', 0);
    }

    /* ================= 3) MISMA RESPUESTA EXISTA O NO EL CORREO ================= */

    public function test_la_respuesta_de_solicitar_es_identica_byte_a_byte(): void
    {
        $this->crearUsuario('inactiva@clave.test', $this->negocio, 1, 0);

        $existe = $this->solicitar('ana@clave.test');
        $noExiste = $this->solicitar('nadie@clave.test');
        $inactiva = $this->solicitar('inactiva@clave.test');

        $this->assertSame($existe->getStatusCode(), $noExiste->getStatusCode());
        $this->assertSame($existe->getContent(), $noExiste->getContent());
        $this->assertSame($existe->getContent(), $inactiva->getContent());
        $existe->assertJsonPath('data.mensaje', 'Si el correo está registrado, te enviamos un código.');

        // Solo el que existe y está activo recibió un código.
        Mail::assertQueued(CodigoRecuperacionClave::class, 1);
        Mail::assertQueued(CodigoRecuperacionClave::class, fn ($c) => $c->hasTo('ana@clave.test'));
        $this->assertSame(1, DB::table('codigos_recuperacion_clave')->count());
    }

    /* ================= 4) LÍMITE DE INTENTOS DE CÓDIGO =================
     * (mutación: ver la cabecera del archivo)
     */

    public function test_tras_5_codigos_equivocados_el_sexto_es_rechazado_aunque_sea_el_correcto(): void
    {
        $this->solicitar('ana@clave.test');
        $correcto = $this->codigoEnviadoA('ana@clave.test');

        for ($i = 1; $i <= RecuperacionClaveController::INTENTOS_CONFIRMAR; $i++) {
            $this->confirmar('ana@clave.test', $this->codigoEquivocado($correcto))->assertJsonPath('error', 1);
        }

        $sexto = $this->confirmar('ana@clave.test', $correcto);

        $sexto->assertJsonPath('error', 1);
        $this->assertStringContainsString('Demasiados intentos', $sexto->json('mensaje'));
        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $this->claveDe('ana@clave.test')));
    }

    public function test_hasta_el_quinto_intento_el_correcto_todavia_entra(): void
    {
        $this->solicitar('ana@clave.test');
        $correcto = $this->codigoEnviadoA('ana@clave.test');

        for ($i = 1; $i < RecuperacionClaveController::INTENTOS_CONFIRMAR; $i++) {
            $this->confirmar('ana@clave.test', $this->codigoEquivocado($correcto))->assertJsonPath('error', 1);
        }

        $this->confirmar('ana@clave.test', $correcto)->assertJsonPath('error', 0);
    }

    /* ================= 5) CERRAR LAS SESIONES ABIERTAS =================
     * (mutación: ver la cabecera del archivo)
     */

    public function test_cambiar_la_clave_cierra_las_sesiones_abiertas_de_esa_cuenta(): void
    {
        $cookie = config('session.cookie');

        // Una sesión abierta ANTES de recuperar la clave (login real).
        $idSesionVieja = $this->login('ana@clave.test', self::CLAVE_VIEJA)
            ->assertJsonPath('error', 0)
            ->getCookie($cookie)
            ->getValue();

        // Control: esa sesión funciona.
        $this->flushSession();
        $this->withCookie($cookie, $idSesionVieja)->get('backoffice/dashboard')->assertOk();

        // Desde "otro dispositivo", sin sesión: se recupera la clave.
        $this->flushSession();
        $this->solicitar('ana@clave.test');
        $this->confirmar('ana@clave.test', $this->codigoEnviadoA('ana@clave.test'))->assertJsonPath('error', 0);

        // La sesión vieja deja de servir en la siguiente petición.
        $this->flushSession();
        $this->withCookie($cookie, $idSesionVieja)
            ->get('backoffice/dashboard')
            ->assertRedirect(url('/login'))
            ->assertSessionHas('aviso_login', VerificarSesion::MENSAJE_CLAVE_CAMBIADA);

        $this->flushSession();
        $this->withCredentials()
            ->withCookie($cookie, $idSesionVieja)
            ->getJson('request/negocio/horario')
            ->assertJsonPath('error', 1);

        // Y una sesión nueva, abierta con la clave nueva, sí vale.
        $idSesionNueva = $this->login('ana@clave.test', self::CLAVE_NUEVA)
            ->assertJsonPath('error', 0)
            ->getCookie($cookie)
            ->getValue();

        $this->flushSession();
        $this->withCookie($cookie, $idSesionNueva)->get('backoffice/dashboard')->assertOk();
    }

    public function test_recuperar_la_clave_de_una_cuenta_no_cierra_las_sesiones_de_otra(): void
    {
        $cookie = config('session.cookie');

        $idSesionBeto = $this->login('beto@clave.test', self::CLAVE_VIEJA)->getCookie($cookie)->getValue();

        $this->flushSession();
        $this->solicitar('ana@clave.test');
        $this->confirmar('ana@clave.test', $this->codigoEnviadoA('ana@clave.test'))->assertJsonPath('error', 0);

        $this->flushSession();
        $this->withCookie($cookie, $idSesionBeto)->get('backoffice/dashboard')->assertOk();
    }

    /* ================= 6) AISLAMIENTO ENTRE CUENTAS =================
     * (mutación: ver la cabecera del archivo)
     */

    public function test_el_codigo_de_una_cuenta_no_sirve_para_cambiar_la_clave_de_otra(): void
    {
        $this->solicitar('ana@clave.test');
        $codigoDeAna = $this->codigoEnviadoA('ana@clave.test');

        // El código de Ana, con el correo de Beto (que no pidió ninguno).
        $this->confirmar('beto@clave.test', $codigoDeAna)->assertJsonPath('error', 1);
        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $this->claveDe('beto@clave.test')));

        // Aunque Beto también tenga su propio código vigente.
        $this->solicitar('beto@clave.test');
        $this->confirmar('beto@clave.test', $codigoDeAna === $this->codigoEnviadoA('beto@clave.test') ? '999999' : $codigoDeAna)
            ->assertJsonPath('error', 1);
        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $this->claveDe('beto@clave.test')));

        // El de Ana sigue intacto y le sirve a ella.
        $this->confirmar('ana@clave.test', $codigoDeAna)->assertJsonPath('error', 0);
    }

    /* ================= 7) THROTTLE DE SOLICITUDES ================= */

    public function test_la_sexta_solicitud_del_minuto_desde_la_misma_ip_es_rechazada(): void
    {
        for ($i = 1; $i <= AppServiceProvider::SOLICITUDES_RECUPERACION_POR_MINUTO; $i++) {
            $this->solicitar('ana@clave.test')->assertJsonPath('error', 0);
        }

        $sexta = $this->solicitar('ana@clave.test');
        $sexta->assertJsonPath('error', 1);
        $this->assertStringContainsString('demasiados códigos', $sexta->json('mensaje'));
        Mail::assertQueued(CodigoRecuperacionClave::class, AppServiceProvider::SOLICITUDES_RECUPERACION_POR_MINUTO);

        // Otra IP no comparte el límite.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.90'])
            ->solicitar('ana@clave.test')
            ->assertJsonPath('error', 0);
    }

    /* ================= 8) LA PANTALLA ================= */

    public function test_el_login_enlaza_a_la_pantalla_de_recuperacion(): void
    {
        $this->get('login')->assertOk()->assertSee(url('recuperar-clave'));
        $this->get('recuperar-clave')->assertOk()->assertSee('Enviar código');
    }

    public function test_la_pantalla_precarga_el_correo_y_lo_escapa(): void
    {
        $this->get('recuperar-clave?correo='.urlencode('"><img src=x onerror=alert(1)>'))
            ->assertOk()
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSee('&quot;&gt;&lt;img src=x onerror=alert(1)&gt;', false);
    }
}
