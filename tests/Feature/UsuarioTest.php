<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Usuarios: aislamiento entre negocios y unicidad de credenciales.
 */
class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = $this->crearNegocio('Negocio A');
        $this->negocioB = $this->crearNegocio('Negocio B');
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'rubro' => 'spa',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearUsuario(?int $tenantId, string $usuario, string $email, int $idRol = 1, int $estado = 1): int
    {
        return DB::table('usuarios')->insertGetId([
            'tenant_id' => $tenantId,
            'id_rol' => $idRol,
            'usuario' => $usuario,
            'nombre' => 'Usuario '.$usuario,
            'email' => $email,
            'clave' => bcrypt('secreta'),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => $estado,
        ]);
    }

    private function sesionAdmin(int $tenantId): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 1,
            'usuario' => 'admin.test',
            'nombre_usuario' => 'Admin Test',
            'tenant_id' => $tenantId,
            'id_rol' => 1,
        ];
    }

    /* ================= 1) FUGA DE TENANT AL EDITAR =================
     *
     * ---------- PRUEBA DE MUTACIÓN ----------
     *
     * test_editar_usuario_no_permite_cambiar_el_negocio_desde_el_request() se
     * verificó rompiendo la protección a propósito. Procedimiento ejecutado:
     *
     *   1. En app/Service/SvcUsuario.php, dentro de editar(), se quitaron las
     *      tres líneas que imponen el negocio de la sesión:
     *          if ($tenantId !== null) { $info['tenant_id'] = $tenantId; }
     *      Con eso el tenant_id vuelve a tomarse del cuerpo de la petición.
     *   2. Se ejecutó: php artisan test --filter=test_editar_usuario_no_permite...
     *      Resultado: 1 test, 0 passed, 1 FAILED:
     *        "El usuario debe seguir en su negocio original, no en el que llegó
     *         por el request / Failed asserting that 2 is identical to 1."
     *      (el usuario del negocio 1 había quedado en el negocio 2).
     *   3. Se restauró el archivo y la prueba volvió a pasar (2 tests, 5 asserts).
     *
     * Antes de la corrección, esta misma prueba fallaba igual: así se confirmó
     * que la fuga era real y no una sospecha al leer el código.
     */

    /**
     * El negocio de un usuario no puede cambiarse desde el cuerpo de la
     * petición: aunque el request traiga un tenant_id de otro negocio, el
     * usuario debe quedarse donde estaba.
     *
     * Mover un usuario a otro negocio le daría acceso a los datos de ese
     * negocio (sus clientes, sus reservas, su inventario) en el siguiente
     * inicio de sesión, porque el tenant de la sesión sale de esta columna.
     */
    public function test_editar_usuario_no_permite_cambiar_el_negocio_desde_el_request(): void
    {
        $idUsuario = $this->crearUsuario($this->negocioA, 'usuario.del.a', 'a@test.local');

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/usuario/editar', [
                'id_usuario' => $idUsuario,
                'usuario' => 'usuario.del.a',
                'nombre' => 'Usuario Del A',
                'email' => 'a@test.local',
                'id_rol' => 1,
                'estado' => 1,
                // El intento: mandar el negocio ajeno en el cuerpo de la petición.
                'tenant_id' => $this->negocioB,
            ]);

        $respuesta->assertJsonPath('error', 0);

        $tenantFinal = DB::table('usuarios')->where('id_usuario', $idUsuario)->value('tenant_id');

        $this->assertSame(
            $this->negocioA,
            $tenantFinal,
            'El usuario debe seguir en su negocio original, no en el que llegó por el request'
        );
        $this->assertNotSame($this->negocioB, $tenantFinal);
    }

    /** Un admin no puede editar usuarios de otro negocio. */
    public function test_editar_usuario_de_otro_negocio_es_rechazado(): void
    {
        $idUsuario = $this->crearUsuario($this->negocioA, 'usuario.del.a', 'a@test.local');

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioB))
            ->postJson('request/usuario/editar', [
                'id_usuario' => $idUsuario,
                'usuario' => 'secuestrado',
                'nombre' => 'Secuestrado',
                'email' => 'secuestrado@test.local',
                'id_rol' => 1,
                'estado' => 1,
                'tenant_id' => $this->negocioB,
            ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertSame('usuario.del.a', DB::table('usuarios')->where('id_usuario', $idUsuario)->value('usuario'));
    }

    /* ================= 2) UNICIDAD AL EDITAR ================= */

    /** Guardar sin tocar las credenciales no debe chocar consigo mismo. */
    public function test_editar_usuario_sin_cambiar_sus_credenciales_funciona(): void
    {
        $idUsuario = $this->crearUsuario($this->negocioA, 'usuario.uno', 'uno@test.local');

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/usuario/editar', [
                'id_usuario' => $idUsuario,
                'usuario' => 'usuario.uno',
                'email' => 'uno@test.local',
                'nombre' => 'Nombre Cambiado',
                'id_rol' => 1,
                'estado' => 1,
                'tenant_id' => $this->negocioA,
            ]);

        $respuesta->assertJsonPath('error', 0);
        $this->assertSame('Nombre Cambiado', DB::table('usuarios')->where('id_usuario', $idUsuario)->value('nombre'));
    }

    public function test_editar_usuario_con_correo_de_otra_cuenta_es_rechazado(): void
    {
        $idUno = $this->crearUsuario($this->negocioA, 'usuario.uno', 'uno@test.local');
        $this->crearUsuario($this->negocioA, 'usuario.dos', 'dos@test.local');

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/usuario/editar', [
                'id_usuario' => $idUno,
                'usuario' => 'usuario.uno',
                'email' => 'dos@test.local',
                'nombre' => 'Usuario Uno',
                'id_rol' => 1,
                'estado' => 1,
                'tenant_id' => $this->negocioA,
            ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertStringContainsString('ya está registrado en otra cuenta', $respuesta->json('mensaje'));
        $this->assertSame('uno@test.local', DB::table('usuarios')->where('id_usuario', $idUno)->value('email'));
    }

    public function test_editar_usuario_con_nombre_de_usuario_de_otra_cuenta_es_rechazado(): void
    {
        $idUno = $this->crearUsuario($this->negocioA, 'usuario.uno', 'uno@test.local');
        $this->crearUsuario($this->negocioA, 'usuario.dos', 'dos@test.local');

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/usuario/editar', [
                'id_usuario' => $idUno,
                'usuario' => 'usuario.dos',
                'email' => 'uno@test.local',
                'nombre' => 'Usuario Uno',
                'id_rol' => 1,
                'estado' => 1,
                'tenant_id' => $this->negocioA,
            ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertStringContainsString('ya está en uso', $respuesta->json('mensaje'));
        $this->assertSame('usuario.uno', DB::table('usuarios')->where('id_usuario', $idUno)->value('usuario'));
    }

    /**
     * La unicidad es global, no por negocio: el login es una sola pantalla para
     * toda la plataforma, así que un correo ya usado en otro negocio tampoco
     * sirve aquí.
     */
    public function test_editar_usuario_con_correo_de_otro_negocio_tambien_es_rechazado(): void
    {
        $idDelA = $this->crearUsuario($this->negocioA, 'usuario.del.a', 'a@test.local');
        $this->crearUsuario($this->negocioB, 'usuario.del.b', 'b@test.local');

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/usuario/editar', [
                'id_usuario' => $idDelA,
                'usuario' => 'usuario.del.a',
                'email' => 'b@test.local',
                'nombre' => 'Usuario Del A',
                'id_rol' => 1,
                'estado' => 1,
                'tenant_id' => $this->negocioA,
            ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertSame('a@test.local', DB::table('usuarios')->where('id_usuario', $idDelA)->value('email'));
        // Y el usuario del otro negocio queda intacto.
        $this->assertSame(1, DB::table('usuarios')->where('email', 'b@test.local')->where('tenant_id', $this->negocioB)->count());
    }

    /* ================= 3) UNICIDAD SOLO ENTRE CUENTAS ACTIVAS =================
     *
     * Al desactivar una cuenta, su correo y su nombre de usuario quedan libres.
     * Las columnas originales conservan el dato real; lo que deja de contar para
     * la restricción son las columnas generadas, que valen NULL si estado = 0.
     */

    public function test_se_puede_reutilizar_el_correo_de_una_cuenta_desactivada(): void
    {
        $this->crearUsuario($this->negocioA, 'usuario.viejo', 'liberado@test.local', 1, 0);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/usuario/crear', [
                'usuario' => 'usuario.nuevo',
                'nombre' => 'Usuario Nuevo',
                'email' => 'liberado@test.local',
                'clave' => 'Clave2026',
                'id_rol' => 1,
                'estado' => 1,
                'tenant_id' => $this->negocioA,
            ]);

        $respuesta->assertJsonPath('error', 0);

        // Conviven: la vieja conserva su correo para auditoría, la nueva lo usa.
        $this->assertSame(2, DB::table('usuarios')->where('email', 'liberado@test.local')->count());
        $this->assertSame(1, DB::table('usuarios')->where('email', 'liberado@test.local')->where('estado', 1)->count());
    }

    /** La liberación cruza negocios: el correo queda libre para cualquiera. */
    public function test_se_puede_reutilizar_en_otro_negocio_el_correo_de_una_cuenta_desactivada(): void
    {
        $this->crearUsuario($this->negocioA, 'usuario.viejo', 'liberado@test.local', 1, 0);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioB))
            ->postJson('request/usuario/crear', [
                'usuario' => 'usuario.del.b',
                'nombre' => 'Usuario Del B',
                'email' => 'liberado@test.local',
                'clave' => 'Clave2026',
                'id_rol' => 1,
                'estado' => 1,
                'tenant_id' => $this->negocioB,
            ]);

        $respuesta->assertJsonPath('error', 0);
        $this->assertSame(
            $this->negocioB,
            DB::table('usuarios')->where('email', 'liberado@test.local')->where('estado', 1)->value('tenant_id')
        );
    }

    public function test_se_puede_reutilizar_el_nombre_de_usuario_de_una_cuenta_desactivada(): void
    {
        $this->crearUsuario($this->negocioA, 'nombre.liberado', 'viejo@test.local', 1, 0);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/usuario/crear', [
                'usuario' => 'nombre.liberado',
                'nombre' => 'Usuario Nuevo',
                'email' => 'nuevo@test.local',
                'clave' => 'Clave2026',
                'id_rol' => 1,
                'estado' => 1,
                'tenant_id' => $this->negocioA,
            ]);

        $respuesta->assertJsonPath('error', 0);
        $this->assertSame(1, DB::table('usuarios')->where('usuario', 'nombre.liberado')->where('estado', 1)->count());
    }

    /** La regla de siempre sigue en pie: una cuenta ACTIVA no cede su correo. */
    public function test_el_correo_de_una_cuenta_activa_se_sigue_rechazando(): void
    {
        $this->crearUsuario($this->negocioA, 'usuario.activo', 'ocupado@test.local', 1, 1);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioB))
            ->postJson('request/usuario/crear', [
                'usuario' => 'otro.usuario',
                'nombre' => 'Otro Usuario',
                'email' => 'ocupado@test.local',
                'clave' => 'Clave2026',
                'id_rol' => 1,
                'estado' => 1,
                'tenant_id' => $this->negocioB,
            ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertStringContainsString('ya está registrado en otra cuenta', $respuesta->json('mensaje'));
        $this->assertSame(1, DB::table('usuarios')->where('email', 'ocupado@test.local')->count());
    }

    /* ================= 4) LA PROTECCIÓN VIVE EN LA BASE ================= */

    /**
     * Saltándose por completo el controller y su validación, la base debe seguir
     * rechazando dos cuentas ACTIVAS con el mismo correo.
     *
     * Esto es lo que distingue una regla de negocio protegida de una que solo
     * está escrita en un if: si alguien quita la validación sin querer, el dato
     * sigue a salvo.
     */
    public function test_la_base_rechaza_dos_cuentas_activas_con_el_mismo_correo(): void
    {
        $this->crearUsuario($this->negocioA, 'usuario.uno', 'choque@test.local', 1, 1);

        $this->expectException(QueryException::class);

        // Insert directo por Eloquent: ni controller ni Service de por medio.
        Usuario::create([
            'tenant_id' => $this->negocioB,
            'id_rol' => 1,
            'usuario' => 'usuario.dos',
            'nombre' => 'Usuario Dos',
            'email' => 'choque@test.local',
            'clave' => 'da-igual',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    public function test_la_base_rechaza_dos_cuentas_activas_con_el_mismo_nombre_de_usuario(): void
    {
        $this->crearUsuario($this->negocioA, 'choque.usuario', 'uno@test.local', 1, 1);

        $this->expectException(QueryException::class);

        Usuario::create([
            'tenant_id' => $this->negocioB,
            'id_rol' => 1,
            'usuario' => 'choque.usuario',
            'nombre' => 'Usuario Dos',
            'email' => 'dos@test.local',
            'clave' => 'da-igual',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    /** La otra cara: con una de las dos inactiva, la base sí lo permite. */
    public function test_la_base_permite_el_mismo_correo_si_una_de_las_cuentas_esta_inactiva(): void
    {
        $this->crearUsuario($this->negocioA, 'usuario.inactivo', 'compartido@test.local', 1, 0);

        $creado = Usuario::create([
            'tenant_id' => $this->negocioB,
            'id_rol' => 1,
            'usuario' => 'usuario.activo',
            'nombre' => 'Usuario Activo',
            'email' => 'compartido@test.local',
            'clave' => 'da-igual',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $this->assertNotNull($creado->id_usuario);
        $this->assertSame(2, DB::table('usuarios')->where('email', 'compartido@test.local')->count());

        // La columna original conserva el dato en las dos; solo la generada
        // distingue cuál compite por la unicidad.
        $this->assertSame(1, DB::table('usuarios')
            ->where('email', 'compartido@test.local')
            ->whereNotNull('email_activo_unico')
            ->count());
    }

    /* ================= 5) EL LOGIN NO CAMBIA ================= */

    /**
     * El login ya filtraba por estado = 1, así que no necesitó tocarse. Se
     * comprueba de verdad, por HTTP, que sigue entrando la cuenta activa y que
     * la desactivada que comparte correo no interfiere.
     */
    public function test_el_login_sigue_funcionando_con_un_correo_reutilizado(): void
    {
        // La vieja cuenta, desactivada, conserva el correo con OTRA clave.
        DB::table('usuarios')->insert([
            'tenant_id' => $this->negocioA,
            'id_rol' => 1,
            'usuario' => 'cuenta.vieja',
            'nombre' => 'Cuenta Vieja',
            'email' => 'reutilizado@test.local',
            'clave' => bcrypt('ClaveVieja'),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 0,
        ]);

        // La nueva, activa, con el mismo correo y su propia clave.
        $idNuevo = DB::table('usuarios')->insertGetId([
            'tenant_id' => $this->negocioB,
            'id_rol' => 1,
            'usuario' => 'cuenta.nueva',
            'nombre' => 'Cuenta Nueva',
            'email' => 'reutilizado@test.local',
            'clave' => bcrypt('ClaveNueva'),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $respuesta = $this->postJson('request/autenticacion/login', [
            'email' => 'reutilizado@test.local',
            'clave' => 'ClaveNueva',
        ]);

        $respuesta->assertJsonPath('error', 0);

        // Entró la cuenta activa, con su negocio, no la desactivada.
        $this->assertSame($idNuevo, session('id_usuario'));
        $this->assertSame($this->negocioB, session('tenant_id'));

        // Y la clave de la cuenta desactivada no sirve para entrar.
        $this->flushSession();

        $this->postJson('request/autenticacion/login', [
            'email' => 'reutilizado@test.local',
            'clave' => 'ClaveVieja',
        ])->assertJsonPath('error', 1);
    }

    /** Login normal, sin correos reutilizados de por medio. */
    public function test_el_login_normal_sigue_funcionando(): void
    {
        DB::table('usuarios')->insert([
            'tenant_id' => $this->negocioA,
            'id_rol' => 1,
            'usuario' => 'admin.normal',
            'nombre' => 'Admin Normal',
            'email' => 'normal@test.local',
            'clave' => bcrypt('Clave2026'),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $respuesta = $this->postJson('request/autenticacion/login', [
            'email' => 'normal@test.local',
            'clave' => 'Clave2026',
        ]);

        $respuesta->assertJsonPath('error', 0);
        $this->assertSame($this->negocioA, session('tenant_id'));
        $this->assertSame(VerificarSesion::CLAVE_SESION, session('app_sesion'));
    }

    /* ================= 6) REACTIVACIÓN Y FILTRO DE INACTIVOS ================= */

    private function editarPorHttp(int $idUsuario, int $tenantId, int $estado)
    {
        return $this->withSession($this->sesionAdmin($tenantId))
            ->postJson('request/usuario/editar', [
                'id_usuario' => $idUsuario,
                'usuario' => 'usuario.uno',
                'nombre' => 'Usuario Uno',
                'email' => 'uno@test.local',
                'id_rol' => 1,
                'tenant_id' => $tenantId,
                'estado' => $estado,
            ]);
    }

    public function test_editar_puede_desactivar_y_luego_reactivar_una_cuenta(): void
    {
        $idUsuario = $this->crearUsuario($this->negocioA, 'usuario.uno', 'uno@test.local');

        $this->editarPorHttp($idUsuario, $this->negocioA, 0)->assertJsonPath('error', 0);
        $this->assertSame(0, DB::table('usuarios')->where('id_usuario', $idUsuario)->value('estado'));

        $this->editarPorHttp($idUsuario, $this->negocioA, 1)->assertJsonPath('error', 0);
        $this->assertSame(1, DB::table('usuarios')->where('id_usuario', $idUsuario)->value('estado'));
    }

    public function test_editar_sin_estado_es_rechazado(): void
    {
        $idUsuario = $this->crearUsuario($this->negocioA, 'usuario.uno', 'uno@test.local');

        $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/usuario/editar', [
                'id_usuario' => $idUsuario,
                'usuario' => 'usuario.uno',
                'nombre' => 'Usuario Uno',
                'email' => 'uno@test.local',
                'id_rol' => 1,
                'tenant_id' => $this->negocioA,
            ])
            ->assertJsonPath('error', 1);
    }

    public function test_listar_no_muestra_inactivos_por_defecto_pero_si_al_pedirlos(): void
    {
        $this->crearUsuario($this->negocioA, 'activo', 'activo@test.local', 1, 1);
        $this->crearUsuario($this->negocioA, 'inactivo', 'inactivo@test.local', 1, 0);

        $svcUsuario = new \App\Service\SvcUsuario;

        $normal = $svcUsuario->listar($this->negocioA);
        $this->assertCount(1, $normal);
        $this->assertSame('activo', $normal[0]['usuario']);

        $conInactivos = $svcUsuario->listar($this->negocioA, true);
        $this->assertCount(2, $conInactivos);
    }

    public function test_el_endpoint_de_listar_respeta_incluir_inactivos(): void
    {
        $this->crearUsuario($this->negocioA, 'activo', 'activo@test.local', 1, 1);
        $this->crearUsuario($this->negocioA, 'inactivo', 'inactivo@test.local', 1, 0);

        $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/usuario/listar')
            ->assertJsonPath('error', 0)
            ->assertJsonCount(1, 'data.usuarios');

        $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/usuario/listar?incluir_inactivos=1')
            ->assertJsonPath('error', 0)
            ->assertJsonCount(2, 'data.usuarios');
    }

    /** El aislamiento tiene que seguir en pie con el parámetro nuevo. */
    public function test_listar_con_inactivos_no_mezcla_negocios(): void
    {
        $this->crearUsuario($this->negocioA, 'inactivo.a', 'ia@test.local', 1, 0);
        $this->crearUsuario($this->negocioB, 'inactivo.b', 'ib@test.local', 1, 0);

        $listadoA = (new \App\Service\SvcUsuario)->listar($this->negocioA, true);

        $this->assertCount(1, $listadoA);
        $this->assertSame('inactivo.a', $listadoA[0]['usuario']);
    }

    public function test_no_se_puede_cambiar_el_estado_de_un_usuario_de_otro_negocio(): void
    {
        $idDelB = $this->crearUsuario($this->negocioB, 'usuario.del.b', 'b@test.local', 1, 1);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/usuario/editar', [
                'id_usuario' => $idDelB,
                'usuario' => 'secuestrado',
                'nombre' => 'Secuestrado',
                'email' => 'secuestrado@test.local',
                'id_rol' => 1,
                'tenant_id' => $this->negocioA,
                'estado' => 0,
            ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertSame(1, DB::table('usuarios')->where('id_usuario', $idDelB)->value('estado'));
        $this->assertSame('usuario.del.b', DB::table('usuarios')->where('id_usuario', $idDelB)->value('usuario'));
    }

    /* ================= 7) RELACIÓN USUARIO -> EMPLEADO: COMPORTAMIENTO ACTUAL =================
     *
     * Documenta (no cambia) lo investigado: desactivar una CUENTA no desactiva
     * al empleado que la tiene vinculada. Es la dirección inversa de la cascada
     * empleado -> usuario, y queda como pregunta abierta de producto.
     */

    /* ================= 7) CASCADA USUARIO -> EMPLEADO ================= */

    private function crearEmpleado(int $tenantId, string $nombre, ?int $idUsuario, int $estado = 1): int
    {
        return DB::table('empleados')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'telefono' => '3000000000',
            'id_usuario' => $idUsuario,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => $estado,
        ]);
    }

    private function estadoEmpleado(int $idEmpleado): int
    {
        return (int) DB::table('empleados')->where('id_empleado', $idEmpleado)->value('estado');
    }

    private function estadoUsuario(int $idUsuario): int
    {
        return (int) DB::table('usuarios')->where('id_usuario', $idUsuario)->value('estado');
    }

    private function esAsignableAReservas(int $idEmpleado, int $tenantId): bool
    {
        $asignables = array_column((new \App\Service\SvcEmpleado)->listarActivos($tenantId), 'id_empleado');

        return in_array($idEmpleado, $asignables, false);
    }

    /**
     * Las líneas que el canal "database" lleva escritas hasta ahora.
     *
     * Es un canal "daily", así que el archivo del día es el único que puede
     * crecer durante la prueba. Comparando antes y después se cuenta con
     * exactitud cuántas veces se registró cada cascada.
     */
    private function lineasDeLog(): array
    {
        $ruta = storage_path('logs/database-'.date('Y-m-d').'.log');

        return file_exists($ruta) ? file($ruta) : [];
    }

    private function lineasNuevasDeLog(array $previas): array
    {
        return array_slice($this->lineasDeLog(), count($previas));
    }

    public function test_desactivar_un_usuario_desactiva_a_su_empleado_vinculado(): void
    {
        $idUsuario = $this->crearUsuario($this->negocioA, 'usuario.uno', 'uno@test.local', 2, 1);
        $idEmpleado = $this->crearEmpleado($this->negocioA, 'Empleado Vinculado', $idUsuario);

        $this->assertTrue($this->esAsignableAReservas($idEmpleado, $this->negocioA));

        $this->editarPorHttp($idUsuario, $this->negocioA, 0)->assertJsonPath('error', 0);

        $this->assertSame(0, $this->estadoUsuario($idUsuario));
        $this->assertSame(0, $this->estadoEmpleado($idEmpleado), 'El empleado vinculado tiene que caer con su cuenta');

        // La consecuencia fuerte de esta dirección: deja de poder asignarse a
        // reservas nuevas, no solo de entrar al panel.
        $this->assertFalse(
            $this->esAsignableAReservas($idEmpleado, $this->negocioA),
            'Un empleado dado de baja por cascada no puede seguir siendo asignable'
        );
    }

    public function test_desactivar_un_usuario_sin_empleado_vinculado_no_toca_la_tabla_de_empleados(): void
    {
        $idUsuario = $this->crearUsuario($this->negocioA, 'usuario.uno', 'uno@test.local');

        // Un empleado del mismo negocio, pero que no es de esta cuenta, y otro
        // con cuenta propia distinta: ninguno de los dos puede verse afectado.
        $idSinCuenta = $this->crearEmpleado($this->negocioA, 'Empleado Sin Cuenta', null);
        $idOtraCuenta = $this->crearEmpleado(
            $this->negocioA,
            'Empleado De Otra Cuenta',
            $this->crearUsuario($this->negocioA, 'usuario.dos', 'dos@test.local')
        );

        $this->editarPorHttp($idUsuario, $this->negocioA, 0)->assertJsonPath('error', 0);

        $this->assertSame(0, $this->estadoUsuario($idUsuario));
        $this->assertSame(1, $this->estadoEmpleado($idSinCuenta));
        $this->assertSame(1, $this->estadoEmpleado($idOtraCuenta));
        $this->assertSame(2, DB::table('empleados')->where('estado', 1)->count());
    }

    public function test_reactivar_un_usuario_nunca_reactiva_a_su_empleado(): void
    {
        $idUsuario = $this->crearUsuario($this->negocioA, 'usuario.uno', 'uno@test.local', 2, 1);
        $idEmpleado = $this->crearEmpleado($this->negocioA, 'Empleado Vinculado', $idUsuario);

        $this->editarPorHttp($idUsuario, $this->negocioA, 0)->assertJsonPath('error', 0);
        $this->assertSame(0, $this->estadoEmpleado($idEmpleado));

        $this->editarPorHttp($idUsuario, $this->negocioA, 1)->assertJsonPath('error', 0);

        $this->assertSame(1, $this->estadoUsuario($idUsuario), 'La cuenta sí vuelve');
        $this->assertSame(
            0,
            $this->estadoEmpleado($idEmpleado),
            'El empleado NO vuelve solo: darlo de alta es una acción aparte desde Empleados'
        );
        $this->assertFalse($this->esAsignableAReservas($idEmpleado, $this->negocioA));
    }

    /**
     * La papelera es el otro camino que deja un usuario inactivo. En la cascada
     * contraria fue justo ahí donde se coló el hueco, así que aquí se prueba.
     */
    public function test_la_papelera_de_usuarios_tambien_arrastra_al_empleado_vinculado(): void
    {
        $idUsuario = $this->crearUsuario($this->negocioA, 'usuario.uno', 'uno@test.local', 2, 1);
        $idEmpleado = $this->crearEmpleado($this->negocioA, 'Empleado Vinculado', $idUsuario);

        $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/usuario/eliminar', ['id_usuario' => $idUsuario])
            ->assertJsonPath('error', 0);

        $this->assertSame(0, $this->estadoUsuario($idUsuario));
        $this->assertSame(0, $this->estadoEmpleado($idEmpleado), 'La papelera no puede ser una puerta trasera');
    }

    /**
     * Solo cuenta la transición 1 -> 0. Si el admin reactivó al empleado a
     * propósito desde su módulo, volver a guardar la cuenta (que sigue
     * inactiva) no puede deshacérselo por la espalda.
     */
    public function test_guardar_un_usuario_ya_inactivo_no_vuelve_a_arrastrar_al_empleado(): void
    {
        $idUsuario = $this->crearUsuario($this->negocioA, 'usuario.uno', 'uno@test.local', 2, 1);
        $idEmpleado = $this->crearEmpleado($this->negocioA, 'Empleado Vinculado', $idUsuario);

        $this->editarPorHttp($idUsuario, $this->negocioA, 0)->assertJsonPath('error', 0);
        $this->assertSame(0, $this->estadoEmpleado($idEmpleado));

        // El admin lo da de alta otra vez desde Empleados, con la cuenta todavía
        // inactiva (puede seguir atendiendo aunque no entre al panel).
        DB::table('empleados')->where('id_empleado', $idEmpleado)->update(['estado' => 1]);

        // Y ahora alguien vuelve a guardar la cuenta sin tocar su estado.
        $this->editarPorHttp($idUsuario, $this->negocioA, 0)->assertJsonPath('error', 0);

        $this->assertSame(
            1,
            $this->estadoEmpleado($idEmpleado),
            'Sin transición no hay cascada: el empleado reactivado a mano se queda como está'
        );
    }

    /**
     * Anomalía de integridad: un empleado de otro negocio apuntando a esta
     * cuenta. No se toca esa fila ajena, y la baja entera se revierte: es la
     * prueba de que la transacción es de verdad todo o nada.
     */
    public function test_un_empleado_de_otro_negocio_vinculado_aborta_la_baja_entera(): void
    {
        $idUsuario = $this->crearUsuario($this->negocioA, 'usuario.uno', 'uno@test.local', 2, 1);
        $idEmpleadoAjeno = $this->crearEmpleado($this->negocioB, 'Empleado Del B', $idUsuario);

        $this->editarPorHttp($idUsuario, $this->negocioA, 0)->assertJsonPath('error', 1);

        $this->assertSame(1, $this->estadoUsuario($idUsuario), 'El usuario NO puede quedar dado de baja a medias');
        $this->assertSame(1, $this->estadoEmpleado($idEmpleadoAjeno), 'Y no se toca la fila del otro negocio');
    }

    public function test_la_cascada_no_alcanza_al_empleado_de_otro_negocio_desde_la_papelera(): void
    {
        $idUsuario = $this->crearUsuario($this->negocioA, 'usuario.uno', 'uno@test.local', 2, 1);
        $idEmpleadoAjeno = $this->crearEmpleado($this->negocioB, 'Empleado Del B', $idUsuario);

        $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/usuario/eliminar', ['id_usuario' => $idUsuario])
            ->assertJsonPath('error', 1);

        $this->assertSame(1, $this->estadoUsuario($idUsuario));
        $this->assertSame(1, $this->estadoEmpleado($idEmpleadoAjeno));
    }

    /**
     * ANTI-RECURSIÓN entre las dos cascadas.
     *
     * Ahora hay dos cascadas que se apuntan mutuamente. El corte está en que
     * ninguna llama al método de servicio del otro módulo: ambas escriben
     * directo sobre la columna estado. Esta prueba lo confirma con evidencia
     * medible: cada baja registra EXACTAMENTE una línea de cascada, la de su
     * propia dirección, y ninguna de vuelta.
     */
    public function test_las_dos_cascadas_no_se_disparan_entre_si_en_bucle(): void
    {
        $idUsuario = $this->crearUsuario($this->negocioA, 'empleado.uno', 'emp1@test.local', 2, 1);
        $idEmpleado = $this->crearEmpleado($this->negocioA, 'Empleado Vinculado', $idUsuario);

        /* ---- Dirección empleado -> usuario ---- */
        $previas = $this->lineasDeLog();
        $arranque = microtime(true);

        $this->assertTrue((new \App\Service\SvcEmpleado)->editar(
            $idEmpleado,
            ['nombre' => 'Empleado Vinculado', 'estado' => 0],
            $this->negocioA
        ));

        $duracionEmpleado = microtime(true) - $arranque;
        $nuevas = $this->lineasNuevasDeLog($previas);

        $haciaUsuario = array_values(array_filter($nuevas, fn ($linea) => str_contains($linea, 'se desactivó automáticamente su usuario')));
        $haciaEmpleado = array_values(array_filter($nuevas, fn ($linea) => str_contains($linea, 'se desactivó automáticamente su empleado')));

        $this->assertCount(1, $haciaUsuario, 'La cascada empleado -> usuario tiene que registrarse UNA sola vez');
        $this->assertCount(0, $haciaEmpleado, 'Y no puede rebotar de vuelta hacia el empleado');
        $this->assertSame(0, $this->estadoUsuario($idUsuario));
        $this->assertSame(0, $this->estadoEmpleado($idEmpleado));
        $this->assertLessThan(5, $duracionEmpleado, 'Una cascada en bucle se notaría como una demora anormal');

        /* ---- Dirección usuario -> empleado, sobre otro par ---- */
        $idUsuario2 = $this->crearUsuario($this->negocioA, 'empleado.dos', 'emp2@test.local', 2, 1);
        $idEmpleado2 = $this->crearEmpleado($this->negocioA, 'Otro Vinculado', $idUsuario2);

        $previas = $this->lineasDeLog();
        $arranque = microtime(true);

        $this->assertTrue((new \App\Service\SvcUsuario)->editar(
            $idUsuario2,
            ['nombre' => 'Empleado Dos', 'estado' => 0],
            $this->negocioA
        ));

        $duracionUsuario = microtime(true) - $arranque;
        $nuevas = $this->lineasNuevasDeLog($previas);

        $haciaUsuario = array_values(array_filter($nuevas, fn ($linea) => str_contains($linea, 'se desactivó automáticamente su usuario')));
        $haciaEmpleado = array_values(array_filter($nuevas, fn ($linea) => str_contains($linea, 'se desactivó automáticamente su empleado')));

        $this->assertCount(1, $haciaEmpleado, 'La cascada usuario -> empleado tiene que registrarse UNA sola vez');
        $this->assertCount(0, $haciaUsuario, 'Y no puede rebotar de vuelta hacia el usuario');
        $this->assertSame(0, $this->estadoUsuario($idUsuario2));
        $this->assertSame(0, $this->estadoEmpleado($idEmpleado2));
        $this->assertLessThan(5, $duracionUsuario, 'Una cascada en bucle se notaría como una demora anormal');
    }

    /**
     * El listado tiene que decir qué cuentas arrastran a un empleado: es lo que
     * le permite al modal avisar ANTES de guardar la baja.
     */
    public function test_el_listado_expone_el_empleado_vinculado_de_cada_cuenta(): void
    {
        $idConEmpleado = $this->crearUsuario($this->negocioA, 'usuario.uno', 'uno@test.local', 2, 1);
        $idEmpleado = $this->crearEmpleado($this->negocioA, 'Empleado Vinculado', $idConEmpleado);
        $idSinEmpleado = $this->crearUsuario($this->negocioA, 'usuario.dos', 'dos@test.local');

        $usuarios = collect($this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/usuario/listar')
            ->json('data.usuarios'))->keyBy('id_usuario');

        $this->assertSame($idEmpleado, (int) $usuarios[$idConEmpleado]['id_empleado_vinculado']);
        $this->assertSame('Empleado Vinculado', $usuarios[$idConEmpleado]['nombre_empleado_vinculado']);
        $this->assertNull($usuarios[$idSinEmpleado]['id_empleado_vinculado']);
    }

    /**
     * El log tiene que decir quién arrastró a quién, no solo que algo pasó.
     */
    public function test_la_cascada_queda_registrada_con_el_usuario_y_el_empleado_afectados(): void
    {
        $idUsuario = $this->crearUsuario($this->negocioA, 'usuario.uno', 'uno@test.local', 2, 1);
        $idEmpleado = $this->crearEmpleado($this->negocioA, 'Empleado Vinculado', $idUsuario);

        $previas = $this->lineasDeLog();

        $this->editarPorHttp($idUsuario, $this->negocioA, 0)->assertJsonPath('error', 0);

        $registro = implode('', $this->lineasNuevasDeLog($previas));

        $this->assertStringContainsString('al desactivar el usuario '.$idUsuario, $registro);
        $this->assertStringContainsString('su empleado '.$idEmpleado, $registro);
        $this->assertStringContainsString('negocio '.$this->negocioA, $registro);
    }
}
