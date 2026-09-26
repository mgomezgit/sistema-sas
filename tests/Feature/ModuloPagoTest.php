<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Service\SvcModulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Módulos de pago por negocio: activación, bloqueo real y aislamiento.
 *
 * Todo se ejerce por los endpoints y vistas reales con withSession() (la
 * autenticación de este proyecto usa sesión propia, no el Auth de Laravel).
 * El bloqueo se comprueba por los DOS caminos —la vista de backoffice y los
 * endpoints de request— porque cerrar solo la pantalla no es cerrar nada:
 * quien conozca la URL del endpoint seguiría entrando.
 *
 * ================= PRUEBAS DE MUTACIÓN DEL TENANT_ID =================
 *
 * El aislamiento se verificó rompiendo el código a propósito, en los DOS
 * puntos donde el filtro por negocio decide el acceso. Ambas mutaciones se
 * ejecutaron de verdad; estos son sus resultados exactos.
 *
 * MUTACIÓN 1 — el WHERE de la escritura.
 *   1. En app/Service/SvcModulo.php, dentro de desactivarModulo(), se quitó
 *      la línea del filtro por negocio:
 *          ->where('tenant_id', $tenantId)
 *      dejando el update acotado únicamente por id_modulo.
 *   2. php artisan test --filter=ModuloPagoTest
 *      Resultado: 18 tests, 17 passed, 1 FAILED — la que custodia el caso:
 *        - test_desactivar_un_modulo_no_afecta_a_otro_negocio:
 *          "Desactivar el modulo del negocio A no puede apagarlo en el B
 *           Failed asserting that false is true."
 *   3. Se restauró la línea tal cual estaba.
 *
 * MUTACIÓN 2 — el WHERE de la lectura, que es la que gobierna el bloqueo.
 *   1. En estaActivo(), se quitó:
 *          ->where('nm.tenant_id', $tenantId)
 *   2. php artisan test --filter=ModuloPagoTest
 *      Resultado: 18 tests, 13 passed, 5 FAILED:
 *        - test_un_negocio_nuevo_nace_con_comisiones_inactivo
 *        - test_activar_un_modulo_no_lo_activa_en_otro_negocio
 *        - test_desactivar_un_modulo_no_afecta_a_otro_negocio
 *        - test_aislamiento_en_estaActivo_entre_negocios
 *        - test_el_bloqueo_no_se_contagia_entre_negocios:
 *          "Expected response status code [302] but received 200."
 *      Ese último es el que más importa: con el filtro roto, el negocio que NO
 *      tiene el módulo entró a la pantalla por HTTP. El aislamiento queda
 *      custodiado de punta a punta, no solo a nivel de Service.
 *   3. Se restauró la línea tal cual estaba.
 *
 * Tras restaurar ambas: 18 passed, 63 assertions.
 *
 * Es decir: las pruebas no pasan "por casualidad" — fallan exactamente cuando
 * el WHERE de tenant_id desaparece, que es lo que deben custodiar.
 */
class ModuloPagoTest extends TestCase
{
    use RefreshDatabase;

    /** Ruta de la migración de datos puntual, que algunas pruebas reejecutan. */
    const MIGRACION_BACKFILL = 'migrations/2026_09_25_100200_activar_comisiones_en_negocios_existentes.php';

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

    private function svc(): SvcModulo
    {
        return app(SvcModulo::class);
    }

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

    private function crearEmpleado(int $tenantId, string $nombre): int
    {
        return DB::table('empleados')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'telefono' => '3000000000',
            'porcentaje_comision' => 10,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearRecurso(int $tenantId, string $nombre): int
    {
        return DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'duracion_minutos' => 60,
            'precio' => 100000,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
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

    /** Ejecuta la migración de datos puntual tal cual está en disco. */
    private function correrMigracionBackfill(): void
    {
        $migracion = require database_path(self::MIGRACION_BACKFILL);

        $migracion->up();
    }

    /* ================= 1) CATÁLOGO Y DATOS MIGRADOS ================= */

    public function test_el_catalogo_trae_comisiones_desde_la_migracion(): void
    {
        $modulo = DB::table('modulos_plataforma')->where('clave', 'comisiones')->first();

        $this->assertNotNull($modulo, 'La migración debe dejar sembrado el módulo comisiones');
        $this->assertSame('Cálculo de comisiones', $modulo->nombre);
        $this->assertSame(1, (int) $modulo->estado);
    }

    /**
     * Los negocios que ya existían cuando se introdujo el control de módulos
     * conservan el acceso que tenían. Se ejerce la migración de datos real, no
     * una reimplementación de su lógica.
     */
    public function test_los_negocios_existentes_quedan_con_comisiones_activo(): void
    {
        $this->correrMigracionBackfill();

        $this->assertTrue(
            $this->svc()->estaActivo($this->negocioA, 'comisiones'),
            'Un negocio preexistente debe conservar Comisiones activo tras la migración'
        );
        $this->assertTrue($this->svc()->estaActivo($this->negocioB, 'comisiones'));

        $fila = DB::table('negocio_modulos')->where('tenant_id', $this->negocioA)->first();
        $this->assertNotNull($fila->fecha_activacion, 'La migración debe sellar la fecha de activación');
    }

    /**
     * La migración de datos es puntual: lo que preserva es el pasado, no fija
     * el comportamiento futuro.
     */
    public function test_un_negocio_nuevo_nace_con_comisiones_inactivo(): void
    {
        $this->correrMigracionBackfill();

        // Se crea DESPUÉS de la migración, igual que un negocio que se registre
        // mañana en la plataforma.
        $negocioNuevo = $this->crearNegocio('Negocio Recien Creado');

        $this->assertFalse(
            $this->svc()->estaActivo($negocioNuevo, 'comisiones'),
            'Un negocio creado después de la migración no estrena el módulo'
        );
    }

    public function test_esta_activo_es_false_cuando_no_existe_la_fila(): void
    {
        $this->assertSame(0, DB::table('negocio_modulos')->where('tenant_id', $this->negocioA)->count());

        $this->assertFalse(
            $this->svc()->estaActivo($this->negocioA, 'comisiones'),
            'Sin fila en negocio_modulos el default es negar, nunca conceder'
        );
    }

    public function test_esta_activo_es_false_para_una_clave_de_modulo_inexistente(): void
    {
        $this->svc()->activarModulo($this->negocioA, 'comisiones', 'test');

        $this->assertFalse($this->svc()->estaActivo($this->negocioA, 'modulo-que-no-existe'));
    }

    /* ================= 2) ACTIVAR Y DESACTIVAR ================= */

    public function test_activar_crea_la_fila_y_sella_la_fecha(): void
    {
        $this->assertTrue($this->svc()->activarModulo($this->negocioA, 'comisiones', 'superadmin.test'));

        $fila = DB::table('negocio_modulos')->where('tenant_id', $this->negocioA)->first();

        $this->assertSame(1, (int) $fila->activo);
        $this->assertNotNull($fila->fecha_activacion);
        $this->assertSame('superadmin.test', $fila->usuario_registra);
        $this->assertTrue($this->svc()->estaActivo($this->negocioA, 'comisiones'));
    }

    public function test_desactivar_apaga_el_modulo_y_sella_la_fecha(): void
    {
        $this->svc()->activarModulo($this->negocioA, 'comisiones', 'test');

        $this->assertTrue($this->svc()->desactivarModulo($this->negocioA, 'comisiones'));

        $fila = DB::table('negocio_modulos')->where('tenant_id', $this->negocioA)->first();

        $this->assertSame(0, (int) $fila->activo);
        $this->assertNotNull($fila->fecha_desactivacion);
        $this->assertFalse($this->svc()->estaActivo($this->negocioA, 'comisiones'));
    }

    /**
     * Alternar el módulo no debe acumular filas: el índice único lo impide, y
     * el estado vigente tiene que ser inequívoco.
     */
    public function test_activar_y_desactivar_reusan_la_misma_fila(): void
    {
        $this->svc()->activarModulo($this->negocioA, 'comisiones', 'test');
        $this->svc()->desactivarModulo($this->negocioA, 'comisiones');
        $this->svc()->activarModulo($this->negocioA, 'comisiones', 'test');

        $this->assertSame(1, DB::table('negocio_modulos')->where('tenant_id', $this->negocioA)->count());
        $this->assertTrue($this->svc()->estaActivo($this->negocioA, 'comisiones'));
    }

    /**
     * Cerrar el acceso no es borrar: si el negocio vuelve a contratar el módulo
     * tiene que encontrar sus tarifas y su historial donde los dejó.
     */
    public function test_desactivar_no_toca_las_tarifas_ni_los_pagos_historicos(): void
    {
        $this->svc()->activarModulo($this->negocioA, 'comisiones', 'test');

        $idEmpleado = $this->crearEmpleado($this->negocioA, 'Empleada A');
        $idRecurso = $this->crearRecurso($this->negocioA, 'Masaje');

        $idTarifa = DB::table('comisiones_tarifas')->insertGetId([
            'tenant_id' => $this->negocioA,
            'id_empleado' => $idEmpleado,
            'id_recurso' => $idRecurso,
            'porcentaje_comision' => 25,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $idPago = DB::table('pagos_comisiones')->insertGetId([
            'tenant_id' => $this->negocioA,
            'id_empleado' => $idEmpleado,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-01-31',
            'monto_total' => 250000,
            'fecha_pago' => date('Y-m-d H:i:s'),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $this->svc()->desactivarModulo($this->negocioA, 'comisiones');

        $tarifa = DB::table('comisiones_tarifas')->where('id_comision_tarifa', $idTarifa)->first();
        $pago = DB::table('pagos_comisiones')->where('id_pago_comision', $idPago)->first();

        $this->assertNotNull($tarifa, 'Desactivar el módulo no puede borrar las tarifas');
        // Comparación numérica: el decimal vuelve como '25' en SQLite y como
        // '25.00' en MySQL, y lo que importa es el valor, no el formato.
        $this->assertEquals(25, (float) $tarifa->porcentaje_comision);
        $this->assertSame(1, (int) $tarifa->estado);
        $this->assertNotNull($pago, 'Desactivar el módulo no puede borrar el historial de pagos');
        $this->assertEquals(250000, (float) $pago->monto_total);

        // Y al reactivarlo, sigue todo en su sitio.
        $this->svc()->activarModulo($this->negocioA, 'comisiones', 'test');

        $this->assertSame(1, DB::table('comisiones_tarifas')->where('tenant_id', $this->negocioA)->count());
        $this->assertSame(1, DB::table('pagos_comisiones')->where('tenant_id', $this->negocioA)->count());
    }

    /* ================= 3) AISLAMIENTO MULTI-TENANT ================= */

    public function test_activar_un_modulo_no_lo_activa_en_otro_negocio(): void
    {
        $this->svc()->activarModulo($this->negocioA, 'comisiones', 'test');

        $this->assertTrue($this->svc()->estaActivo($this->negocioA, 'comisiones'));
        $this->assertFalse(
            $this->svc()->estaActivo($this->negocioB, 'comisiones'),
            'Activar el módulo del negocio A no puede encenderlo en el B'
        );
        $this->assertSame(0, DB::table('negocio_modulos')->where('tenant_id', $this->negocioB)->count());
    }

    /**
     * Ver la nota de MUTACIÓN en el encabezado de la clase: esta es una de las
     * dos pruebas que se rompieron a propósito para comprobar que realmente
     * custodian el filtro por tenant_id.
     */
    public function test_desactivar_un_modulo_no_afecta_a_otro_negocio(): void
    {
        $this->svc()->activarModulo($this->negocioA, 'comisiones', 'test');
        $this->svc()->activarModulo($this->negocioB, 'comisiones', 'test');

        $this->svc()->desactivarModulo($this->negocioA, 'comisiones');

        $this->assertFalse($this->svc()->estaActivo($this->negocioA, 'comisiones'));
        $this->assertTrue(
            $this->svc()->estaActivo($this->negocioB, 'comisiones'),
            'Desactivar el modulo del negocio A no puede apagarlo en el B'
        );
    }

    /** Ver la nota de MUTACIÓN en el encabezado de la clase. */
    public function test_aislamiento_en_estaActivo_entre_negocios(): void
    {
        $this->svc()->activarModulo($this->negocioB, 'comisiones', 'test');

        $this->assertFalse(
            $this->svc()->estaActivo($this->negocioA, 'comisiones'),
            'El módulo activo del negocio B no puede dar acceso al negocio A'
        );
        $this->assertTrue($this->svc()->estaActivo($this->negocioB, 'comisiones'));
    }

    /* ================= 4) BLOQUEO REAL POR HTTP ================= */

    /**
     * Los dos caminos, en la misma prueba: si el módulo está inactivo, ni la
     * pantalla ni los endpoints pueden responder.
     */
    public function test_modulo_inactivo_bloquea_la_vista_y_los_endpoints(): void
    {
        $sesion = $this->sesionAdmin($this->negocioA);

        // Vista: rebota fuera de la sección.
        $this->withSession($sesion)
            ->get('backoffice/comisiones')
            ->assertStatus(302)
            ->assertRedirect(url('backoffice/dashboard'));

        // Endpoints: mismo formato de respuesta de siempre, con error.
        $rutas = [
            ['get', 'request/comisiones/informe?fecha_inicio=2026-01-01&fecha_fin=2026-01-31'],
            ['get', 'request/comisiones/tarifas'],
            ['get', 'request/comisiones/historial-pagos'],
        ];

        foreach ($rutas as [$metodo, $url]) {
            $respuesta = $this->withSession($sesion)->getJson($url);

            $respuesta->assertStatus(200);
            $this->assertSame(1, $respuesta->json('error'), "El endpoint $url debe quedar bloqueado");
            $this->assertStringContainsString('no está activo', $respuesta->json('mensaje'));
        }

        $posts = [
            ['request/comisiones/marcar-pagado', ['id_empleado' => 1, 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-01-31']],
            ['request/comisiones/tarifas/guardar', ['id_empleado' => 1, 'id_recurso' => 1, 'porcentaje_comision' => 10]],
            ['request/comisiones/tarifas/eliminar', ['id_comision_tarifa' => 1]],
        ];

        foreach ($posts as [$url, $cuerpo]) {
            $respuesta = $this->withSession($sesion)->postJson($url, $cuerpo);

            $this->assertSame(1, $respuesta->json('error'), "El endpoint $url debe quedar bloqueado");
        }
    }

    public function test_modulo_activo_deja_pasar_la_vista_y_los_endpoints(): void
    {
        $this->svc()->activarModulo($this->negocioA, 'comisiones', 'test');

        $sesion = $this->sesionAdmin($this->negocioA);

        $this->withSession($sesion)->get('backoffice/comisiones')->assertStatus(200);

        $respuesta = $this->withSession($sesion)
            ->getJson('request/comisiones/informe?fecha_inicio=2026-01-01&fecha_fin=2026-01-31');

        $this->assertSame(0, $respuesta->json('error'));
    }

    /**
     * Desactivar el módulo de un negocio no puede cerrarle la puerta a otro que
     * sí lo tiene: el bloqueo se resuelve por tenant, no globalmente.
     */
    public function test_el_bloqueo_no_se_contagia_entre_negocios(): void
    {
        $this->svc()->activarModulo($this->negocioB, 'comisiones', 'test');

        // A no lo tiene: bloqueado.
        $this->withSession($this->sesionAdmin($this->negocioA))
            ->get('backoffice/comisiones')
            ->assertStatus(302);

        // B sí: entra.
        $this->withSession($this->sesionAdmin($this->negocioB))
            ->get('backoffice/comisiones')
            ->assertStatus(200);
    }

    /**
     * El super admin no pertenece a ningún negocio, así que no hay módulo que
     * comprobarle: el middleware no puede dejarlo fuera.
     */
    public function test_el_super_admin_nunca_queda_bloqueado_por_el_modulo(): void
    {
        $sesion = $this->sesionSuperAdmin();

        // El super admin tampoco gestiona comisiones de un negocio concreto: el
        // propio ComisionViewController lo devuelve al dashboard, y eso ya se
        // prueba en ComisionTest. Como ese controlador redirige al MISMO destino
        // que el middleware, el estado HTTP por sí solo no distingue quién lo
        // detuvo. Lo que esta prueba afirma es lo que de verdad importa: que el
        // estado del módulo no cambia NADA para él.
        $sinModulo = $this->withSession($sesion)->get('backoffice/comisiones');

        $this->svc()->activarModulo($this->negocioA, 'comisiones', 'test');
        $this->svc()->activarModulo($this->negocioB, 'comisiones', 'test');

        $conModulo = $this->withSession($sesion)->get('backoffice/comisiones');

        $this->assertSame(
            $sinModulo->getStatusCode(),
            $conModulo->getStatusCode(),
            'Para el super admin, activar o no el módulo no puede cambiar la respuesta'
        );

        // En el endpoint la evidencia es directa: si el middleware lo hubiera
        // bloqueado, el mensaje sería el del módulo inactivo. Llega al
        // controlador, que responde lo suyo.
        $this->svc()->desactivarModulo($this->negocioA, 'comisiones');
        $this->svc()->desactivarModulo($this->negocioB, 'comisiones');

        $respuesta = $this->withSession($sesion)
            ->getJson('request/comisiones/informe?fecha_inicio=2026-01-01&fecha_fin=2026-01-31');

        $this->assertStringNotContainsString(
            'no está activo',
            (string) $respuesta->json('mensaje'),
            'El super admin no puede rebotar en el middleware de módulos'
        );
    }

    /* ================= 5) LISTADOS ================= */

    /**
     * LEFT JOIN: un módulo que el negocio nunca activó debe salir en la lista
     * como inactivo, no desaparecer.
     */
    public function test_listar_modulos_por_negocio_incluye_los_nunca_activados(): void
    {
        $modulos = $this->svc()->listarModulosPorNegocio($this->negocioA);

        $this->assertCount(1, $modulos);
        $this->assertSame('comisiones', $modulos[0]['clave']);
        $this->assertSame(0, (int) $modulos[0]['activo']);

        $this->svc()->activarModulo($this->negocioA, 'comisiones', 'test');

        $modulos = $this->svc()->listarModulosPorNegocio($this->negocioA);
        $this->assertSame(1, (int) $modulos[0]['activo']);
    }

    public function test_listar_negocios_con_modulos_resume_todos_los_negocios(): void
    {
        $this->svc()->activarModulo($this->negocioA, 'comisiones', 'test');

        $resumen = $this->svc()->listarNegociosConModulos();

        // Un negocio por módulo del catálogo: 2 negocios x 1 módulo.
        $this->assertCount(2, $resumen);

        $porNegocio = [];

        foreach ($resumen as $fila) {
            $porNegocio[$fila['id_negocio']] = (int) $fila['activo'];
        }

        $this->assertSame(1, $porNegocio[$this->negocioA]);
        $this->assertSame(0, $porNegocio[$this->negocioB]);
    }
}
