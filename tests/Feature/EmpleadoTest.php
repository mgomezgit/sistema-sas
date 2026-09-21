<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Service\SvcEmpleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Empleados: reactivación desde el modal de edición y el filtro de inactivos,
 * mismo patrón ya construido y probado en Productos, Clientes y Recursos.
 *
 * El bloque 4 cubre aparte la cascada de seguridad: dar de baja a un empleado
 * le revoca el acceso al sistema, y reactivarlo NO se lo devuelve.
 */
class EmpleadoTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    private SvcEmpleado $svcEmpleado;

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
        $this->svcEmpleado = new SvcEmpleado;
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

    private function crearEmpleado(int $tenantId, array $sobrescribe = []): int
    {
        return DB::table('empleados')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'nombre' => 'Empleado de prueba',
            'telefono' => '3000000000',
            'email' => null,
            'cargo' => null,
            'porcentaje_comision' => null,
            'id_usuario' => null,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ], $sobrescribe));
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

    /* ================= 1) REACTIVAR UN EMPLEADO DESDE EL MODAL ================= */

    public function test_editar_puede_desactivar_y_luego_reactivar_un_empleado(): void
    {
        $idEmpleado = $this->crearEmpleado($this->negocioA, ['nombre' => 'Empleado Uno']);

        $desactivar = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/empleado/editar', [
                'id_empleado' => $idEmpleado,
                'nombre' => 'Empleado Uno',
                'telefono' => '3000000000',
                'estado' => 0,
            ]);

        $desactivar->assertJsonPath('error', 0);
        $this->assertSame(0, DB::table('empleados')->where('id_empleado', $idEmpleado)->value('estado'));

        $reactivar = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/empleado/editar', [
                'id_empleado' => $idEmpleado,
                'nombre' => 'Empleado Uno',
                'telefono' => '3000000000',
                'estado' => 1,
            ]);

        $reactivar->assertJsonPath('error', 0);
        $this->assertSame(1, DB::table('empleados')->where('id_empleado', $idEmpleado)->value('estado'));
    }

    public function test_editar_sin_estado_es_rechazado(): void
    {
        $idEmpleado = $this->crearEmpleado($this->negocioA, ['nombre' => 'Empleado Uno']);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/empleado/editar', [
                'id_empleado' => $idEmpleado,
                'nombre' => 'Empleado Uno',
                'telefono' => '3000000000',
            ]);

        $respuesta->assertJsonPath('error', 1);
    }

    /* ================= 2) FILTRO DE INACTIVOS EN listar() ================= */

    public function test_listar_no_muestra_inactivos_por_defecto(): void
    {
        $this->crearEmpleado($this->negocioA, ['nombre' => 'Activo', 'estado' => 1]);
        $this->crearEmpleado($this->negocioA, ['nombre' => 'Inactivo', 'estado' => 0]);

        $listado = $this->svcEmpleado->listar($this->negocioA);

        $nombres = array_column($listado, 'nombre');

        $this->assertContains('Activo', $nombres);
        $this->assertNotContains('Inactivo', $nombres);
        $this->assertCount(1, $listado);
    }

    public function test_listar_incluye_inactivos_cuando_se_pide_explicitamente(): void
    {
        $this->crearEmpleado($this->negocioA, ['nombre' => 'Activo', 'estado' => 1]);
        $this->crearEmpleado($this->negocioA, ['nombre' => 'Inactivo', 'estado' => 0]);

        $listado = $this->svcEmpleado->listar($this->negocioA, true);

        $nombres = array_column($listado, 'nombre');

        $this->assertContains('Activo', $nombres);
        $this->assertContains('Inactivo', $nombres);
        $this->assertCount(2, $listado);
    }

    public function test_el_endpoint_de_listar_respeta_incluir_inactivos(): void
    {
        $this->crearEmpleado($this->negocioA, ['nombre' => 'Activo', 'estado' => 1]);
        $this->crearEmpleado($this->negocioA, ['nombre' => 'Inactivo', 'estado' => 0]);

        $normal = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/empleado/listar');

        $normal->assertJsonPath('error', 0);
        $this->assertCount(1, $normal->json('data.empleados'));

        $conInactivos = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/empleado/listar?incluir_inactivos=1');

        $conInactivos->assertJsonPath('error', 0);
        $this->assertCount(2, $conInactivos->json('data.empleados'));
    }

    /* ================= 3) AISLAMIENTO DE TENANT AL CAMBIAR ESTADO ================= */

    public function test_no_se_puede_reactivar_un_empleado_de_otro_negocio(): void
    {
        $idDelB = $this->crearEmpleado($this->negocioB, ['nombre' => 'Del B', 'estado' => 0]);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/empleado/editar', [
                'id_empleado' => $idDelB,
                'nombre' => 'Secuestrado',
                'telefono' => '3009999999',
                'estado' => 1,
            ]);

        $respuesta->assertJsonPath('error', 1);
        // Sigue inactivo y con su nombre original: el negocio A no lo tocó.
        $this->assertSame(0, DB::table('empleados')->where('id_empleado', $idDelB)->value('estado'));
        $this->assertSame('Del B', DB::table('empleados')->where('id_empleado', $idDelB)->value('nombre'));
    }

    public function test_no_se_puede_desactivar_un_empleado_de_otro_negocio(): void
    {
        $idDelB = $this->crearEmpleado($this->negocioB, ['nombre' => 'Del B', 'estado' => 1]);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/empleado/editar', [
                'id_empleado' => $idDelB,
                'nombre' => 'Secuestrado',
                'telefono' => '3009999999',
                'estado' => 0,
            ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertSame(1, DB::table('empleados')->where('id_empleado', $idDelB)->value('estado'));
    }

    /**
     * El aislamiento de listar() con el nuevo parámetro: el negocio B no debe
     * asomarse en el conteo del A ni siquiera pidiendo los inactivos.
     */
    public function test_listar_con_inactivos_no_mezcla_negocios(): void
    {
        $this->crearEmpleado($this->negocioA, ['nombre' => 'Inactivo A', 'estado' => 0]);
        $this->crearEmpleado($this->negocioB, ['nombre' => 'Inactivo B', 'estado' => 0]);

        $listadoA = $this->svcEmpleado->listar($this->negocioA, true);

        $this->assertCount(1, $listadoA);
        $this->assertSame('Inactivo A', $listadoA[0]['nombre']);
    }

    /* ================= 4) CASCADA DE BAJA: EMPLEADO -> USUARIO =================
     *
     * Dar de baja a un empleado le quita también el acceso al sistema.
     * Reactivarlo NO se lo devuelve: eso exige una acción aparte y explícita
     * desde el módulo de Usuarios. La asimetría es a propósito.
     */

    /** Crea un usuario con acceso y devuelve su id. */
    private function crearUsuarioConAcceso(int $tenantId, array $sobrescribe = []): int
    {
        $idRolEmpleado = DB::table('roles')->where('nombre_rol', 'empleado')->value('id_rol');

        return DB::table('usuarios')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'id_rol' => $idRolEmpleado,
            'usuario' => 'empleado.con.acceso',
            'nombre' => 'Empleado Con Acceso',
            'email' => 'conacceso@test.local',
            'clave' => bcrypt('Clave2026'),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ], $sobrescribe));
    }

    private function desactivarPorHttp(int $idEmpleado, int $tenantId)
    {
        return $this->withSession($this->sesionAdmin($tenantId))
            ->postJson('request/empleado/editar', [
                'id_empleado' => $idEmpleado,
                'nombre' => 'Empleado Con Acceso',
                'telefono' => '3000000000',
                'estado' => 0,
            ]);
    }

    public function test_desactivar_un_empleado_con_acceso_desactiva_tambien_su_usuario(): void
    {
        $idUsuario = $this->crearUsuarioConAcceso($this->negocioA);
        $idEmpleado = $this->crearEmpleado($this->negocioA, [
            'nombre' => 'Empleado Con Acceso',
            'id_usuario' => $idUsuario,
        ]);

        $this->desactivarPorHttp($idEmpleado, $this->negocioA)->assertJsonPath('error', 0);

        $this->assertSame(0, DB::table('empleados')->where('id_empleado', $idEmpleado)->value('estado'));
        $this->assertSame(
            0,
            DB::table('usuarios')->where('id_usuario', $idUsuario)->value('estado'),
            'Al dar de baja al empleado, su usuario debe quedar desactivado'
        );
    }

    /**
     * La consecuencia que motiva toda la cascada: una vez revocado, el login
     * deja de encontrarlo, así que ya no puede entrar ni con su clave real.
     */
    public function test_el_usuario_de_un_empleado_desactivado_ya_no_puede_iniciar_sesion(): void
    {
        $idUsuario = $this->crearUsuarioConAcceso($this->negocioA);
        $idEmpleado = $this->crearEmpleado($this->negocioA, [
            'nombre' => 'Empleado Con Acceso',
            'id_usuario' => $idUsuario,
        ]);

        // Antes de la baja sí entra.
        $this->postJson('request/autenticacion/login', [
            'email' => 'conacceso@test.local',
            'clave' => 'Clave2026',
        ])->assertJsonPath('error', 0);

        $this->flushSession();

        $this->desactivarPorHttp($idEmpleado, $this->negocioA)->assertJsonPath('error', 0);

        // Se limpia la sesión del admin que acaba de hacer la baja, para que el
        // intento siguiente parta de cero y lo que quede en sesión sea solo lo
        // que el propio login haya establecido.
        $this->flushSession();

        // Después de la baja, la misma clave real ya no sirve.
        $this->postJson('request/autenticacion/login', [
            'email' => 'conacceso@test.local',
            'clave' => 'Clave2026',
        ])->assertJsonPath('error', 1);

        $this->assertNull(session('id_usuario'), 'El login fallido no debe dejar ninguna sesión iniciada');
    }

    /** La baja lógica de la papelera arrastra la misma cascada que el interruptor. */
    public function test_eliminar_un_empleado_con_acceso_tambien_desactiva_su_usuario(): void
    {
        $idUsuario = $this->crearUsuarioConAcceso($this->negocioA);
        $idEmpleado = $this->crearEmpleado($this->negocioA, [
            'nombre' => 'Empleado Con Acceso',
            'id_usuario' => $idUsuario,
        ]);

        $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/empleado/eliminar', ['id_empleado' => $idEmpleado])
            ->assertJsonPath('error', 0);

        $this->assertSame(0, DB::table('empleados')->where('id_empleado', $idEmpleado)->value('estado'));
        $this->assertSame(
            0,
            DB::table('usuarios')->where('id_usuario', $idUsuario)->value('estado'),
            'La papelera no puede ser una puerta trasera que deje el acceso vivo'
        );
    }

    /** Sin acceso vinculado no hay nada que revocar, y nada debe romperse. */
    public function test_desactivar_un_empleado_sin_acceso_no_afecta_a_ningun_usuario(): void
    {
        $idUsuarioAjeno = $this->crearUsuarioConAcceso($this->negocioA);

        $idEmpleado = $this->crearEmpleado($this->negocioA, [
            'nombre' => 'Empleado Con Acceso',
            'id_usuario' => null,
        ]);

        $this->desactivarPorHttp($idEmpleado, $this->negocioA)->assertJsonPath('error', 0);

        $this->assertSame(0, DB::table('empleados')->where('id_empleado', $idEmpleado)->value('estado'));
        // Ningún usuario del negocio se vio tocado.
        $this->assertSame(1, DB::table('usuarios')->where('id_usuario', $idUsuarioAjeno)->value('estado'));
        $this->assertSame(1, DB::table('usuarios')->where('estado', 1)->count());
    }

    /**
     * La asimetría, que es el punto delicado: reactivar al empleado NO le
     * devuelve el acceso. Debe seguir sin poder entrar hasta que alguien
     * reactive su usuario a propósito.
     */
    public function test_reactivar_un_empleado_nunca_reactiva_su_usuario(): void
    {
        $idUsuario = $this->crearUsuarioConAcceso($this->negocioA, ['estado' => 0]);
        $idEmpleado = $this->crearEmpleado($this->negocioA, [
            'nombre' => 'Empleado Con Acceso',
            'id_usuario' => $idUsuario,
            'estado' => 0,
        ]);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/empleado/editar', [
                'id_empleado' => $idEmpleado,
                'nombre' => 'Empleado Con Acceso',
                'telefono' => '3000000000',
                'estado' => 1,
            ]);

        $respuesta->assertJsonPath('error', 0);
        $this->assertSame(1, DB::table('empleados')->where('id_empleado', $idEmpleado)->value('estado'));

        $this->assertSame(
            0,
            DB::table('usuarios')->where('id_usuario', $idUsuario)->value('estado'),
            'Reactivar al empleado no debe devolverle el acceso por la espalda'
        );

        // Y en la práctica: sigue sin poder entrar.
        $this->postJson('request/autenticacion/login', [
            'email' => 'conacceso@test.local',
            'clave' => 'Clave2026',
        ])->assertJsonPath('error', 1);
    }

    /**
     * Guardar un empleado que YA estaba inactivo no vuelve a revocar nada: si
     * el admin reactivó el usuario a propósito, editarle el teléfono no puede
     * deshacer esa decisión sin avisar.
     */
    public function test_guardar_un_empleado_ya_inactivo_no_vuelve_a_revocar_el_acceso(): void
    {
        // El admin reactivó el usuario a mano, aunque el empleado siga de baja.
        $idUsuario = $this->crearUsuarioConAcceso($this->negocioA, ['estado' => 1]);
        $idEmpleado = $this->crearEmpleado($this->negocioA, [
            'nombre' => 'Empleado Con Acceso',
            'id_usuario' => $idUsuario,
            'estado' => 0,
        ]);

        // Se guarda el empleado sin cambiarle el estado (sigue inactivo).
        $this->desactivarPorHttp($idEmpleado, $this->negocioA)->assertJsonPath('error', 0);

        $this->assertSame(
            1,
            DB::table('usuarios')->where('id_usuario', $idUsuario)->value('estado'),
            'No hubo transición de activo a inactivo, así que no debe tocarse el usuario'
        );
    }

    /**
     * Atomicidad y aislamiento a la vez: un empleado que apunta a un usuario de
     * OTRO negocio es una anomalía. No se toca ese usuario ajeno, pero tampoco
     * se da por buena la baja (quedaría un acceso vivo que el admin cree
     * revocado): la transacción entera se revierte.
     */
    public function test_si_falla_la_cascada_el_empleado_tampoco_queda_desactivado(): void
    {
        // Usuario del negocio B, mal referenciado por un empleado del negocio A.
        $idUsuarioDelB = $this->crearUsuarioConAcceso($this->negocioB);

        $idEmpleado = $this->crearEmpleado($this->negocioA, [
            'nombre' => 'Empleado Con Acceso',
            'id_usuario' => $idUsuarioDelB,
        ]);

        $respuesta = $this->desactivarPorHttp($idEmpleado, $this->negocioA);

        $respuesta->assertJsonPath('error', 1);

        // Todo o nada: el empleado sigue ACTIVO porque la cascada no pudo aplicarse.
        $this->assertSame(
            1,
            DB::table('empleados')->where('id_empleado', $idEmpleado)->value('estado'),
            'Si no se pudo revocar el acceso, la baja del empleado debe revertirse'
        );

        // Y el usuario del otro negocio quedó intacto.
        $this->assertSame(1, DB::table('usuarios')->where('id_usuario', $idUsuarioDelB)->value('estado'));
    }
}
