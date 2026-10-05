<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Service\SvcCliente;
use App\Service\SvcReserva;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Métricas del dashboard que antes decidían "hoy" con date() nativo de PHP.
 *
 * date() ignora Carbon::setTestNow(): en producción da igual (ambos leen el
 * mismo reloj real, con el mismo timezone), pero en una prueba que congela el
 * tiempo, un método que use date() se queda mirando el día REAL mientras el
 * resto de la prueba cree que es otro día — fue exactamente lo que rompió
 * XssHttpTest. Se corrigieron contarHoy(), listarProximasHoy(),
 * distribucionHoyPorHora(), calcularIngresosMes() y calcularOcupacionHoy() en
 * SvcReserva; contarActivosMesAnterior() en SvcCliente; y el valor por
 * defecto de "fecha" en ReservaController::misCitas(). Aquí se prueba que
 * ahora SÍ siguen el reloj congelado, con aislamiento de tenant en cada uno
 * (CLAUDE.md exige esto para todo método tocado, aunque no se pida).
 */
class MetricasDashboardTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    private SvcReserva $svcReserva;

    private SvcCliente $svcCliente;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->svcReserva = new SvcReserva;
        $this->svcCliente = new SvcCliente;

        $this->negocioA = $this->crearNegocio('Spa A');
        $this->negocioB = $this->crearNegocio('Spa B');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'rubro' => 'spa',
            'dias_atencion' => '1,2,3,4,5,6,7',
            'hora_apertura' => '08:00:00',
            'hora_cierre' => '18:00:00',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearCliente(int $tenantId, string $nombre, string $telefono = '3000000000'): int
    {
        return DB::table('clientes')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'telefono' => $telefono,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearRecurso(int $tenantId, string $nombre, float $precio = 50000, int $duracion = 60): int
    {
        return DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'duracion_minutos' => $duracion,
            'precio' => $precio,
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
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function insertarReserva(int $tenantId, int $idCliente, int $idRecurso, array $sobreescribir = []): int
    {
        return DB::table('reservas')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'id_cliente' => $idCliente,
            'id_recurso' => $idRecurso,
            'id_empleado' => null,
            'fecha_reserva' => Carbon::today()->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado_reserva' => 'confirmada',
            'origen' => 'admin',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ], $sobreescribir));
    }

    /* ================= 1) contarHoy() / listarProximasHoy() SIGUEN EL RELOJ ================= */

    /**
     * Congela el reloj en un día conocido: una reserva de ESE día aparece, una
     * de "mañana" y una de "ayer" (relativas al mismo día congelado) no.
     */
    public function test_contar_hoy_y_proximas_hoy_siguen_el_reloj_congelado(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10')->setTime(7, 0)); // martes

        $cliente = $this->crearCliente($this->negocioA, 'Cliente De Hoy');
        $recurso = $this->crearRecurso($this->negocioA, 'Masaje');

        $this->insertarReserva($this->negocioA, $cliente, $recurso, ['fecha_reserva' => '2026-11-10']);
        $this->insertarReserva($this->negocioA, $cliente, $recurso, ['fecha_reserva' => '2026-11-11']);
        $this->insertarReserva($this->negocioA, $cliente, $recurso, ['fecha_reserva' => '2026-11-09']);

        $this->assertSame(1, $this->svcReserva->contarHoy($this->negocioA));

        $proximas = $this->svcReserva->listarProximasHoy($this->negocioA);
        $this->assertCount(1, $proximas);
        $this->assertSame('Cliente De Hoy', $proximas[0]['nombre_cliente']);
    }

    public function test_contar_hoy_y_proximas_hoy_nunca_incluyen_reservas_de_otro_negocio(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10')->setTime(7, 0));

        $clienteA = $this->crearCliente($this->negocioA, 'Cliente A');
        $recursoA = $this->crearRecurso($this->negocioA, 'Masaje A');
        $clienteB = $this->crearCliente($this->negocioB, 'Cliente B');
        $recursoB = $this->crearRecurso($this->negocioB, 'Masaje B');

        $this->insertarReserva($this->negocioA, $clienteA, $recursoA);
        $this->insertarReserva($this->negocioB, $clienteB, $recursoB);
        $this->insertarReserva($this->negocioB, $clienteB, $recursoB);

        $this->assertSame(1, $this->svcReserva->contarHoy($this->negocioA));
        $this->assertSame(2, $this->svcReserva->contarHoy($this->negocioB));

        $proximasA = $this->svcReserva->listarProximasHoy($this->negocioA);
        $this->assertCount(1, $proximasA);
        $this->assertNotContains('Cliente B', array_column($proximasA, 'nombre_cliente'));
    }

    /* ================= 2) distribucionHoyPorHora() ================= */

    public function test_distribucion_hoy_por_hora_sigue_el_reloj_congelado_y_aisla_por_negocio(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10')->setTime(7, 0));

        $clienteA = $this->crearCliente($this->negocioA, 'Cliente A');
        $recursoA = $this->crearRecurso($this->negocioA, 'Masaje A');
        $clienteB = $this->crearCliente($this->negocioB, 'Cliente B');
        $recursoB = $this->crearRecurso($this->negocioB, 'Masaje B');

        $this->insertarReserva($this->negocioA, $clienteA, $recursoA, ['hora_inicio' => '10:00:00']);
        // Otro día: no debe sumar a la franja de hoy.
        $this->insertarReserva($this->negocioA, $clienteA, $recursoA, ['fecha_reserva' => '2026-11-11', 'hora_inicio' => '10:00:00']);
        // Otro negocio, misma hora, mismo día: no debe sumarse al de A.
        $this->insertarReserva($this->negocioB, $clienteB, $recursoB, ['hora_inicio' => '10:00:00']);

        $distribucionA = $this->svcReserva->distribucionHoyPorHora($this->negocioA);

        $this->assertSame(1, $distribucionA['10:00']);
        $this->assertSame(1, array_sum($distribucionA), 'Solo debe contar la reserva de hoy, de este negocio');
    }

    /* ================= 3) calcularIngresosMes() ================= */

    public function test_calcular_ingresos_mes_sigue_el_mes_congelado_y_aisla_por_negocio(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10')->setTime(7, 0));

        $clienteA = $this->crearCliente($this->negocioA, 'Cliente A');
        // Precios DISTINTOS a propósito: si el método mirara el mes real en
        // vez del congelado, sumaría una reserva distinta a la esperada, y con
        // el mismo precio en las tres el resultado podría coincidir con el
        // correcto por pura casualidad (pasó: con date() nativo, en una
        // ejecución de octubre esto sumaba igual 100000 confundiendo la
        // reserva de octubre con la de noviembre, ambas al mismo precio, y la
        // prueba no detectaba la mutación). Con precios distintos, cualquier
        // mes equivocado da una suma que no es 100000.
        $recursoEnMes = $this->crearRecurso($this->negocioA, 'Masaje de noviembre', 100000);
        $recursoMesAnterior = $this->crearRecurso($this->negocioA, 'Masaje de octubre', 7000);
        $recursoMesSiguiente = $this->crearRecurso($this->negocioA, 'Masaje de diciembre', 9000);
        $clienteB = $this->crearCliente($this->negocioB, 'Cliente B');
        $recursoB = $this->crearRecurso($this->negocioB, 'Masaje B', 999999);

        // Dentro del mes congelado (noviembre 2026): sí cuenta.
        $this->insertarReserva($this->negocioA, $clienteA, $recursoEnMes, ['fecha_reserva' => '2026-11-05']);
        // Último día de octubre: mes anterior, NO cuenta aunque sea reciente.
        $this->insertarReserva($this->negocioA, $clienteA, $recursoMesAnterior, ['fecha_reserva' => '2026-10-31']);
        // Primer día de diciembre: mes siguiente, tampoco cuenta.
        $this->insertarReserva($this->negocioA, $clienteA, $recursoMesSiguiente, ['fecha_reserva' => '2026-12-01']);
        // Otro negocio, mismo mes: no debe sumarse al de A.
        $this->insertarReserva($this->negocioB, $clienteB, $recursoB, ['fecha_reserva' => '2026-11-05']);

        $this->assertEquals(100000.0, $this->svcReserva->calcularIngresosMes($this->negocioA));
    }

    /* ================= 4) calcularOcupacionHoy(): el fix de date('N') ================= */

    /**
     * El día de la semana "de hoy" decide si hay jornada. Antes se leía con
     * date('N') nativo; ahora con Carbon::now()->dayOfWeekIso, que sí sigue el
     * reloj congelado.
     */
    public function test_calcular_ocupacion_hoy_respeta_el_dia_de_la_semana_congelado(): void
    {
        DB::table('negocios')->where('id_negocio', $this->negocioA)->update(['dias_atencion' => '1,2,3,4,5']);

        $cliente = $this->crearCliente($this->negocioA, 'Cliente A');
        $recurso = $this->crearRecurso($this->negocioA, 'Masaje', 50000, 60);

        // 2026-11-08 es domingo: el negocio no atiende, así que 0 aunque haya
        // una reserva cargada ese día.
        Carbon::setTestNow(Carbon::parse('2026-11-08')->setTime(7, 0));
        $this->insertarReserva($this->negocioA, $cliente, $recurso, ['fecha_reserva' => '2026-11-08']);
        $this->assertSame(0, $this->svcReserva->calcularOcupacionHoy($this->negocioA));

        // 2026-11-09 es lunes: sí atiende. Jornada 08:00-18:00 = 600 min; con
        // 60 min reservados da 10%.
        Carbon::setTestNow(Carbon::parse('2026-11-09')->setTime(7, 0));
        $this->insertarReserva($this->negocioA, $cliente, $recurso, ['fecha_reserva' => '2026-11-09']);
        $this->assertSame(10, $this->svcReserva->calcularOcupacionHoy($this->negocioA));
    }

    public function test_calcular_ocupacion_hoy_nunca_mezcla_minutos_de_otro_negocio(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09')->setTime(7, 0)); // lunes

        $clienteA = $this->crearCliente($this->negocioA, 'Cliente A');
        $recursoA = $this->crearRecurso($this->negocioA, 'Masaje A', 50000, 60);
        $clienteB = $this->crearCliente($this->negocioB, 'Cliente B');
        // Si se mezclara con el negocio B, esta reserva de 600 min llevaría la
        // ocupación de A al 100% en vez del 10% que le corresponde.
        $recursoB = $this->crearRecurso($this->negocioB, 'Masaje B', 50000, 600);

        $this->insertarReserva($this->negocioA, $clienteA, $recursoA, ['fecha_reserva' => '2026-11-09']);
        $this->insertarReserva($this->negocioB, $clienteB, $recursoB, ['fecha_reserva' => '2026-11-09']);

        $this->assertSame(10, $this->svcReserva->calcularOcupacionHoy($this->negocioA));
    }

    /* ================= 5) contarActivosMesAnterior() ================= */

    public function test_contar_activos_mes_anterior_sigue_el_mes_congelado_y_aisla_por_negocio(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10')->setTime(7, 0));

        // Activo, registrado antes de que empezara noviembre: cuenta.
        DB::table('clientes')->insertGetId([
            'tenant_id' => $this->negocioA, 'nombre' => 'Viejo A', 'telefono' => '3001111111',
            'usuario_registra' => 'test', 'fecha_registro' => '2026-10-15 10:00:00', 'estado' => 1,
        ]);
        // Registrado DENTRO de noviembre: todavía no cuenta ("nuevo este mes").
        DB::table('clientes')->insertGetId([
            'tenant_id' => $this->negocioA, 'nombre' => 'Nuevo A', 'telefono' => '3002222222',
            'usuario_registra' => 'test', 'fecha_registro' => '2026-11-05 10:00:00', 'estado' => 1,
        ]);
        // Otro negocio, también de octubre: no debe sumarse al conteo de A.
        DB::table('clientes')->insertGetId([
            'tenant_id' => $this->negocioB, 'nombre' => 'Viejo B', 'telefono' => '3003333333',
            'usuario_registra' => 'test', 'fecha_registro' => '2026-10-15 10:00:00', 'estado' => 1,
        ]);

        $this->assertSame(1, $this->svcCliente->contarActivosMesAnterior($this->negocioA));
    }

    /* ================= 6) ReservaController::misCitas(): el default de "fecha" ================= */

    public function test_mis_citas_sin_fecha_usa_el_dia_congelado_por_defecto(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10')->setTime(7, 0));

        $idEmpleado = $this->crearEmpleado($this->negocioA, 'Empleada Uno');
        $cliente = $this->crearCliente($this->negocioA, 'Cliente De Hoy');
        $recurso = $this->crearRecurso($this->negocioA, 'Masaje');

        $this->insertarReserva($this->negocioA, $cliente, $recurso, [
            'id_empleado' => $idEmpleado, 'fecha_reserva' => '2026-11-10',
        ]);
        $this->insertarReserva($this->negocioA, $cliente, $recurso, [
            'id_empleado' => $idEmpleado, 'fecha_reserva' => '2026-11-11',
        ]);

        $sesion = [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 1,
            'usuario' => 'empleada.uno',
            'nombre_usuario' => 'Empleada Uno',
            'tenant_id' => $this->negocioA,
            'id_rol' => 2,
            'id_empleado' => $idEmpleado,
        ];

        $citas = $this->withSession($sesion)->getJson('request/reserva/mis-citas')->json('data.citas');

        $this->assertCount(1, $citas);
        $this->assertSame('2026-11-10', $citas[0]['fecha_reserva']);
    }

    public function test_mis_citas_nunca_incluye_citas_de_un_empleado_de_otro_negocio(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10')->setTime(7, 0));

        $idEmpleadoA = $this->crearEmpleado($this->negocioA, 'Empleada A');
        $idEmpleadoB = $this->crearEmpleado($this->negocioB, 'Empleada B');

        $clienteA = $this->crearCliente($this->negocioA, 'Cliente A');
        $recursoA = $this->crearRecurso($this->negocioA, 'Masaje A');
        $clienteB = $this->crearCliente($this->negocioB, 'Cliente B');
        $recursoB = $this->crearRecurso($this->negocioB, 'Masaje B');

        $this->insertarReserva($this->negocioA, $clienteA, $recursoA, ['id_empleado' => $idEmpleadoA]);
        $this->insertarReserva($this->negocioB, $clienteB, $recursoB, ['id_empleado' => $idEmpleadoB]);

        $sesion = [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 1,
            'usuario' => 'empleada.a',
            'nombre_usuario' => 'Empleada A',
            'tenant_id' => $this->negocioA,
            'id_rol' => 2,
            'id_empleado' => $idEmpleadoA,
        ];

        $citas = $this->withSession($sesion)->getJson('request/reserva/mis-citas')->json('data.citas');

        $this->assertCount(1, $citas);
        $this->assertNotContains('Cliente B', array_column($citas, 'nombre_cliente'));
    }

    /**
     * REFORZADA: la prueba anterior, por el camino HTTP normal, NO detecta si
     * se quita el filtro ->where('r.tenant_id', $tenantId) de
     * listarPorEmpleado() — lo comprobé mutando el código: la prueba de
     * arriba siguió en verde, porque id_empleado ya es una PK de
     * auto-incremento única en toda la tabla y, en el flujo real (el
     * id_empleado sale de la sesión del propio empleado, nunca del request),
     * basta para no mezclar negocios incluso sin tenant_id.
     *
     * Esta prueba llama al Service directo, pasando a propósito un id_empleado
     * de un negocio junto con el tenant_id de OTRO: esa combinación nunca
     * puede darse en el flujo real, pero es exactamente lo que el filtro de
     * tenant_id existe para frenar como última barrera (defensa en
     * profundidad), por si algún día algo más ya falló antes (una sesión mal
     * armada, un bug en otro punto). Sin el filtro, esto SÍ falla.
     */
    public function test_listar_por_empleado_nunca_devuelve_algo_si_el_tenant_no_coincide_con_el_empleado(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10')->setTime(7, 0));

        $idEmpleadoA = $this->crearEmpleado($this->negocioA, 'Empleada A');
        $clienteA = $this->crearCliente($this->negocioA, 'Cliente A');
        $recursoA = $this->crearRecurso($this->negocioA, 'Masaje A');

        $this->insertarReserva($this->negocioA, $clienteA, $recursoA, ['id_empleado' => $idEmpleadoA]);

        // id_empleado real de negocioA, pero tenant_id de negocioB: combinación
        // imposible por el flujo normal, que el filtro debe rechazar igual.
        $citas = $this->svcReserva->listarPorEmpleado($idEmpleadoA, $this->negocioB, '2026-11-10');

        $this->assertCount(0, $citas, 'listarPorEmpleado no puede devolver una reserva cuyo tenant no coincide con el tenant_id pedido');
    }
}
