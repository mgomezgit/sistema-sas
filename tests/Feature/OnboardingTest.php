<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresión del progreso de onboarding (bienvenida, drawer, conteo de pasos).
 *
 * El drawer del backoffice muestra "completados/6" y pinta cada paso a partir
 * de lo que devuelve request/negocio/progreso-onboarding, así que estas
 * pruebas verifican ese endpoint (y los dos que lo modifican) contra el
 * estado real de la base de datos: nunca contra un mock del Service.
 *
 * Lo que NO cubren estas pruebas, y por qué, queda documentado al final del
 * archivo.
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->tenantId = $this->crearNegocio('Negocio Nuevo');
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre, array $sobreescribir = []): int
    {
        return DB::table('negocios')->insertGetId(array_merge([
            'nombre_negocio' => $nombre,
            'rubro' => 'spa',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
            // Los defaults de la migración: arranca todo en falso.
            'tema_personalizado' => false,
            'tour_completado' => false,
            'bienvenida_vista' => false,
        ], $sobreescribir));
    }

    private function crearProducto(int $tenantId, array $sobreescribir = []): int
    {
        return DB::table('productos')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'nombre' => 'Producto de prueba',
            'cantidad_actual' => 10,
            'cantidad_minima' => 2,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ], $sobreescribir));
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

    private function sesionEmpleado(int $tenantId): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 2,
            'usuario' => 'empleado.test',
            'nombre_usuario' => 'Empleado Test',
            'tenant_id' => $tenantId,
            'id_rol' => 2,
        ];
    }

    private function sesionSuperAdmin(): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 3,
            'usuario' => 'superadmin.test',
            'nombre_usuario' => 'Super Admin',
            'tenant_id' => null,
            'id_rol' => 3,
        ];
    }

    /** Lee el bloque "onboarding" tal como lo consume el drawer. */
    private function obtenerProgreso(array $sesion): array
    {
        $respuesta = $this->withSession($sesion)->getJson('request/negocio/progreso-onboarding');

        return $respuesta->json('data.onboarding');
    }

    private function pasoPorId(array $onboarding, string $id): array
    {
        return array_values(array_filter($onboarding['pasos'], fn ($paso) => $paso['id'] === $id))[0];
    }

    /* ================= 1) ESTADO INICIAL: LOS 6 PASOS PENDIENTES ================= */

    public function test_negocio_nuevo_arranca_con_los_ocho_pasos_pendientes(): void
    {
        $onboarding = $this->obtenerProgreso($this->sesionAdmin($this->tenantId));

        $this->assertFalse($onboarding['tour_completado']);
        $this->assertFalse($onboarding['bienvenida_vista']);
        $this->assertCount(8, $onboarding['pasos']);

        // Los dos pasos nuevos existen y llegan con el mismo formato que el resto.
        $ids = array_column($onboarding['pasos'], 'id');
        $this->assertContains('inventario', $ids);
        $this->assertContains('reportes', $ids);

        foreach ($onboarding['pasos'] as $paso) {
            $this->assertFalse($paso['completado'], 'El paso "'.$paso['id'].'" no debería estar completado todavía');
        }
    }

    /* ================= 2) CADA PASO SE COMPLETA POR SU CONDICIÓN REAL ================= */

    public function test_paso_personalizar_se_completa_al_guardar_el_tema(): void
    {
        $antes = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'personalizar');
        $this->assertFalse($antes['completado']);

        $respuesta = $this->withSession($this->sesionAdmin($this->tenantId))
            ->postJson('request/negocio/actualizar-tema', [
                'modo_tema' => 'oscuro',
                'color_acento' => 'dorado',
            ]);

        $respuesta->assertJsonPath('error', 0);

        $despues = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'personalizar');
        $this->assertTrue($despues['completado']);
    }

    public function test_paso_horario_se_completa_cuando_el_negocio_tiene_dias_de_atencion(): void
    {
        $antes = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'horario');
        $this->assertFalse($antes['completado']);

        DB::table('negocios')->where('id_negocio', $this->tenantId)->update([
            'dias_atencion' => '1,2,3,4,5',
        ]);

        $despues = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'horario');
        $this->assertTrue($despues['completado']);
    }

    public function test_paso_recurso_se_completa_con_un_recurso_activo_e_ignora_los_inactivos(): void
    {
        $antes = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'recurso');
        $this->assertFalse($antes['completado']);

        DB::table('recursos_reservables')->insert([
            'tenant_id' => $this->tenantId,
            'nombre' => 'Inactivo',
            'duracion_minutos' => 30,
            'precio' => 10000,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 0,
        ]);

        // Solo existe uno, y está inactivo: el paso sigue pendiente.
        $conSoloInactivo = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'recurso');
        $this->assertFalse($conSoloInactivo['completado']);

        DB::table('recursos_reservables')->insert([
            'tenant_id' => $this->tenantId,
            'nombre' => 'Activo',
            'duracion_minutos' => 30,
            'precio' => 10000,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $despues = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'recurso');
        $this->assertTrue($despues['completado']);
    }

    public function test_paso_empleado_se_completa_con_un_empleado_activo(): void
    {
        DB::table('empleados')->insert([
            'tenant_id' => $this->tenantId,
            'nombre' => 'Empleado Activo',
            'telefono' => '3000000000',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $paso = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'empleado');
        $this->assertTrue($paso['completado']);
    }

    public function test_paso_cliente_se_completa_con_un_cliente_activo(): void
    {
        DB::table('clientes')->insert([
            'tenant_id' => $this->tenantId,
            'nombre' => 'Cliente Activo',
            'telefono' => '3000000000',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $paso = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'cliente');
        $this->assertTrue($paso['completado']);
    }

    public function test_paso_reserva_se_completa_con_una_reserva_activa(): void
    {
        $idCliente = DB::table('clientes')->insertGetId([
            'tenant_id' => $this->tenantId,
            'nombre' => 'Cliente',
            'telefono' => '3000000000',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
        $idRecurso = DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $this->tenantId,
            'nombre' => 'Servicio',
            'duracion_minutos' => 30,
            'precio' => 10000,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $antes = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'reserva');
        $this->assertFalse($antes['completado']);

        DB::table('reservas')->insert([
            'tenant_id' => $this->tenantId,
            'id_cliente' => $idCliente,
            'id_recurso' => $idRecurso,
            'fecha_reserva' => '2026-01-10',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado_reserva' => 'pendiente',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $despues = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'reserva');
        $this->assertTrue($despues['completado']);
    }

    /* ================= PASOS NUEVOS: INVENTARIO Y REPORTES ================= */

    public function test_paso_inventario_se_completa_con_un_producto_activo(): void
    {
        $antes = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'inventario');
        $this->assertFalse($antes['completado']);

        $this->crearProducto($this->tenantId);

        $despues = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'inventario');
        $this->assertTrue($despues['completado']);
    }

    public function test_paso_inventario_ignora_productos_inactivos(): void
    {
        $this->crearProducto($this->tenantId, ['estado' => 0]);

        $paso = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'inventario');
        $this->assertFalse($paso['completado']);
    }

    /**
     * Aislamiento multi-tenant: el producto de otro negocio no puede dar por
     * cumplido el paso del negocio propio.
     */
    public function test_paso_inventario_no_cuenta_productos_de_otro_negocio(): void
    {
        $otroNegocio = $this->crearNegocio('Otro Negocio');
        $this->crearProducto($otroNegocio);

        $propio = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'inventario');
        $ajeno = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($otroNegocio)), 'inventario');

        $this->assertFalse($propio['completado']);
        $this->assertTrue($ajeno['completado']);
    }

    public function test_paso_reportes_se_completa_al_marcar_el_tour_visto(): void
    {
        $antes = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'reportes');
        $this->assertFalse($antes['completado']);

        $respuesta = $this->withSession($this->sesionAdmin($this->tenantId))
            ->postJson('request/negocio/marcar-reportes-tour');

        $respuesta->assertJsonPath('error', 0);

        $despues = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'reportes');
        $this->assertTrue($despues['completado']);
        $this->assertSame(1, DB::table('negocios')->where('id_negocio', $this->tenantId)->value('reportes_tour_visto'));
    }

    /**
     * El flag se escribe SIEMPRE sobre el negocio de la sesión: no hay forma de
     * marcar el de otro, porque el id ni siquiera se acepta por parámetro.
     */
    public function test_marcar_reportes_tour_solo_afecta_al_negocio_de_la_sesion(): void
    {
        $otroNegocio = $this->crearNegocio('Otro Negocio');

        $this->withSession($this->sesionAdmin($this->tenantId))
            ->postJson('request/negocio/marcar-reportes-tour', ['tenant_id' => $otroNegocio])
            ->assertJsonPath('error', 0);

        $this->assertSame(1, DB::table('negocios')->where('id_negocio', $this->tenantId)->value('reportes_tour_visto'));
        // El negocio que viajaba en el cuerpo de la petición quedó intacto.
        $this->assertSame(0, DB::table('negocios')->where('id_negocio', $otroNegocio)->value('reportes_tour_visto'));

        $pasoAjeno = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($otroNegocio)), 'reportes');
        $this->assertFalse($pasoAjeno['completado']);
    }

    public function test_marcar_reportes_tour_rechazado_para_empleado_y_super_admin(): void
    {
        $comoEmpleado = $this->withSession($this->sesionEmpleado($this->tenantId))
            ->postJson('request/negocio/marcar-reportes-tour');

        $comoEmpleado->assertJsonPath('error', 1);
        $this->assertStringContainsString('No tienes permiso', $comoEmpleado->json('mensaje'));

        $comoSuperAdmin = $this->withSession($this->sesionSuperAdmin())
            ->postJson('request/negocio/marcar-reportes-tour');

        $comoSuperAdmin->assertJsonPath('error', 1);
        $this->assertStringContainsString('cuenta de cada negocio', $comoSuperAdmin->json('mensaje'));

        $this->assertSame(0, DB::table('negocios')->where('id_negocio', $this->tenantId)->value('reportes_tour_visto'));
    }

    public function test_todos_los_pasos_completados_cuando_el_negocio_ya_avanzo_en_todo(): void
    {
        $negocioAvanzado = $this->crearNegocio('Negocio Avanzado', [
            'dias_atencion' => '1,2,3,4,5',
            'tema_personalizado' => true,
            'reportes_tour_visto' => true,
        ]);

        DB::table('productos')->insert([
            'tenant_id' => $negocioAvanzado, 'nombre' => 'Shampoo', 'cantidad_actual' => 5,
            'cantidad_minima' => 2, 'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'), 'estado' => 1,
        ]);

        DB::table('recursos_reservables')->insert([
            'tenant_id' => $negocioAvanzado, 'nombre' => 'Servicio', 'duracion_minutos' => 30,
            'precio' => 10000, 'usuario_registra' => 'test', 'fecha_registro' => date('Y-m-d H:i:s'), 'estado' => 1,
        ]);
        DB::table('empleados')->insert([
            'tenant_id' => $negocioAvanzado, 'nombre' => 'Empleado', 'telefono' => '3000000000',
            'usuario_registra' => 'test', 'fecha_registro' => date('Y-m-d H:i:s'), 'estado' => 1,
        ]);
        $idCliente = DB::table('clientes')->insertGetId([
            'tenant_id' => $negocioAvanzado, 'nombre' => 'Cliente', 'telefono' => '3000000000',
            'usuario_registra' => 'test', 'fecha_registro' => date('Y-m-d H:i:s'), 'estado' => 1,
        ]);
        $idRecurso = DB::table('recursos_reservables')->where('tenant_id', $negocioAvanzado)->value('id_recurso');
        DB::table('reservas')->insert([
            'tenant_id' => $negocioAvanzado, 'id_cliente' => $idCliente, 'id_recurso' => $idRecurso,
            'fecha_reserva' => '2026-01-10', 'hora_inicio' => '10:00:00', 'hora_fin' => '11:00:00',
            'estado_reserva' => 'pendiente', 'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'), 'estado' => 1,
        ]);

        $onboarding = $this->obtenerProgreso($this->sesionAdmin($negocioAvanzado));

        foreach ($onboarding['pasos'] as $paso) {
            $this->assertTrue($paso['completado'], 'El paso "'.$paso['id'].'" debería estar completado');
        }
    }

    /**
     * El progreso de un negocio nunca debe filtrarse al consultar el de otro
     * (mismo principio de aislamiento multi-tenant que el resto de módulos).
     */
    public function test_el_progreso_de_un_negocio_no_se_mezcla_con_el_de_otro(): void
    {
        $otroNegocio = $this->crearNegocio('Otro Negocio', ['dias_atencion' => '1,2,3,4,5']);

        $propio = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($this->tenantId)), 'horario');
        $ajeno = $this->pasoPorId($this->obtenerProgreso($this->sesionAdmin($otroNegocio)), 'horario');

        $this->assertFalse($propio['completado']);
        $this->assertTrue($ajeno['completado']);
    }

    /* ================= 3) BIENVENIDA_VISTA ================= */

    public function test_marcar_bienvenida_vista_cambia_el_estado_de_false_a_true(): void
    {
        $this->assertFalse($this->obtenerProgreso($this->sesionAdmin($this->tenantId))['bienvenida_vista']);

        $respuesta = $this->withSession($this->sesionAdmin($this->tenantId))
            ->postJson('request/negocio/marcar-bienvenida');

        $respuesta->assertJsonPath('error', 0);

        $this->assertTrue($this->obtenerProgreso($this->sesionAdmin($this->tenantId))['bienvenida_vista']);
        $this->assertSame(1, DB::table('negocios')->where('id_negocio', $this->tenantId)->value('bienvenida_vista'));
    }

    public function test_marcar_bienvenida_vista_es_idempotente(): void
    {
        $this->withSession($this->sesionAdmin($this->tenantId))->postJson('request/negocio/marcar-bienvenida');

        $segundaVez = $this->withSession($this->sesionAdmin($this->tenantId))
            ->postJson('request/negocio/marcar-bienvenida');

        $segundaVez->assertJsonPath('error', 0);
        $this->assertTrue($this->obtenerProgreso($this->sesionAdmin($this->tenantId))['bienvenida_vista']);
    }

    /* ================= 4) TOUR_COMPLETADO ================= */

    public function test_completar_onboarding_marca_tour_completado_en_true(): void
    {
        $this->assertFalse($this->obtenerProgreso($this->sesionAdmin($this->tenantId))['tour_completado']);

        $respuesta = $this->withSession($this->sesionAdmin($this->tenantId))
            ->postJson('request/negocio/completar-onboarding');

        $respuesta->assertJsonPath('error', 0);

        $this->assertTrue($this->obtenerProgreso($this->sesionAdmin($this->tenantId))['tour_completado']);
    }

    /* ================= 5) RESPUESTA NEUTRA PARA EMPLEADO Y SUPER ADMIN ================= */

    /**
     * Regresión directa del bug de conteo: independientemente del estado real
     * del negocio, un empleado o el super admin siempre deben recibir la
     * forma "ya terminado" (tour_completado=true, bienvenida_vista=true,
     * pasos=[]), para que el drawer nunca se les muestre.
     */
    public function test_empleado_y_super_admin_reciben_progreso_neutro_sin_importar_el_estado_real_del_negocio(): void
    {
        // El negocio real sigue con todo pendiente...
        $onboardingReal = $this->obtenerProgreso($this->sesionAdmin($this->tenantId));
        $this->assertFalse($onboardingReal['tour_completado']);

        // ...pero el empleado de ese mismo negocio ve la forma neutra.
        $comoEmpleado = $this->obtenerProgreso($this->sesionEmpleado($this->tenantId));
        $this->assertTrue($comoEmpleado['tour_completado']);
        $this->assertTrue($comoEmpleado['bienvenida_vista']);
        $this->assertSame([], $comoEmpleado['pasos']);

        $comoSuperAdmin = $this->obtenerProgreso($this->sesionSuperAdmin());
        $this->assertTrue($comoSuperAdmin['tour_completado']);
        $this->assertTrue($comoSuperAdmin['bienvenida_vista']);
        $this->assertSame([], $comoSuperAdmin['pasos']);
    }

    /* ================= 6) CONTROL DE ACCESO ================= */

    public function test_marcar_bienvenida_y_completar_onboarding_rechazados_para_empleado(): void
    {
        $sesionEmpleado = $this->sesionEmpleado($this->tenantId);

        $bienvenida = $this->withSession($sesionEmpleado)->postJson('request/negocio/marcar-bienvenida');
        $bienvenida->assertJsonPath('error', 1);
        $this->assertStringContainsString('No tienes permiso', $bienvenida->json('mensaje'));

        $completar = $this->withSession($sesionEmpleado)->postJson('request/negocio/completar-onboarding');
        $completar->assertJsonPath('error', 1);
        $this->assertStringContainsString('No tienes permiso', $completar->json('mensaje'));

        $this->assertSame(0, DB::table('negocios')->where('id_negocio', $this->tenantId)->value('bienvenida_vista'));
        $this->assertSame(0, DB::table('negocios')->where('id_negocio', $this->tenantId)->value('tour_completado'));
    }

    public function test_marcar_bienvenida_y_completar_onboarding_rechazados_para_super_admin(): void
    {
        $sesionSuper = $this->sesionSuperAdmin();

        $bienvenida = $this->withSession($sesionSuper)->postJson('request/negocio/marcar-bienvenida');
        $bienvenida->assertJsonPath('error', 1);
        $this->assertStringContainsString('cuenta de cada negocio', $bienvenida->json('mensaje'));

        $completar = $this->withSession($sesionSuper)->postJson('request/negocio/completar-onboarding');
        $completar->assertJsonPath('error', 1);
        $this->assertStringContainsString('cuenta de cada negocio', $completar->json('mensaje'));
    }
}

/*
 * ================= FUERA DE ALCANCE DE PHPUNIT =================
 *
 * Lo siguiente vive en resources/views/layout/backoffice.blade.php como JS
 * puro que corre en el navegador, sin ida al backend más allá de la lectura
 * de request/negocio/progreso-onboarding (que sí está cubierta arriba). No se
 * fuerza una prueba de Feature/HTTP para esto porque no probaría nada real:
 *
 * 1) Actualización en vivo sin recargar la página: cada guardado exitoso en
 *    cualquier módulo llama a window.avisarGuardado(), que vuelve a pedir el
 *    progreso y repinta el drawer (pintarDrawerOnboarding(), líneas ~2051 a
 *    2175). Que el badge "#conteo-onboarding" y la barra de progreso se
 *    actualicen SIN refrescar depende de que ese callback jQuery se dispare
 *    y del DOM real, algo que un test de Feature (que solo hace peticiones
 *    HTTP) no ejecuta.
 *
 * 2) El conteo mostrado en el badge (`completados + '/' + total`, línea 2096)
 *    es aritmética trivial sobre el array "pasos" que ya devuelve el backend;
 *    lo relevante para el bug de conteo es que ese array venga bien armado
 *    desde el Service, que sí está cubierto por las pruebas de esta clase.
 *
 * 3) La detección de "qué paso se acaba de completar" para decidir si lanzar
 *    el confeti y abrir el drawer solo (variable "huboAvance", que compara
 *    contra el snapshot guardado en sessionStorage bajo la clave
 *    "onboarding_ids_completados") es estado del navegador entre peticiones:
 *    no existe sessionStorage en un test de PHPUnit, y simularlo a mano no
 *    verificaría el bug real (una condición de carrera entre dos pintadas
 *    del drawer en la misma sesión de navegador).
 *
 * 4) Transiciones visuales (fade del overlay de bienvenida, animación de
 *    entrada del drawer, disparo de confeti) son puramente cosméticas y no
 *    tienen equivalente en una prueba de backend.
 *
 * Cobertura recomendada para 1-3 si se quiere automatizar: un test end-to-end
 * con navegador real (Dusk / Playwright), fuera del alcance de PHPUnit.
 */
