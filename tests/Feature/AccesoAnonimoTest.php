<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Una petición sin sesión no puede usar ningún endpoint privado de request/*.
 *
 * Antes de esta prueba, el grupo administrativo de request/* solo llevaba
 * restringir.empleado, que deja pasar a quien no tiene rol. Y varios
 * controladores interpretan session('tenant_id') === null como "super admin,
 * no filtres por negocio". Una petición anónima también tiene tenant_id null,
 * así que se colaba con permisos de plataforma:
 *
 *   - GET  request/usuario/listar devolvía los usuarios de TODOS los negocios.
 *   - POST request/usuario/crear aceptaba tenant_id e id_rol del cuerpo, es
 *     decir, cualquiera podía crearse un admin dentro de cualquier negocio.
 *
 * Se verificó por HTTP contra el servidor real (listar) y con estas pruebas
 * antes de corregir (crear). La corrección es sesion.activa en los grupos
 * privados de request/*.
 *
 * ================= PRUEBA DE MUTACIÓN =================
 *
 * ANTES de la corrección (código original): 6 tests, 2 passed, 4 FAILED. Las
 * tres de ataque (listar, crear admin, editar correo ajeno) y el guardián, que
 * listó las 59 rutas de request/* sin sesion.activa.
 *
 * MUTACIÓN, ya corregido:
 *   1. En routes/web.php se quitó 'sesion.activa' del grupo administrativo
 *      (el que tiene restringir.empleado).
 *   2. php artisan test --filter=AccesoAnonimoTest
 *      Resultado: 6 tests, 2 passed, 4 FAILED — las tres de ataque y el
 *      guardián. El anónimo volvió a listar usuarios y a crearse un admin.
 *   3. Se restauró la línea tal cual estaba.
 *   4. Se volvió a ejecutar: 6 passed.
 */
class AccesoAnonimoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rutas de request/* que SÍ pueden llamarse sin sesión, a propósito. Si
     * hace falta sumar una, se agrega aquí con su justificación.
     */
    const RUTAS_PUBLICAS_REQUEST = [
        // El propio login: por definición se llama sin sesión.
        'request/autenticacion/login',
        // Alta autoservicio de un negocio nuevo: quien la usa aún no tiene cuenta.
        'request/registro-publico/crear',
        // Paso 2 del alta: el link del correo de confirmación. Quien lo abre
        // todavía no tiene cuenta; lo protege el token de un solo uso.
        'request/registro-publico/confirmar/{token}',
        // Recuperación de clave: quien la usa, por definición, no puede entrar.
        // La protegen el throttle por IP y el límite de intentos por correo+IP.
        'request/recuperacion/solicitar',
        'request/recuperacion/confirmar',
    ];

    private int $negocio;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocio = DB::table('negocios')->insertGetId([
            'nombre_negocio' => 'Negocio Victima',
            'rubro' => 'spa',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        DB::table('usuarios')->insert([
            'usuario' => 'dueno',
            'nombre' => 'Dueno del Negocio',
            'email' => 'dueno@negocio.test',
            'clave' => bcrypt('secreta123'),
            'tenant_id' => $this->negocio,
            'id_rol' => 1,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    public function test_un_anonimo_no_puede_listar_los_usuarios_de_la_plataforma(): void
    {
        $respuesta = $this->getJson('request/usuario/listar');

        $this->assertSame(1, $respuesta->json('error'), 'Sin sesión no se puede listar usuarios');
        $this->assertStringNotContainsString('dueno@negocio.test', $respuesta->getContent());
    }

    public function test_un_anonimo_no_puede_crearse_un_admin_en_un_negocio_ajeno(): void
    {
        $antes = DB::table('usuarios')->count();

        $respuesta = $this->postJson('request/usuario/crear', [
            'usuario' => 'intruso',
            'nombre' => 'Intruso',
            'email' => 'intruso@atacante.test',
            'clave' => 'intruso123',
            'tenant_id' => $this->negocio,
            'id_rol' => 1,
        ]);

        $this->assertSame(1, $respuesta->json('error'), 'Sin sesión no se puede crear usuarios');
        $this->assertSame($antes, DB::table('usuarios')->count(), 'No debe haberse creado ninguna cuenta');
        $this->assertFalse(DB::table('usuarios')->where('email', 'intruso@atacante.test')->exists());
    }

    public function test_un_anonimo_no_puede_editar_un_usuario_ajeno(): void
    {
        $idUsuario = DB::table('usuarios')->where('email', 'dueno@negocio.test')->value('id_usuario');

        $respuesta = $this->postJson('request/usuario/editar', [
            'id_usuario' => $idUsuario,
            'usuario' => 'dueno',
            'nombre' => 'Dueno del Negocio',
            'email' => 'robado@atacante.test',
            'tenant_id' => $this->negocio,
            'id_rol' => 1,
            'estado' => 1,
        ]);

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertSame(
            'dueno@negocio.test',
            DB::table('usuarios')->where('id_usuario', $idUsuario)->value('email'),
            'El correo del dueño no puede haber cambiado'
        );
    }

    /**
     * Guardián: toda ruta de request/* lleva sesion.activa, salvo la lista
     * blanca de arriba. Una ruta nueva sin protección rompe la suite.
     */
    public function test_toda_ruta_privada_de_request_exige_sesion(): void
    {
        $sinProteccion = [];

        foreach (Route::getRoutes() as $ruta) {
            $uri = $ruta->uri();

            if (! str_starts_with($uri, 'request/') || in_array($uri, self::RUTAS_PUBLICAS_REQUEST, true)) {
                continue;
            }

            if (! in_array('sesion.activa', $ruta->gatherMiddleware(), true)) {
                $sinProteccion[] = implode('|', $ruta->methods()).' '.$uri;
            }
        }

        $this->assertSame([], $sinProteccion, "Rutas de request/* sin sesion.activa:\n".implode("\n", $sinProteccion));
    }

    /** El rechazo en request/* es JSON, no una redirección que el frontend no entiende. */
    public function test_el_rechazo_en_request_es_json(): void
    {
        $respuesta = $this->getJson('request/cliente/listar');

        $respuesta->assertStatus(200);
        $this->assertSame(1, $respuesta->json('error'));
        $this->assertNotEmpty($respuesta->json('mensaje'));
    }

    /** El login sigue funcionando sin sesión: no se cerró lo que debe estar abierto. */
    public function test_el_login_sigue_abierto_sin_sesion(): void
    {
        $respuesta = $this->postJson('request/autenticacion/login', [
            'email' => 'dueno@negocio.test',
            'clave' => 'secreta123',
        ]);

        $this->assertSame(0, $respuesta->json('error'), $respuesta->json('mensaje') ?? '');
    }
}
