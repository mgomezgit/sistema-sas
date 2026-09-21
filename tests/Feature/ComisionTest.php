<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Módulo de Comisiones: cálculo, liquidación y aislamiento multi-tenant.
 *
 * Todo se ejerce por los endpoints reales con withSession() (la autenticación
 * de este proyecto usa sesión propia, no el Auth de Laravel), sin mocks de
 * capas intermedias: si la protección real se rompe, la prueba se rompe.
 *
 * El precio de cada cita sale de recursos_reservables.precio, la misma fuente
 * que usa el reporte de ingresos por servicio.
 *
 * ================= PRUEBA DE MUTACIÓN DEL TENANT_ID =================
 *
 * test_aislamiento_multi_tenant_en_el_informe() se verificó rompiendo el
 * código a propósito. Procedimiento ejecutado:
 *
 *   1. En app/Service/SvcComision.php, dentro de generarInforme(), se comentó
 *      la línea del filtro por negocio:
 *          ->where('r.tenant_id', $tenantId)
 *   2. Se ejecutó: php artisan test --filter=ComisionTest
 *      Resultado: 16 tests, 14 passed, 2 FAILED. Las dos que fallaron son
 *      justamente las que custodian el aislamiento:
 *        - test_aislamiento_multi_tenant_en_el_informe:
 *          "El informe del negocio A no debe incluir empleados del negocio B
 *           Failed asserting that an array does not contain 'Empleado Del B'."
 *        - test_no_se_puede_marcar_pagado_un_empleado_de_otro_negocio:
 *          "Failed asserting that 0 is identical to 1." (el endpoint respondió
 *          error=0, es decir: el negocio A SÍ consiguió liquidar al empleado
 *          del negocio B).
 *   3. Se restauró la línea tal cual estaba.
 *   4. Se volvió a ejecutar: php artisan test --filter=ComisionTest
 *      Resultado: 16 passed, 74 assertions.
 *
 * Es decir: la prueba no pasa "por casualidad" — falla exactamente cuando el
 * WHERE de tenant_id desaparece, que es lo que debe custodiar.
 */
class ComisionTest extends TestCase
{
    use RefreshDatabase;

    /** Negocio A: sobre el que se hacen casi todas las pruebas. */
    private int $negocioA;

    /** Negocio B: el que nunca debe aparecer en los informes del A. */
    private int $negocioB;

    private int $idClienteA;

    /** Empleado del negocio A con 10% de comisión general. */
    private int $idEmpleadoA;

    private int $idMasajeA;

    private int $idFacialA;

    private int $idEmpleadoB;

    private int $idServicioB;

    private int $idClienteB;

    /** Rango de trabajo de casi todas las pruebas. */
    private string $fechaInicio = '2026-01-01';

    private string $fechaFin = '2026-01-31';

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

        $this->idClienteA = $this->crearCliente($this->negocioA, 'Cliente A');
        $this->idEmpleadoA = $this->crearEmpleado($this->negocioA, 'Empleado Del A', 10);
        // Precios redondos para que las comisiones se verifiquen a simple vista.
        $this->idMasajeA = $this->crearRecurso($this->negocioA, 'Masaje', 100000);
        $this->idFacialA = $this->crearRecurso($this->negocioA, 'Facial', 50000);

        $this->idClienteB = $this->crearCliente($this->negocioB, 'Cliente B');
        $this->idEmpleadoB = $this->crearEmpleado($this->negocioB, 'Empleado Del B', 50);
        $this->idServicioB = $this->crearRecurso($this->negocioB, 'Servicio Del B', 200000);
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

    private function crearCliente(int $tenantId, string $nombre): int
    {
        return DB::table('clientes')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'telefono' => '3000000000',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearEmpleado(int $tenantId, string $nombre, ?float $porcentajeComision): int
    {
        return DB::table('empleados')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'telefono' => '3000000000',
            'porcentaje_comision' => $porcentajeComision,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearRecurso(int $tenantId, string $nombre, float $precio): int
    {
        return DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'duracion_minutos' => 60,
            'precio' => $precio,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    /** Inserta una cita saltándose las validaciones del endpoint de reservas. */
    private function crearReserva(int $tenantId, int $idCliente, int $idRecurso, ?int $idEmpleado, array $sobreescribir = []): int
    {
        return DB::table('reservas')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'id_cliente' => $idCliente,
            'id_recurso' => $idRecurso,
            'id_empleado' => $idEmpleado,
            'fecha_reserva' => '2026-01-10',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado_reserva' => 'completada',
            'id_pago_comision' => null,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ], $sobreescribir));
    }

    private function crearTarifaEspecifica(int $tenantId, int $idEmpleado, int $idRecurso, float $porcentaje): int
    {
        return DB::table('comisiones_tarifas')->insertGetId([
            'tenant_id' => $tenantId,
            'id_empleado' => $idEmpleado,
            'id_recurso' => $idRecurso,
            'porcentaje_comision' => $porcentaje,
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

    /** GET del informe con el rango por defecto. */
    private function informeComo(array $sesion, array $parametros = [])
    {
        $query = array_merge([
            'fecha_inicio' => $this->fechaInicio,
            'fecha_fin' => $this->fechaFin,
        ], $parametros);

        return $this->withSession($sesion)->getJson('request/comisiones/informe?'.http_build_query($query));
    }

    /* ================= 1) AISLAMIENTO MULTI-TENANT ================= */

    /**
     * Ver la nota de MUTACIÓN en el encabezado de la clase: esta es la prueba
     * que se rompió a propósito para comprobar que realmente custodia el
     * filtro por tenant_id.
     */
    public function test_aislamiento_multi_tenant_en_el_informe(): void
    {
        // Cada negocio tiene una cita completada en el mismo rango.
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA);
        $this->crearReserva($this->negocioB, $this->idClienteB, $this->idServicioB, $this->idEmpleadoB);

        $respuesta = $this->informeComo($this->sesionAdmin($this->negocioA));

        $respuesta->assertJsonPath('error', 0);

        $comisiones = $respuesta->json('data.comisiones');
        $nombres = array_column($comisiones, 'nombre_empleado');

        $this->assertContains('Empleado Del A', $nombres);
        $this->assertNotContains(
            'Empleado Del B',
            $nombres,
            'El informe del negocio A no debe incluir empleados del negocio B'
        );
        $this->assertCount(1, $comisiones);

        // Y el dinero del otro negocio tampoco se cuela en el total.
        // assertEquals y no assertSame: al serializar la respuesta, un float
        // redondo como 10000.0 viaja en el JSON como 10000 y vuelve como int.
        $this->assertEquals(10000, $comisiones[0]['total_comision']);
    }

    /**
     * El negocio A no puede liquidar (ni tocar) las citas de un empleado del
     * negocio B, aunque mande su id explícitamente.
     */
    public function test_no_se_puede_marcar_pagado_un_empleado_de_otro_negocio(): void
    {
        $idReservaB = $this->crearReserva($this->negocioB, $this->idClienteB, $this->idServicioB, $this->idEmpleadoB);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/comisiones/marcar-pagado', [
                'id_empleado' => $this->idEmpleadoB,
                'fecha_inicio' => $this->fechaInicio,
                'fecha_fin' => $this->fechaFin,
            ]);

        $respuesta->assertJsonPath('error', 1);

        // No se creó ningún pago...
        $this->assertSame(0, DB::table('pagos_comisiones')->count());
        // ...y la cita del negocio B sigue intacta, sin marcar.
        $this->assertNull(DB::table('reservas')->where('id_reserva', $idReservaB)->value('id_pago_comision'));
    }

    /* ================= 2) RESOLUCIÓN DEL PORCENTAJE ================= */

    public function test_usa_el_porcentaje_general_del_empleado_cuando_no_hay_tarifa_especifica(): void
    {
        // Masaje de 100.000 con el 10% general del empleado = 10.000.
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA);

        $comisiones = $this->informeComo($this->sesionAdmin($this->negocioA))->json('data.comisiones');

        $this->assertCount(1, $comisiones[0]['servicios']);
        $this->assertEquals(10, $comisiones[0]['servicios'][0]['porcentaje_aplicado']);
        $this->assertEquals(10000, $comisiones[0]['servicios'][0]['monto_comision']);
        $this->assertEquals(10000, $comisiones[0]['total_comision']);
    }

    public function test_la_tarifa_especifica_tiene_prioridad_sobre_el_porcentaje_del_empleado(): void
    {
        // Para el Masaje se pacta 25% en vez del 10% general.
        $this->crearTarifaEspecifica($this->negocioA, $this->idEmpleadoA, $this->idMasajeA, 25);

        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA);
        // El Facial no tiene tarifa propia: debe caer al 10% general.
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idFacialA, $this->idEmpleadoA);

        $comisiones = $this->informeComo($this->sesionAdmin($this->negocioA))->json('data.comisiones');
        $servicios = collect($comisiones[0]['servicios'])->keyBy('nombre_servicio');

        // Masaje: 100.000 * 25% = 25.000 (tarifa específica).
        $this->assertEquals(25, $servicios['Masaje']['porcentaje_aplicado']);
        $this->assertEquals(25000, $servicios['Masaje']['monto_comision']);

        // Facial: 50.000 * 10% = 5.000 (porcentaje general).
        $this->assertEquals(10, $servicios['Facial']['porcentaje_aplicado']);
        $this->assertEquals(5000, $servicios['Facial']['monto_comision']);

        $this->assertEquals(30000, $comisiones[0]['total_comision']);
    }

    /**
     * Una tarifa específica del negocio B no puede aplicarse sobre las citas
     * del negocio A aunque coincidieran los ids.
     */
    public function test_una_tarifa_dada_de_baja_deja_de_aplicarse(): void
    {
        $idTarifa = $this->crearTarifaEspecifica($this->negocioA, $this->idEmpleadoA, $this->idMasajeA, 25);
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA);

        // Con la tarifa activa manda el 25%.
        $conTarifa = $this->informeComo($this->sesionAdmin($this->negocioA))->json('data.comisiones');
        $this->assertEquals(25000, $conTarifa[0]['total_comision']);

        $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/comisiones/tarifas/eliminar', ['id_comision_tarifa' => $idTarifa])
            ->assertJsonPath('error', 0);

        // Dada de baja, vuelve a mandar el 10% general del empleado.
        $sinTarifa = $this->informeComo($this->sesionAdmin($this->negocioA))->json('data.comisiones');
        $this->assertEquals(10000, $sinTarifa[0]['total_comision']);
    }

    /* ================= 3) SOLO CUENTAN LAS CITAS COMPLETADAS ================= */

    public function test_solo_las_reservas_completadas_generan_comision(): void
    {
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA, [
            'estado_reserva' => 'completada',
        ]);
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA, [
            'estado_reserva' => 'confirmada',
        ]);
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA, [
            'estado_reserva' => 'cancelada',
        ]);
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA, [
            'estado_reserva' => 'pendiente',
        ]);

        $comisiones = $this->informeComo($this->sesionAdmin($this->negocioA))->json('data.comisiones');

        // Solo la completada: 1 cita, 100.000 * 10% = 10.000.
        $this->assertSame(1, $comisiones[0]['servicios'][0]['cantidad_citas']);
        $this->assertEquals(10000, $comisiones[0]['total_comision']);
    }

    public function test_las_reservas_fuera_del_rango_de_fechas_no_entran(): void
    {
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA, [
            'fecha_reserva' => '2026-01-10',
        ]);
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA, [
            'fecha_reserva' => '2026-02-10',
        ]);

        $comisiones = $this->informeComo($this->sesionAdmin($this->negocioA))->json('data.comisiones');

        $this->assertSame(1, $comisiones[0]['servicios'][0]['cantidad_citas']);
        $this->assertEquals(10000, $comisiones[0]['total_comision']);
    }

    /* ================= 4) LIQUIDACIÓN Y NO DOBLE PAGO ================= */

    public function test_marcar_pagado_marca_las_citas_y_no_vuelven_a_aparecer_en_un_rango_solapado(): void
    {
        $idReserva = $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/comisiones/marcar-pagado', [
                'id_empleado' => $this->idEmpleadoA,
                'fecha_inicio' => $this->fechaInicio,
                'fecha_fin' => $this->fechaFin,
            ]);

        $respuesta->assertJsonPath('error', 0);
        $idPago = $respuesta->json('data.id_pago_comision');

        // La cita quedó vinculada al pago.
        $this->assertSame($idPago, DB::table('reservas')->where('id_reserva', $idReserva)->value('id_pago_comision'));

        // El pago guardó el monto calculado en el servidor.
        $this->assertEquals(10000, DB::table('pagos_comisiones')->where('id_pago_comision', $idPago)->value('monto_total'));

        // Un informe posterior con un rango MÁS AMPLIO que se solapa no vuelve
        // a cobrar la misma cita.
        $posterior = $this->informeComo($this->sesionAdmin($this->negocioA), [
            'fecha_inicio' => '2025-12-01',
            'fecha_fin' => '2026-03-31',
        ]);

        $this->assertSame([], $posterior->json('data.comisiones'));
    }

    /**
     * El monto es autoridad del servidor: si el formulario mandara un total
     * inflado, debe ignorarse por completo.
     */
    public function test_el_monto_total_enviado_desde_el_request_se_ignora(): void
    {
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/comisiones/marcar-pagado', [
                'id_empleado' => $this->idEmpleadoA,
                'fecha_inicio' => $this->fechaInicio,
                'fecha_fin' => $this->fechaFin,
                // Intento de manipulación desde el cliente.
                'monto_total' => 99999999,
            ]);

        $respuesta->assertJsonPath('error', 0);

        // Se guardó el cálculo real (10.000), no lo que mandó el request.
        $this->assertEquals(
            10000,
            DB::table('pagos_comisiones')->where('id_pago_comision', $respuesta->json('data.id_pago_comision'))->value('monto_total')
        );
    }

    public function test_marcar_pagado_sin_comisiones_pendientes_no_crea_un_pago_vacio(): void
    {
        // Existe el empleado, pero no tiene ninguna cita completada en el rango.
        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/comisiones/marcar-pagado', [
                'id_empleado' => $this->idEmpleadoA,
                'fecha_inicio' => $this->fechaInicio,
                'fecha_fin' => $this->fechaFin,
            ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertSame(0, DB::table('pagos_comisiones')->count());
    }

    public function test_el_historial_de_pagos_solo_muestra_los_del_propio_negocio(): void
    {
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA);
        $this->crearReserva($this->negocioB, $this->idClienteB, $this->idServicioB, $this->idEmpleadoB);

        // Cada negocio liquida lo suyo.
        $this->withSession($this->sesionAdmin($this->negocioA))->postJson('request/comisiones/marcar-pagado', [
            'id_empleado' => $this->idEmpleadoA,
            'fecha_inicio' => $this->fechaInicio,
            'fecha_fin' => $this->fechaFin,
        ])->assertJsonPath('error', 0);

        $this->withSession($this->sesionAdmin($this->negocioB))->postJson('request/comisiones/marcar-pagado', [
            'id_empleado' => $this->idEmpleadoB,
            'fecha_inicio' => $this->fechaInicio,
            'fecha_fin' => $this->fechaFin,
        ])->assertJsonPath('error', 0);

        $this->assertSame(2, DB::table('pagos_comisiones')->count());

        $historialA = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/comisiones/historial-pagos')
            ->json('data.pagos');

        $this->assertCount(1, $historialA);
        $this->assertSame('Empleado Del A', $historialA[0]['nombre_empleado']);
    }

    /* ================= 5) TARIFAS: AISLAMIENTO Y CRUD ================= */

    public function test_las_tarifas_de_un_negocio_no_se_ven_ni_se_borran_desde_otro(): void
    {
        $idTarifaB = $this->crearTarifaEspecifica($this->negocioB, $this->idEmpleadoB, $this->idServicioB, 40);
        $this->crearTarifaEspecifica($this->negocioA, $this->idEmpleadoA, $this->idMasajeA, 25);

        $tarifasA = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/comisiones/tarifas')
            ->json('data.tarifas');

        $this->assertCount(1, $tarifasA);
        $this->assertSame('Empleado Del A', $tarifasA[0]['nombre_empleado']);

        // Intentar borrar la tarifa ajena falla y la deja intacta.
        $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/comisiones/tarifas/eliminar', ['id_comision_tarifa' => $idTarifaB])
            ->assertJsonPath('error', 1);

        $this->assertSame(1, DB::table('comisiones_tarifas')->where('id_comision_tarifa', $idTarifaB)->value('estado'));
    }

    /**
     * El listado esconde las tarifas dadas de baja salvo que se pidan.
     *
     * Es lo que sostiene el filtro "Mostrar inactivas" de la pestaña: sin él,
     * dar de baja una tarifa se sentiría como borrarla, porque no habría forma
     * de volver a encontrarla para reactivarla.
     */
    public function test_listar_tarifas_no_muestra_inactivas_por_defecto_pero_si_al_pedirlas(): void
    {
        $sesion = $this->sesionAdmin($this->negocioA);

        $idActiva = $this->crearTarifaEspecifica($this->negocioA, $this->idEmpleadoA, $this->idMasajeA, 25);
        $idInactiva = $this->crearTarifaEspecifica($this->negocioA, $this->idEmpleadoA, $this->idFacialA, 30);

        $this->withSession($sesion)
            ->postJson('request/comisiones/tarifas/eliminar', ['id_comision_tarifa' => $idInactiva])
            ->assertJsonPath('error', 0);

        $porDefecto = $this->withSession($sesion)
            ->getJson('request/comisiones/tarifas')
            ->json('data.tarifas');

        $this->assertCount(1, $porDefecto);
        $this->assertSame($idActiva, $porDefecto[0]['id_comision_tarifa']);

        $conInactivas = $this->withSession($sesion)
            ->getJson('request/comisiones/tarifas?incluir_inactivas=1')
            ->json('data.tarifas');

        $this->assertCount(2, $conInactivas);

        $estados = collect($conInactivas)->pluck('estado', 'id_comision_tarifa');
        $this->assertSame(1, (int) $estados[$idActiva]);
        $this->assertSame(0, (int) $estados[$idInactiva]);
    }

    /**
     * Reactivar una tarifa dada de baja es guardar con estado = 1.
     *
     * Además de volver al listado, tiene que volver a mandar sobre el
     * porcentaje general del empleado: si el informe siguiera calculando con
     * el 10% general, la reactivación sería solo cosmética.
     */
    public function test_guardar_con_estado_1_reactiva_una_tarifa_dada_de_baja(): void
    {
        $sesion = $this->sesionAdmin($this->negocioA);

        $idTarifa = $this->crearTarifaEspecifica($this->negocioA, $this->idEmpleadoA, $this->idMasajeA, 25);
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA);

        $this->withSession($sesion)
            ->postJson('request/comisiones/tarifas/eliminar', ['id_comision_tarifa' => $idTarifa])
            ->assertJsonPath('error', 0);

        // Dada de baja manda el 10% general: 100.000 * 10% = 10.000.
        $sinTarifa = $this->informeComo($sesion)->json('data.comisiones');
        $this->assertEquals(10000, $sinTarifa[0]['total_comision']);

        $this->withSession($sesion)->postJson('request/comisiones/tarifas/guardar', [
            'id_empleado' => $this->idEmpleadoA,
            'id_recurso' => $this->idMasajeA,
            'porcentaje_comision' => 25,
            'estado' => 1,
        ])->assertJsonPath('error', 0);

        $this->assertSame(1, (int) DB::table('comisiones_tarifas')->where('id_comision_tarifa', $idTarifa)->value('estado'));
        // Y no se creó una segunda tarifa para el mismo par empleado+servicio.
        $this->assertSame(1, DB::table('comisiones_tarifas')->count());

        $reactivada = $this->withSession($sesion)
            ->getJson('request/comisiones/tarifas')
            ->json('data.tarifas');
        $this->assertCount(1, $reactivada);

        // Y vuelve a mandar sobre el general: 100.000 * 25% = 25.000.
        $conTarifa = $this->informeComo($sesion)->json('data.comisiones');
        $this->assertEquals(25000, $conTarifa[0]['total_comision']);
    }

    /**
     * El interruptor del modal también da de baja, no solo la papelera.
     */
    public function test_guardar_con_estado_0_da_de_baja_la_tarifa(): void
    {
        $sesion = $this->sesionAdmin($this->negocioA);

        $idTarifa = $this->crearTarifaEspecifica($this->negocioA, $this->idEmpleadoA, $this->idMasajeA, 25);

        $this->withSession($sesion)->postJson('request/comisiones/tarifas/guardar', [
            'id_empleado' => $this->idEmpleadoA,
            'id_recurso' => $this->idMasajeA,
            'porcentaje_comision' => 25,
            'estado' => 0,
        ])->assertJsonPath('error', 0);

        $this->assertSame(0, (int) DB::table('comisiones_tarifas')->where('id_comision_tarifa', $idTarifa)->value('estado'));

        $this->assertCount(0, $this->withSession($sesion)
            ->getJson('request/comisiones/tarifas')
            ->json('data.tarifas'));
    }

    /**
     * Un alta no manda estado y tiene que nacer activa.
     */
    public function test_una_tarifa_nueva_nace_activa_sin_mandar_estado(): void
    {
        $sesion = $this->sesionAdmin($this->negocioA);

        $this->withSession($sesion)->postJson('request/comisiones/tarifas/guardar', [
            'id_empleado' => $this->idEmpleadoA,
            'id_recurso' => $this->idMasajeA,
            'porcentaje_comision' => 25,
        ])->assertJsonPath('error', 0);

        $this->assertSame(1, (int) DB::table('comisiones_tarifas')->value('estado'));
    }

    public function test_guardar_tarifa_con_un_estado_invalido_es_rechazado(): void
    {
        $sesion = $this->sesionAdmin($this->negocioA);

        $idTarifa = $this->crearTarifaEspecifica($this->negocioA, $this->idEmpleadoA, $this->idMasajeA, 25);

        $this->withSession($sesion)->postJson('request/comisiones/tarifas/guardar', [
            'id_empleado' => $this->idEmpleadoA,
            'id_recurso' => $this->idMasajeA,
            'porcentaje_comision' => 25,
            'estado' => 7,
        ])->assertJsonPath('error', 1);

        $this->assertSame(1, (int) DB::table('comisiones_tarifas')->where('id_comision_tarifa', $idTarifa)->value('estado'));
    }

    /**
     * El filtro de inactivas no puede convertirse en una rendija para ver las
     * tarifas de otro negocio: sigue acotado al tenant de la sesión.
     */
    public function test_listar_con_inactivas_no_mezcla_negocios(): void
    {
        $idInactivaB = $this->crearTarifaEspecifica($this->negocioB, $this->idEmpleadoB, $this->idServicioB, 40);
        DB::table('comisiones_tarifas')->where('id_comision_tarifa', $idInactivaB)->update(['estado' => 0]);

        $idInactivaA = $this->crearTarifaEspecifica($this->negocioA, $this->idEmpleadoA, $this->idMasajeA, 25);
        DB::table('comisiones_tarifas')->where('id_comision_tarifa', $idInactivaA)->update(['estado' => 0]);

        $tarifasA = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/comisiones/tarifas?incluir_inactivas=1')
            ->json('data.tarifas');

        $this->assertCount(1, $tarifasA);
        $this->assertSame($idInactivaA, $tarifasA[0]['id_comision_tarifa']);
    }

    /**
     * Reactivar no puede servir para resucitar la tarifa de otro negocio.
     *
     * El tenant sale de la sesión, así que guardar "la tarifa de B" desde A
     * crearía una tarifa propia de A, nunca tocaría la de B.
     */
    public function test_no_se_puede_reactivar_la_tarifa_de_otro_negocio(): void
    {
        $idTarifaB = $this->crearTarifaEspecifica($this->negocioB, $this->idEmpleadoB, $this->idServicioB, 40);
        DB::table('comisiones_tarifas')->where('id_comision_tarifa', $idTarifaB)->update(['estado' => 0]);

        $this->withSession($this->sesionAdmin($this->negocioA))->postJson('request/comisiones/tarifas/guardar', [
            'id_empleado' => $this->idEmpleadoB,
            'id_recurso' => $this->idServicioB,
            'porcentaje_comision' => 40,
            'estado' => 1,
            // Aunque se intente forzar el negocio por el cuerpo.
            'tenant_id' => $this->negocioB,
        ]);

        $this->assertSame(0, (int) DB::table('comisiones_tarifas')->where('id_comision_tarifa', $idTarifaB)->value('estado'));
    }

    public function test_guardar_tarifa_actualiza_la_existente_en_vez_de_duplicarla(): void
    {
        $sesion = $this->sesionAdmin($this->negocioA);

        $this->withSession($sesion)->postJson('request/comisiones/tarifas/guardar', [
            'id_empleado' => $this->idEmpleadoA,
            'id_recurso' => $this->idMasajeA,
            'porcentaje_comision' => 25,
        ])->assertJsonPath('error', 0);

        $this->withSession($sesion)->postJson('request/comisiones/tarifas/guardar', [
            'id_empleado' => $this->idEmpleadoA,
            'id_recurso' => $this->idMasajeA,
            'porcentaje_comision' => 30,
        ])->assertJsonPath('error', 0);

        $this->assertSame(1, DB::table('comisiones_tarifas')->count());
        $this->assertEquals(30, DB::table('comisiones_tarifas')->value('porcentaje_comision'));
        // Y quedó asignada al negocio de la sesión.
        $this->assertSame($this->negocioA, (int) DB::table('comisiones_tarifas')->value('tenant_id'));
    }

    /* ================= 6) VALIDACIONES ================= */

    public function test_rechaza_un_rango_con_fecha_fin_anterior_a_fecha_inicio(): void
    {
        $respuesta = $this->informeComo($this->sesionAdmin($this->negocioA), [
            'fecha_inicio' => '2026-01-31',
            'fecha_fin' => '2026-01-01',
        ]);

        $respuesta->assertJsonPath('error', 1);

        $marcar = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/comisiones/marcar-pagado', [
                'id_empleado' => $this->idEmpleadoA,
                'fecha_inicio' => '2026-01-31',
                'fecha_fin' => '2026-01-01',
            ]);

        $marcar->assertJsonPath('error', 1);
        $this->assertSame(0, DB::table('pagos_comisiones')->count());
    }

    /* ================= 7) PERMISOS ================= */

    /**
     * El empleado no puede ver comisiones de nadie, ni siquiera las suyas: se
     * verifican TODOS los endpoints y también la vista de backoffice.
     */
    public function test_el_rol_empleado_queda_bloqueado_en_endpoints_y_en_la_vista(): void
    {
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA);

        $sesion = $this->sesionEmpleado($this->negocioA);

        $gets = [
            'request/comisiones/informe?fecha_inicio='.$this->fechaInicio.'&fecha_fin='.$this->fechaFin,
            'request/comisiones/tarifas',
            'request/comisiones/historial-pagos',
        ];

        foreach ($gets as $endpoint) {
            $respuesta = $this->withSession($sesion)->getJson($endpoint);
            $respuesta->assertJsonPath('error', 1);
            $this->assertStringContainsString('No tienes permiso', $respuesta->json('mensaje'));
        }

        $posts = [
            'request/comisiones/marcar-pagado' => [
                'id_empleado' => $this->idEmpleadoA,
                'fecha_inicio' => $this->fechaInicio,
                'fecha_fin' => $this->fechaFin,
            ],
            'request/comisiones/tarifas/guardar' => [
                'id_empleado' => $this->idEmpleadoA,
                'id_recurso' => $this->idMasajeA,
                'porcentaje_comision' => 50,
            ],
            'request/comisiones/tarifas/eliminar' => ['id_comision_tarifa' => 1],
        ];

        foreach ($posts as $endpoint => $datos) {
            $respuesta = $this->withSession($sesion)->postJson($endpoint, $datos);
            $respuesta->assertJsonPath('error', 1);
            $this->assertStringContainsString('No tienes permiso', $respuesta->json('mensaje'));
        }

        // La vista lo devuelve a su propia agenda.
        $this->withSession($sesion)
            ->get('backoffice/comisiones')
            ->assertRedirect(url('backoffice/mis-citas'));

        // Ninguno de los intentos dejó rastro.
        $this->assertSame(0, DB::table('pagos_comisiones')->count());
        $this->assertSame(0, DB::table('comisiones_tarifas')->count());
    }

    /* ================= 8) RENDER DE LA VISTA ================= */

    /**
     * La vista se arma de verdad para el administrador: se compila el Blade y
     * llegan los datos del Controller Vista (empleados y servicios del tenant)
     * a los selects del filtro y del modal de tarifas.
     */
    public function test_la_vista_de_comisiones_carga_para_el_administrador(): void
    {
        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))->get('backoffice/comisiones');

        $respuesta->assertOk();

        // Las tres pestañas están en el documento.
        $respuesta->assertSee('Informe');
        $respuesta->assertSee('Tarifas específicas');
        $respuesta->assertSee('Historial de pagos');

        // Los datos del tenant llegaron a los selects...
        $respuesta->assertSee('Empleado Del A');
        $respuesta->assertSee('Masaje');
        $respuesta->assertSee('Facial');

        // ...y los del otro negocio no.
        $respuesta->assertDontSee('Empleado Del B');
        $respuesta->assertDontSee('Servicio Del B');
    }

    /**
     * El super admin pasa el middleware (no es empleado) pero no pertenece a
     * ningún negocio: generar informes operativos no es su función.
     */
    public function test_el_super_admin_no_puede_generar_informes_ni_liquidar(): void
    {
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idMasajeA, $this->idEmpleadoA);

        $sesion = $this->sesionSuperAdmin();

        $informe = $this->informeComo($sesion);
        $informe->assertJsonPath('error', 1);
        $this->assertStringContainsString('cuenta de cada negocio', $informe->json('mensaje'));

        $marcar = $this->withSession($sesion)->postJson('request/comisiones/marcar-pagado', [
            'id_empleado' => $this->idEmpleadoA,
            'fecha_inicio' => $this->fechaInicio,
            'fecha_fin' => $this->fechaFin,
        ]);
        $marcar->assertJsonPath('error', 1);
        $this->assertStringContainsString('cuenta de cada negocio', $marcar->json('mensaje'));

        $tarifas = $this->withSession($sesion)->getJson('request/comisiones/tarifas');
        $tarifas->assertJsonPath('error', 1);

        $historial = $this->withSession($sesion)->getJson('request/comisiones/historial-pagos');
        $historial->assertJsonPath('error', 1);

        // La vista lo manda al dashboard (nunca llega a renderizar el blade,
        // que todavía no existe: se construye en un prompt aparte).
        $this->withSession($sesion)
            ->get('backoffice/comisiones')
            ->assertRedirect(url('backoffice/dashboard'));

        $this->assertSame(0, DB::table('pagos_comisiones')->count());
    }
}
