<?php

namespace Tests\Feature;

use App\Http\Controllers\AutenticacionController;
use App\Http\Middleware\VerificarSesion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Endurecimiento del acceso: fuerza bruta en el login, largo mínimo de clave,
 * fijación de sesión, logout y corte de sesión al desactivar a una persona.
 *
 * Sobre las pruebas de cookie de sesión: en PHPUnit el almacén de sesión en
 * memoria sobrevive de una petición a otra dentro de la misma prueba, así que
 * "mandar la cookie vieja" no probaría nada por sí solo (la petición vería
 * igual los datos en memoria). Por eso, antes de cada petición con una cookie
 * concreta se vacía la memoria con flushSession(): la petición solo ve lo que
 * el almacén tiene guardado bajo el ID de esa cookie, como en un navegador.
 *
 * Y otra trampa de PHPUnit que ya mordió aquí: postJson()/getJson() NO
 * mandan cookies salvo que se llame withCredentials(). Toda prueba de este
 * archivo que mande una cookie por JSON lo usa.
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * M1 — Sin el RateLimiter del login (sin el chequeo previo ni los hit()):
 *      18 tests, 14 passed, 4 FAILED — el sexto intento, el intento 30 (la
 *      clave correcta ENTRÓ tras 29 fallos: "0 is identical to 1"), otro
 *      correo misma IP, y el límite por IP. Restaurado: 18 passed.
 * M2 — Sin el chequeo de persona desactivada en VerificarSesion: 18 tests,
 *      16 passed, 2 FAILED — usuario y empleado desactivados siguieron con
 *      la sesión viva ("received 200" en vez de la redirección al login).
 *      Restaurado: 18 passed.
 * M3 — Sin session()->regenerate() en el login. PRIMER intento: las 18
 *      pasaron igual — la prueba no servía: usaba postJson() sin
 *      withCredentials(), la cookie plantada nunca llegaba y Laravel creaba
 *      un ID al azar de todos modos. Corregida la prueba y repetida la
 *      mutación: 18 tests, 17 passed, 1 FAILED — "El login debe emitir un ID
 *      de sesión nuevo". Restaurado: 18 passed.
 * M4 — Logout con solo flush() en vez de invalidate() + regenerateToken():
 *      18 tests, 17 passed, 1 FAILED — el ID viejo seguía vivo en el almacén
 *      (se leyó '{"_previous":...}' donde debía estar vacío). Restaurado:
 *      18 passed.
 * M7 — (al agregar el cierre de sesión desde SvcUsuario::editar()) Sin el
 *      increment('version_sesion') cuando editar() cambia la clave: 20 tests,
 *      19 passed, 1 FAILED — la sesión abierta antes del cambio siguió viva
 *      ("received 200" en vez de la redirección al login). Restaurado:
 *      20 passed.
 * M8 — (al agregar el hash falso contra tiempo) Volviendo a
 *      "! empty($usuario) && Hash::check(...)" (Hash::check ya no se llama
 *      para un correo inexistente): 22 tests, 21 passed, 1 FAILED —
 *      test_hash_check_se_llama_una_vez_para_un_correo_inexistente ("should
 *      be called at least 1 times but called 0 times"). Restaurado: 22 passed.
 */
class SeguridadAccesoTest extends TestCase
{
    use RefreshDatabase;

    const CLAVE = 'ClaveCorrecta2026';

    private int $negocio;

    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocio = DB::table('negocios')->insertGetId([
            'nombre_negocio' => 'Spa Seguro',
            'slug' => 'spa-seguro',
            'rubro' => 'spa',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $this->admin = $this->crearUsuario('admin.seguro', 'admin@seguro.test', 1);
    }

    /* ================= AYUDANTES ================= */

    private function crearUsuario(string $usuario, string $email, int $idRol): int
    {
        return DB::table('usuarios')->insertGetId([
            'tenant_id' => $this->negocio,
            'id_rol' => $idRol,
            'usuario' => $usuario,
            'nombre' => ucfirst($usuario),
            'email' => $email,
            'clave' => Hash::make(self::CLAVE),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function login(string $email, string $clave)
    {
        return $this->postJson('request/autenticacion/login', ['email' => $email, 'clave' => $clave]);
    }

    private function fallarLogin(string $email, int $veces): void
    {
        for ($i = 1; $i <= $veces; $i++) {
            $this->login($email, 'clave-equivocada-'.$i)->assertJsonPath('error', 1);
        }
    }

    private function sesionDe(int $idUsuario, int $idRol, ?int $idEmpleado = null): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => $idUsuario,
            'usuario' => 'x',
            'nombre_usuario' => 'X',
            'tenant_id' => $this->negocio,
            'id_rol' => $idRol,
            'id_empleado' => $idEmpleado,
        ];
    }

    private function nombreCookieSesion(): string
    {
        return config('session.cookie');
    }

    /* ================= 1) FUERZA BRUTA EN EL LOGIN (mutación M1 arriba) ================= */

    public function test_el_sexto_intento_con_el_mismo_correo_es_rechazado_aunque_la_clave_sea_correcta(): void
    {
        $this->fallarLogin('admin@seguro.test', AutenticacionController::INTENTOS_POR_CORREO_E_IP);

        $respuesta = $this->login('admin@seguro.test', self::CLAVE);

        $respuesta->assertJsonPath('error', 1);
        $this->assertStringContainsString('Demasiados intentos', $respuesta->json('mensaje'));
        $this->assertNull(session('app_sesion'), 'Bloqueado no puede dejar una sesión iniciada');
    }

    /** Un ataque sostenido: 29 claves equivocadas y la correcta en el intento 30. */
    public function test_el_intento_30_con_la_clave_correcta_sigue_bloqueado(): void
    {
        $this->fallarLogin('admin@seguro.test', 29);

        $this->login('admin@seguro.test', self::CLAVE)->assertJsonPath('error', 1);
        $this->assertNull(session('app_sesion'));
    }

    public function test_hasta_el_quinto_intento_la_clave_correcta_todavia_entra(): void
    {
        $this->fallarLogin('admin@seguro.test', AutenticacionController::INTENTOS_POR_CORREO_E_IP - 1);

        $this->login('admin@seguro.test', self::CLAVE)->assertJsonPath('error', 0);
    }

    public function test_otro_correo_desde_la_misma_ip_tiene_su_propio_limite(): void
    {
        $this->crearUsuario('otra.persona', 'otra@seguro.test', 1);

        $this->fallarLogin('admin@seguro.test', AutenticacionController::INTENTOS_POR_CORREO_E_IP);
        $this->login('admin@seguro.test', self::CLAVE)->assertJsonPath('error', 1);

        // Misma IP, otro correo: su contador está intacto.
        $this->login('otra@seguro.test', self::CLAVE)->assertJsonPath('error', 0);
    }

    public function test_el_limite_por_ip_frena_a_quien_prueba_muchos_correos_distintos(): void
    {
        // 20 correos distintos, uno cada uno: ninguno llega a su propio
        // límite de 5, pero la IP sí llega al suyo.
        for ($i = 1; $i <= AutenticacionController::INTENTOS_POR_IP; $i++) {
            $this->login('prueba'.$i.'@inventado.test', 'lo-que-sea')->assertJsonPath('error', 1);
        }

        $bloqueado = $this->login('admin@seguro.test', self::CLAVE);
        $bloqueado->assertJsonPath('error', 1);
        $this->assertStringContainsString('Demasiados intentos', $bloqueado->json('mensaje'));

        // Desde otra IP, esa misma cuenta entra sin problema.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->login('admin@seguro.test', self::CLAVE)
            ->assertJsonPath('error', 0);
    }

    /**
     * El bloqueo no puede servir para averiguar si un correo está registrado:
     * responde igual para uno que existe y para uno inventado.
     */
    public function test_el_bloqueo_responde_igual_para_un_correo_real_y_uno_inventado(): void
    {
        $this->fallarLogin('admin@seguro.test', 5);
        $this->fallarLogin('nadie@inventado.test', 5);

        $real = $this->login('admin@seguro.test', 'x')->json();
        $inventado = $this->login('nadie@inventado.test', 'x')->json();

        // Los segundos restantes pueden diferir en 1 entre una llamada y otra.
        $sinNumeros = fn ($texto) => preg_replace('/\d+/', 'N', $texto);

        $this->assertSame($real['error'], $inventado['error']);
        $this->assertSame($sinNumeros($real['mensaje']), $sinNumeros($inventado['mensaje']));
    }

    /**
     * El mensaje ya respondía igual (prueba de arriba), pero el TIEMPO podía
     * delatar si el correo existe: sin el hash falso, un correo inexistente
     * no llamaba a Hash::check() en absoluto, mientras que uno real con
     * clave incorrecta sí. Esta prueba (y la siguiente) no miden
     * milisegundos (no es determinista); miden la única cosa que sí lo es:
     * cuántas veces se llama Hash::check(), que tiene que ser UNA en los dos
     * casos. Van en dos métodos separados, no uno con dos Hash::spy():
     * Facade::spy() solo crea un espía nuevo "if (! static::isMock())", así
     * que una segunda llamada en el mismo test no reinicia nada y las dos
     * aserciones terminan contando sobre el mismo espía acumulado.
     */
    public function test_hash_check_se_llama_una_vez_para_un_correo_inexistente(): void
    {
        Hash::spy();
        $this->login('nadie@inventado.test', 'cualquier-clave');
        Hash::shouldHaveReceived('check')->once();
    }

    public function test_hash_check_se_llama_una_vez_para_clave_mala_de_un_correo_real(): void
    {
        Hash::spy();
        $this->login('admin@seguro.test', 'clave-equivocada');
        Hash::shouldHaveReceived('check')->once();
    }

    public function test_un_login_exitoso_reinicia_el_contador_de_su_correo(): void
    {
        $this->fallarLogin('admin@seguro.test', 4);
        $this->login('admin@seguro.test', self::CLAVE)->assertJsonPath('error', 0);

        // Sin la limpieza, estos 4 sumarían 8 fallos y el siguiente quedaría bloqueado.
        $this->fallarLogin('admin@seguro.test', 4);
        $this->login('admin@seguro.test', self::CLAVE)->assertJsonPath('error', 0);
    }

    /* ================= 2) LARGO MÍNIMO DE CLAVE ================= */

    public function test_crear_un_usuario_con_clave_de_7_caracteres_es_rechazado(): void
    {
        $respuesta = $this->withSession($this->sesionDe($this->admin, 1))
            ->postJson('request/usuario/crear', [
                'usuario' => 'nuevo',
                'nombre' => 'Nuevo',
                'email' => 'nuevo@seguro.test',
                'clave' => '1234567',
                'id_rol' => 1,
                'tenant_id' => $this->negocio,
            ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertContains('La clave debe tener al menos 8 caracteres.', $respuesta->json('mensaje'));
        $this->assertFalse(DB::table('usuarios')->where('email', 'nuevo@seguro.test')->exists());
    }

    public function test_crear_un_usuario_con_clave_de_8_caracteres_si_funciona(): void
    {
        $this->withSession($this->sesionDe($this->admin, 1))
            ->postJson('request/usuario/crear', [
                'usuario' => 'nuevo',
                'nombre' => 'Nuevo',
                'email' => 'nuevo@seguro.test',
                'clave' => '12345678',
                'id_rol' => 1,
                'tenant_id' => $this->negocio,
            ])
            ->assertJsonPath('error', 0);
    }

    private function editarUsuario(int $idUsuario, array $extra)
    {
        return $this->withSession($this->sesionDe($this->admin, 1))
            ->postJson('request/usuario/editar', array_merge([
                'id_usuario' => $idUsuario,
                'usuario' => 'otro',
                'nombre' => 'Otro',
                'email' => 'otro@seguro.test',
                'id_rol' => 1,
                'tenant_id' => $this->negocio,
                'estado' => 1,
            ], $extra));
    }

    public function test_cambiar_la_clave_de_un_usuario_a_una_de_7_caracteres_es_rechazado(): void
    {
        $idOtro = $this->crearUsuario('otro', 'otro@seguro.test', 1);
        $hashAntes = DB::table('usuarios')->where('id_usuario', $idOtro)->value('clave');

        $respuesta = $this->editarUsuario($idOtro, ['clave' => 'corta12']);

        $respuesta->assertJsonPath('error', 1);
        $this->assertContains('La clave debe tener al menos 8 caracteres.', $respuesta->json('mensaje'));
        $this->assertSame($hashAntes, DB::table('usuarios')->where('id_usuario', $idOtro)->value('clave'));
    }

    /** Editar sin tocar la clave (campo vacío) tiene que seguir funcionando. */
    public function test_editar_un_usuario_sin_mandar_clave_sigue_funcionando(): void
    {
        $idOtro = $this->crearUsuario('otro', 'otro@seguro.test', 1);
        $hashAntes = DB::table('usuarios')->where('id_usuario', $idOtro)->value('clave');

        $this->editarUsuario($idOtro, ['clave' => ''])->assertJsonPath('error', 0);
        $this->editarUsuario($idOtro, [])->assertJsonPath('error', 0);

        $this->assertSame($hashAntes, DB::table('usuarios')->where('id_usuario', $idOtro)->value('clave'));
    }

    public function test_registrarse_con_clave_de_7_caracteres_es_rechazado(): void
    {
        $respuesta = $this->postJson('request/registro-publico/crear', [
            'nombre_negocio' => 'Spa Corto',
            'telefono_contacto' => '3001234567',
            'rubro' => 'spa',
            'nombre' => 'Dueña',
            'email' => 'duena@corto.test',
            'clave' => 'abc1234',
            'confirmar_clave' => 'abc1234',
        ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertContains('La clave debe tener al menos 8 caracteres.', $respuesta->json('mensaje'));
        // Desde el registro en dos pasos, el paso 1 deja un registro pendiente
        // (nunca un negocio): lo que se comprueba es que ni eso quedó.
        $this->assertFalse(DB::table('registros_pendientes')->where('correo', 'duena@corto.test')->exists());
    }

    public function test_dar_acceso_a_un_empleado_con_clave_de_7_caracteres_es_rechazado(): void
    {
        $idEmpleado = DB::table('empleados')->insertGetId([
            'tenant_id' => $this->negocio,
            'nombre' => 'Laura',
            'telefono' => '3000000000',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $respuesta = $this->withSession($this->sesionDe($this->admin, 1))
            ->postJson('request/empleado/crear-acceso', [
                'id_empleado' => $idEmpleado,
                'usuario' => 'laura',
                'email' => 'laura@seguro.test',
                'clave' => 'laura12',
            ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertContains('La clave debe tener al menos 8 caracteres.', $respuesta->json('mensaje'));
        $this->assertFalse(DB::table('usuarios')->where('email', 'laura@seguro.test')->exists());
    }

    /* ================= 3) FIJACIÓN DE SESIÓN Y LOGOUT ================= */

    /**
     * Alguien planta un ID de sesión conocido en el navegador de la víctima
     * antes de que ella entre. Tras el login, ese ID no puede quedar
     * autenticado: la sesión autenticada vive bajo un ID nuevo.
     */
    public function test_el_login_exitoso_cambia_el_id_de_sesion(): void
    {
        $idPlantado = Str::random(40);

        // withCredentials(): sin esto, postJson/getJson NO mandan cookies en
        // las pruebas de Laravel, la cookie plantada nunca llegaría y la
        // prueba pasaría aunque el login no regenerara nada (así pasó en la
        // primera versión de esta prueba; ver la mutación M3 en el reporte).
        $respuesta = $this->withCredentials()
            ->withCookie($this->nombreCookieSesion(), $idPlantado)
            ->login('admin@seguro.test', self::CLAVE);

        $respuesta->assertJsonPath('error', 0);

        $idNuevo = $respuesta->getCookie($this->nombreCookieSesion())->getValue();

        $this->assertNotSame($idPlantado, $idNuevo, 'El login debe emitir un ID de sesión nuevo');

        // Con el ID plantado no se entra; con el nuevo sí.
        $this->flushSession();
        $this->withCookie($this->nombreCookieSesion(), $idPlantado)
            ->get('backoffice/dashboard')
            ->assertRedirect(url('/login'));

        $this->flushSession();
        $this->withCookie($this->nombreCookieSesion(), $idNuevo)
            ->get('backoffice/dashboard')
            ->assertOk();
    }

    public function test_el_logout_invalida_la_sesion_y_la_cookie_vieja_ya_no_sirve(): void
    {
        $idSesion = $this->login('admin@seguro.test', self::CLAVE)
            ->assertJsonPath('error', 0)
            ->getCookie($this->nombreCookieSesion())
            ->getValue();

        $tokenAntes = session()->token();

        // Control: antes del logout, esa misma cookie sí autentica por las dos
        // vías. Sin esto, el "no autenticado" del final no probaría nada.
        $this->flushSession();
        $this->withCookie($this->nombreCookieSesion(), $idSesion)->get('backoffice/dashboard')->assertOk();
        $this->flushSession();
        $this->withCredentials()
            ->withCookie($this->nombreCookieSesion(), $idSesion)
            ->getJson('request/negocio/horario')
            ->assertJsonPath('error', 0);

        $this->flushSession();
        $logout = $this->withCookie($this->nombreCookieSesion(), $idSesion)->get('backoffice/logout');
        $logout->assertRedirect(url('/'));

        // El ID viejo se destruyó en el almacén (flush() solo lo vaciaba y lo
        // dejaba vivo), el logout emitió uno nuevo, y el token CSRF cambió.
        $this->assertSame('', $this->app['session']->driver()->getHandler()->read($idSesion));
        $this->assertNotSame($idSesion, $logout->getCookie($this->nombreCookieSesion())->getValue());
        $this->assertNotSame($tokenAntes, session()->token());

        // Una petición posterior con la cookie vieja no está autenticada.
        $this->flushSession();
        $this->withCookie($this->nombreCookieSesion(), $idSesion)
            ->get('backoffice/dashboard')
            ->assertRedirect(url('/login'));

        $this->flushSession();
        $this->withCredentials()
            ->withCookie($this->nombreCookieSesion(), $idSesion)
            ->getJson('request/negocio/horario')
            ->assertJsonPath('error', 1);
    }

    /* ================= 4) CORTE DE SESIÓN AL DESACTIVAR A LA PERSONA (mutación M2 arriba) ================= */

    public function test_desactivar_un_usuario_corta_su_sesion_abierta_en_backoffice_y_en_request(): void
    {
        $idOtroAdmin = $this->crearUsuario('otro.admin', 'otro.admin@seguro.test', 1);

        // Control: con el usuario activo, su sesión funciona.
        $this->withSession($this->sesionDe($idOtroAdmin, 1))->get('backoffice/dashboard')->assertOk();

        // El admin principal lo desactiva por el camino real.
        $this->flushSession();
        $this->editarUsuarioComo($this->admin, $idOtroAdmin, 0)->assertJsonPath('error', 0);

        // La siguiente petición de la sesión del desactivado se corta.
        $this->flushSession();
        $this->withSession($this->sesionDe($idOtroAdmin, 1))
            ->get('backoffice/dashboard')
            ->assertRedirect(url('/login'))
            ->assertSessionHas('aviso_login', VerificarSesion::MENSAJE_USUARIO_INACTIVO);

        $this->assertNull(session('app_sesion'), 'La sesión cortada debe quedar vacía');

        $this->flushSession();
        $this->withSession($this->sesionDe($idOtroAdmin, 1))
            ->getJson('request/negocio/horario')
            ->assertJsonPath('error', 1)
            ->assertJsonPath('mensaje', VerificarSesion::MENSAJE_USUARIO_INACTIVO);
    }

    private function editarUsuarioComo(int $idQuienEdita, int $idUsuario, int $estado)
    {
        return $this->withSession($this->sesionDe($idQuienEdita, 1))
            ->postJson('request/usuario/editar', [
                'id_usuario' => $idUsuario,
                'usuario' => 'otro.admin',
                'nombre' => 'Otro admin',
                'email' => 'otro.admin@seguro.test',
                'id_rol' => 1,
                'tenant_id' => $this->negocio,
                'estado' => $estado,
            ]);
    }

    /**
     * El empleado se desactiva SOLO en su fila (sin tocar su usuario) para
     * probar el chequeo del empleado por sí mismo: la cascada normal también
     * desactivaría el usuario y el caso quedaría cubierto por el otro chequeo.
     */
    public function test_desactivar_un_empleado_corta_su_sesion_propia(): void
    {
        $idUsuarioEmpleado = $this->crearUsuario('laura', 'laura@seguro.test', 2);

        $idEmpleado = DB::table('empleados')->insertGetId([
            'tenant_id' => $this->negocio,
            'nombre' => 'Laura',
            'telefono' => '3000000000',
            'id_usuario' => $idUsuarioEmpleado,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $sesion = $this->sesionDe($idUsuarioEmpleado, 2, $idEmpleado);

        $this->withSession($sesion)->get('backoffice/mis-citas')->assertOk();

        DB::table('empleados')->where('id_empleado', $idEmpleado)->update(['estado' => 0]);

        $this->flushSession();
        $this->withSession($sesion)
            ->get('backoffice/mis-citas')
            ->assertRedirect(url('/login'))
            ->assertSessionHas('aviso_login', VerificarSesion::MENSAJE_USUARIO_INACTIVO);

        $this->flushSession();
        $this->withSession($sesion)
            ->getJson('request/reserva/mis-citas')
            ->assertJsonPath('error', 1)
            ->assertJsonPath('mensaje', VerificarSesion::MENSAJE_USUARIO_INACTIVO);
    }

    public function test_un_usuario_activo_no_se_ve_afectado_por_el_chequeo(): void
    {
        $this->withSession($this->sesionDe($this->admin, 1))->get('backoffice/dashboard')->assertOk();
        $this->withSession($this->sesionDe($this->admin, 1))->getJson('request/negocio/horario')->assertJsonPath('error', 0);
    }

    /* ================= 9) UN ADMIN CAMBIANDO LA CLAVE DE OTRO USUARIO TAMBIÉN CIERRA SUS SESIONES =================
     * (mutación: ver la cabecera del archivo)
     */

    /**
     * Nota sobre este grupo: a diferencia de otras pruebas del archivo, aquí
     * NO se usa el helper editarUsuario() (que llama a withSession()). Mezclar
     * withSession() con el patrón de "otra cookie = otro dispositivo" rompe
     * ese patrón: withSession() arranca el Store de sesión del contenedor, y
     * una vez arrancado, StartSession no lo vuelve a resolver desde la cookie
     * de la siguiente petición — flushSession() lo deja vacío en vez de
     * recargar la sesión vieja, y la prueba ve "Tu sesión terminó" en lugar
     * del aviso real. Por eso aquí el admin también entra con una cookie de
     * verdad (login real), igual que el resto de "dispositivos" de la prueba.
     */
    private function editarComoAdminConCookie(string $cookie, int $idOtro, array $extra)
    {
        $this->flushSession();
        $idSesionAdmin = $this->login('admin@seguro.test', self::CLAVE)->getCookie($cookie)->getValue();

        $this->flushSession();

        return $this->withCredentials()
            ->withCookie($cookie, $idSesionAdmin)
            ->postJson('request/usuario/editar', array_merge([
                'id_usuario' => $idOtro,
                'usuario' => 'otro',
                'nombre' => 'Otro',
                'email' => 'otro@seguro.test',
                'id_rol' => 1,
                'tenant_id' => $this->negocio,
                'estado' => 1,
            ], $extra));
    }

    public function test_cambiar_la_clave_de_un_usuario_desde_editar_corta_su_sesion_abierta(): void
    {
        $idOtro = $this->crearUsuario('otro', 'otro@seguro.test', 1);
        $cookie = $this->nombreCookieSesion();

        // Dos sesiones reales de "otro" abiertas ANTES de que el admin le
        // cambie la clave (dos pestañas/dispositivos). Cada una se visita
        // UNA sola vez después del cambio: la propia VerificarSesion hace
        // session()->flush() al cortar, así que una cookie ya cortada no
        // sirve para comprobar el aviso una segunda vez (la segunda petición
        // con esa misma cookie ya no tiene ni siquiera app_sesion, y cae en
        // el aviso genérico "Tu sesión terminó" en vez del de clave
        // cambiada — correcto, pero no es lo que esta prueba quiere medir).
        $idSesionParaBackoffice = $this->login('otro@seguro.test', self::CLAVE)
            ->assertJsonPath('error', 0)
            ->getCookie($cookie)
            ->getValue();

        $this->flushSession();
        $idSesionParaRequest = $this->login('otro@seguro.test', self::CLAVE)
            ->assertJsonPath('error', 0)
            ->getCookie($cookie)
            ->getValue();

        // Control: las dos sesiones funcionan.
        $this->flushSession();
        $this->withCookie($cookie, $idSesionParaBackoffice)->get('backoffice/dashboard')->assertOk();
        $this->flushSession();
        $this->withCredentials()
            ->withCookie($cookie, $idSesionParaRequest)
            ->getJson('request/negocio/horario')
            ->assertJsonPath('error', 0);

        // El admin (su propia sesión real) le cambia la clave desde usuario/editar.
        $this->editarComoAdminConCookie($cookie, $idOtro, ['clave' => 'ClaveNuevaDesdeAdmin2026'])
            ->assertJsonPath('error', 0);

        // La primera sesión vieja deja de servir en backoffice...
        $this->flushSession();
        $this->withCookie($cookie, $idSesionParaBackoffice)
            ->get('backoffice/dashboard')
            ->assertRedirect(url('/login'))
            ->assertSessionHas('aviso_login', VerificarSesion::MENSAJE_CLAVE_CAMBIADA);

        // ...y la segunda, en request/* (primera y única visita de ESTA cookie
        // tras el cambio, así que todavía conserva el motivo específico).
        $this->flushSession();
        $this->withCredentials()
            ->withCookie($cookie, $idSesionParaRequest)
            ->getJson('request/negocio/horario')
            ->assertJsonPath('error', 1)
            ->assertJsonPath('mensaje', VerificarSesion::MENSAJE_CLAVE_CAMBIADA);

        // Y la clave nueva sirve para entrar.
        $this->flushSession();
        $this->login('otro@seguro.test', 'ClaveNuevaDesdeAdmin2026')->assertJsonPath('error', 0);
    }

    /** Convención ya existente: clave vacía = "no la cambies". No debe tocar version_sesion. */
    public function test_editar_un_usuario_sin_cambiar_la_clave_no_corta_su_sesion(): void
    {
        $idOtro = $this->crearUsuario('otro', 'otro@seguro.test', 1);
        $cookie = $this->nombreCookieSesion();
        $versionAntes = DB::table('usuarios')->where('id_usuario', $idOtro)->value('version_sesion');

        $idSesion = $this->login('otro@seguro.test', self::CLAVE)
            ->assertJsonPath('error', 0)
            ->getCookie($cookie)
            ->getValue();

        $this->editarComoAdminConCookie($cookie, $idOtro, ['clave' => '', 'nombre' => 'Otro Editado'])
            ->assertJsonPath('error', 0);
        $this->editarComoAdminConCookie($cookie, $idOtro, ['nombre' => 'Otro Editado De Nuevo'])
            ->assertJsonPath('error', 0);

        $this->assertSame(
            $versionAntes,
            DB::table('usuarios')->where('id_usuario', $idOtro)->value('version_sesion'),
            'Editar sin tocar la clave no debe incrementar version_sesion'
        );

        $this->flushSession();
        $this->withCookie($cookie, $idSesion)->get('backoffice/dashboard')->assertOk();
    }
}
