<?php

namespace Tests\Feature;

use App\Http\Controllers\Request\ReservaController;
use App\Http\Middleware\VerificarSesion;
use App\Service\SvcComision;
use Carbon\Carbon;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Una reserva con la comisión ya pagada (id_pago_comision no nulo) es parte
 * de una liquidación confirmada: no se puede eliminar ni cambiar de
 * servicio, empleado, fecha u hora (ni de estado, ver ReservaHistorialEstadoTest).
 * Notas y cliente sí se editan. Camino real: HTTP con withSession() sobre
 * SQLite en memoria.
 *
 * Reloj congelado: miércoles 2026-06-10 a las 12:00. La reserva pagada es
 * del viernes 2026-06-05 (ya pasada y completada, como queda tras liquidar).
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * M1 — Sin el chequeo de id_pago_comision en SvcReserva::eliminar(): 18 tests,
 *      15 passed, 3 FAILED — eliminar una pagada (estado quedó en 0), la de
 *      integridad y la del log. Restaurado: 18 passed.
 * M2 — Sin el chequeo en SvcReserva::editar(): 18 tests, 11 passed, 7 FAILED —
 *      los 4 campos fijos (servicio, empleado, fecha, hora), quitar el
 *      empleado, la de integridad y la del log. Restaurado: 18 passed.
 * M3a — eliminar() sin transacción (DB::transaction( -> call_user_func():
 *       mismo cuerpo, sin transacción): 18 tests, 16 passed, 2 FAILED — SOLO
 *       las dos pruebas de refuerzo (eventos de transacción y guardián). Las
 *       de comportamiento no lo detectaban: por eso se agregaron.
 * M3b — editar() sin lockForUpdate(): 18 tests, 17 passed, 1 FAILED — solo
 *       el guardián estructural. SQLite compila lockForUpdate() a nada, así
 *       que ninguna prueba de comportamiento puede verlo.
 * M4a — eliminar() sin ->where('tenant_id') en la lectura bloqueada: 18
 *       tests, 17 passed, 1 FAILED — la de aislamiento (la respuesta para la
 *       reserva de B dejó de ser idéntica a la de un id inexistente).
 *       Restaurado: 18 passed.
 * M4b — editar() sin ->where('tenant_id') en la lectura bloqueada: mismo
 *       resultado, falla la de aislamiento. Restaurado: 18 passed.
 */
class ReservaComisionPagadaTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    private int $adminA;

    private int $clienteAna;

    private int $clienteBeto;

    private int $masaje;

    private int $facial;

    private int $laura;

    private int $sofia;

    /** Reserva completada del 2026-06-05 10:00, de Laura, con su comisión ya pagada. */
    private int $pagada;

    /** Reserva igual pero SIN pagar, para comprobar que el flujo normal no cambió. */
    private int $noPagada;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = $this->crearNegocio('Spa A');
        $this->negocioB = $this->crearNegocio('Spa B');

        $this->adminA = DB::table('usuarios')->insertGetId([
            'tenant_id' => $this->negocioA, 'id_rol' => 1, 'usuario' => 'admin.a', 'nombre' => 'Admin A',
            'email' => 'admin@a.test', 'clave' => bcrypt('ClaveSegura2026'),
            'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01 08:00:00', 'estado' => 1,
        ]);

        $this->clienteAna = $this->crearCliente($this->negocioA, 'Ana');
        $this->clienteBeto = $this->crearCliente($this->negocioA, 'Beto');
        $this->masaje = $this->crearRecurso($this->negocioA, 'Masaje', 60, 50000);
        $this->facial = $this->crearRecurso($this->negocioA, 'Facial', 30, 80000);
        $this->laura = $this->crearEmpleado($this->negocioA, 'Laura');
        $this->sofia = $this->crearEmpleado($this->negocioA, 'Sofia');

        $this->pagada = $this->crearReserva($this->negocioA, $this->clienteAna, $this->masaje, $this->laura, '2026-06-05', '10:00:00', '11:00:00');
        $this->noPagada = $this->crearReserva($this->negocioA, $this->clienteAna, $this->masaje, $this->laura, '2026-06-05', '14:00:00', '15:00:00');

        // La liquidación por el camino real, y luego se "despaga" la de control:
        // así la pagada lo está exactamente como la deja Comisiones.
        $this->assertNotFalse((new SvcComision)->marcarPeriodoPagado($this->negocioA, $this->laura, '2026-06-01', '2026-06-07', 'Admin A'));
        DB::table('reservas')->where('id_reserva', $this->noPagada)->update(['id_pago_comision' => null]);
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
            'nombre_negocio' => $nombre, 'rubro' => 'spa', 'dias_atencion' => '1,2,3,4,5,6,7',
            'hora_apertura' => '06:00:00', 'hora_cierre' => '22:00:00',
            'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01 08:00:00', 'estado' => 1,
        ]);
    }

    private function crearCliente(int $tenantId, string $nombre): int
    {
        return DB::table('clientes')->insertGetId([
            'tenant_id' => $tenantId, 'nombre' => $nombre, 'telefono' => '3000000000',
            'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01 08:00:00', 'estado' => 1,
        ]);
    }

    private function crearRecurso(int $tenantId, string $nombre, int $duracion, int $precio): int
    {
        return DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $tenantId, 'nombre' => $nombre, 'duracion_minutos' => $duracion, 'precio' => $precio,
            'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01 08:00:00', 'estado' => 1,
        ]);
    }

    private function crearEmpleado(int $tenantId, string $nombre): int
    {
        return DB::table('empleados')->insertGetId([
            'tenant_id' => $tenantId, 'nombre' => $nombre, 'telefono' => '3100000000', 'porcentaje_comision' => 10,
            'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01 08:00:00', 'estado' => 1,
        ]);
    }

    private function crearReserva(int $tenantId, int $cliente, int $recurso, ?int $empleado, string $fecha, string $inicio, string $fin): int
    {
        return DB::table('reservas')->insertGetId([
            'tenant_id' => $tenantId, 'id_cliente' => $cliente, 'id_recurso' => $recurso, 'id_empleado' => $empleado,
            'fecha_reserva' => $fecha, 'hora_inicio' => $inicio, 'hora_fin' => $fin,
            'estado_reserva' => 'completada', 'origen' => 'backoffice',
            'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01 08:00:00', 'estado' => 1,
        ]);
    }

    private function sesion(int $idRol = 1, ?int $tenantId = null): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => $this->adminA,
            'usuario' => 'admin.a',
            'nombre_usuario' => 'Admin A',
            'tenant_id' => $idRol === 3 ? null : ($tenantId ?? $this->negocioA),
            'id_rol' => $idRol,
        ];
    }

    /** Lo que el modal manda al guardar: los cuatro campos iguales a los guardados. */
    private function datosEdicion(int $idReserva, array $cambios = []): array
    {
        return array_merge([
            'id_reserva' => $idReserva,
            'id_cliente' => $this->clienteAna,
            'id_recurso' => $this->masaje,
            'id_empleado' => $this->laura,
            'fecha_reserva' => '2026-06-05',
            'hora_inicio' => '10:00',
            'notas' => null,
        ], $cambios);
    }

    private function editar(array $datos, ?array $sesion = null)
    {
        return $this->withSession($sesion ?? $this->sesion())->postJson('request/reserva/editar', $datos);
    }

    private function eliminar(int $idReserva, ?array $sesion = null)
    {
        return $this->withSession($sesion ?? $this->sesion())->postJson('request/reserva/eliminar', ['id_reserva' => $idReserva]);
    }

    private function fila(int $idReserva): array
    {
        return (array) DB::table('reservas')->where('id_reserva', $idReserva)->first();
    }

    /* ================= ELIMINAR ================= */

    public function test_eliminar_una_reserva_pagada_se_rechaza_y_sigue_activa(): void
    {
        $this->eliminar($this->pagada)
            ->assertJsonPath('error', 1)
            ->assertJsonPath('mensaje', ReservaController::MENSAJE_ELIMINAR_PAGADA);

        $this->assertSame(1, (int) $this->fila($this->pagada)['estado']);
    }

    public function test_eliminar_una_reserva_no_pagada_funciona_como_antes(): void
    {
        $this->eliminar($this->noPagada)->assertJsonPath('error', 0);

        $this->assertSame(0, (int) $this->fila($this->noPagada)['estado']);
    }

    /* ================= EDITAR: LOS CUATRO CAMPOS FIJOS ================= */

    public static function camposFijos(): array
    {
        return [
            'servicio' => ['id_recurso'],
            'empleado' => ['id_empleado'],
            'fecha' => ['fecha_reserva'],
            'hora' => ['hora_inicio'],
        ];
    }

    #[DataProvider('camposFijos')]
    public function test_editar_cambiando_un_campo_fijo_de_una_reserva_pagada_se_rechaza(string $campo): void
    {
        $nuevo = [
            'id_recurso' => $this->facial,
            'id_empleado' => $this->sofia,
            // Futura, para que no la frene la regla de "fecha pasada" y llegue
            // de verdad al chequeo de comisión.
            'fecha_reserva' => '2026-06-12',
            'hora_inicio' => '11:00',
        ][$campo];

        $antes = $this->fila($this->pagada);

        $this->editar($this->datosEdicion($this->pagada, [$campo => $nuevo, 'notas' => 'intento']))
            ->assertJsonPath('error', 1)
            ->assertJsonPath('mensaje', ReservaController::MENSAJE_EDITAR_PAGADA);

        $this->assertSame($antes, $this->fila($this->pagada), 'La reserva pagada no debe cambiar en nada, ni las notas');
    }

    public function test_quitar_el_empleado_de_una_reserva_pagada_tambien_cuenta_como_cambio(): void
    {
        $this->editar($this->datosEdicion($this->pagada, ['id_empleado' => '']))
            ->assertJsonPath('error', 1);

        $this->assertSame($this->laura, (int) $this->fila($this->pagada)['id_empleado']);
    }

    /* ================= EDITAR: LO QUE SÍ SE PUEDE ================= */

    public function test_en_una_reserva_pagada_y_pasada_se_pueden_editar_notas_y_cliente(): void
    {
        $this->editar($this->datosEdicion($this->pagada, ['id_cliente' => $this->clienteBeto, 'notas' => 'Corregido el cliente']))
            ->assertJsonPath('error', 0);

        $fila = $this->fila($this->pagada);
        $this->assertSame($this->clienteBeto, (int) $fila['id_cliente']);
        $this->assertSame('Corregido el cliente', $fila['notas']);
        $this->assertSame($this->masaje, (int) $fila['id_recurso']);
        $this->assertSame($this->laura, (int) $fila['id_empleado']);
        $this->assertSame('2026-06-05', $fila['fecha_reserva']);
        $this->assertSame('10:00:00', $fila['hora_inicio']);
    }

    /** Es exactamente lo que hace el modal: manda los cuatro campos iguales más una nota. */
    public function test_los_cuatro_campos_iguales_mas_una_nota_nueva_funciona(): void
    {
        $this->editar($this->datosEdicion($this->pagada, ['notas' => 'Solo una nota']))
            ->assertJsonPath('error', 0);

        $this->assertSame('Solo una nota', $this->fila($this->pagada)['notas']);
    }

    /**
     * Normalización: guardada como "10:00" (SQLite guarda el texto tal cual) y
     * enviada como "10:00:00", o al revés, no es un cambio real.
     */
    public function test_la_hora_en_otro_formato_no_cuenta_como_cambio(): void
    {
        $this->editar($this->datosEdicion($this->pagada, ['hora_inicio' => '10:00:00', 'notas' => 'formato largo']))
            ->assertJsonPath('error', 0);

        DB::table('reservas')->where('id_reserva', $this->pagada)->update(['hora_inicio' => '10:00']);

        $this->editar($this->datosEdicion($this->pagada, ['hora_inicio' => '10:00', 'notas' => 'formato corto']))
            ->assertJsonPath('error', 0);

        $this->assertSame('formato corto', $this->fila($this->pagada)['notas']);
    }

    public function test_editar_una_no_pagada_cambiando_los_cuatro_campos_funciona_como_antes(): void
    {
        $this->editar($this->datosEdicion($this->noPagada, [
            'id_recurso' => $this->facial,
            'id_empleado' => $this->sofia,
            'fecha_reserva' => '2026-06-12',
            'hora_inicio' => '16:00',
        ]))->assertJsonPath('error', 0);

        $fila = $this->fila($this->noPagada);
        $this->assertSame($this->facial, (int) $fila['id_recurso']);
        $this->assertSame($this->sofia, (int) $fila['id_empleado']);
        $this->assertSame('2026-06-12', $fila['fecha_reserva']);
        $this->assertSame('16:00:00', $fila['hora_inicio']);
        $this->assertSame('16:30:00', $fila['hora_fin'], 'hora_fin sigue saliendo de la duración del servicio');
    }

    /* ================= DATO comision_pagada (sin exponer el pago) ================= */

    public function test_el_modal_y_el_panel_reciben_solo_el_booleano_comision_pagada(): void
    {
        $obtener = $this->withSession($this->sesion())
            ->getJson('request/reserva/obtener?id_reserva='.$this->pagada)
            ->assertJsonPath('data.reserva.comision_pagada', true);
        $this->withSession($this->sesion())
            ->getJson('request/reserva/obtener?id_reserva='.$this->noPagada)
            ->assertJsonPath('data.reserva.comision_pagada', false);

        $agenda = $this->withSession($this->sesion())->getJson('request/reserva/listar?fecha_inicio=2026-06-05&fecha_fin=2026-06-05');
        $calendario = $this->withSession($this->sesion())->getJson('request/reserva/listar-calendario?fecha_inicio=2026-06-05&fecha_fin=2026-06-05');

        $porId = collect($agenda->json('data.reservas'))->keyBy('id_reserva');
        $this->assertTrue($porId[$this->pagada]['comision_pagada']);
        $this->assertFalse($porId[$this->noPagada]['comision_pagada']);

        $eventos = collect($calendario->json('data.eventos'))->keyBy('id');
        $this->assertTrue($eventos[(string) $this->pagada]['extendedProps']['comision_pagada']);
        $this->assertFalse($eventos[(string) $this->noPagada]['extendedProps']['comision_pagada']);

        foreach ([$obtener, $agenda, $calendario] as $respuesta) {
            $this->assertStringNotContainsString('id_pago_comision', $respuesta->getContent());
            $this->assertStringNotContainsString('monto', $respuesta->getContent());
        }
    }

    /* ================= LOG DE INTENTOS RECHAZADOS ================= */

    public function test_cada_intento_rechazado_queda_en_el_log_con_quien_reserva_y_que(): void
    {
        $canal = Mockery::spy();
        Log::shouldReceive('channel')->with('database')->andReturn($canal);

        $this->eliminar($this->pagada);
        $this->editar($this->datosEdicion($this->pagada, ['id_recurso' => $this->facial, 'id_empleado' => $this->sofia]));
        $this->withSession($this->sesion())
            ->postJson('request/reserva/cambiar-estado', ['id_reserva' => $this->pagada, 'estado_reserva' => 'cancelada']);

        foreach (['eliminar', 'editar id_recurso, id_empleado', 'cambiar el estado a cancelada'] as $intento) {
            $canal->shouldHaveReceived('info')->withArgs(function ($mensaje) use ($intento) {
                return is_string($mensaje)
                    && str_contains($mensaje, 'intento rechazado de '.$intento.' sobre la reserva '.$this->pagada)
                    && str_contains($mensaje, 'negocio '.$this->negocioA)
                    && str_contains($mensaje, 'usuario '.$this->adminA);
            })->once();
        }
    }

    /* ================= ROLES ================= */

    public function test_empleado_y_super_admin_siguen_rechazados(): void
    {
        $antes = $this->fila($this->noPagada);

        foreach ([2, 3] as $rol) {
            $this->eliminar($this->noPagada, $this->sesion($rol))->assertJsonPath('error', 1);
            $this->editar($this->datosEdicion($this->noPagada, ['notas' => 'x']), $this->sesion($rol))->assertJsonPath('error', 1);
        }

        $this->assertSame($antes, $this->fila($this->noPagada));
    }

    /* ================= AISLAMIENTO ENTRE NEGOCIOS ================= */

    /**
     * Un admin de A no puede eliminar ni editar una reserva pagada (ni una sin
     * pagar) de B, y la respuesta es idéntica a la de un id que no existe: no
     * revela que la reserva existe, ni que está pagada.
     */
    public function test_un_admin_de_A_no_puede_tocar_reservas_de_B_ni_saber_si_existen(): void
    {
        $clienteB = $this->crearCliente($this->negocioB, 'Cliente B');
        $recursoB = $this->crearRecurso($this->negocioB, 'Servicio B', 60, 70000);
        $empleadaB = $this->crearEmpleado($this->negocioB, 'Empleada B');
        $pagadaB = $this->crearReserva($this->negocioB, $clienteB, $recursoB, $empleadaB, '2026-06-05', '10:00:00', '11:00:00');
        $noPagadaB = $this->crearReserva($this->negocioB, $clienteB, $recursoB, $empleadaB, '2026-06-05', '12:00:00', '13:00:00');
        (new SvcComision)->marcarPeriodoPagado($this->negocioB, $empleadaB, '2026-06-01', '2026-06-07', 'Admin B');
        DB::table('reservas')->where('id_reserva', $noPagadaB)->update(['id_pago_comision' => null]);

        $antesPagada = $this->fila($pagadaB);
        $antesNoPagada = $this->fila($noPagadaB);

        // Con datos del propio negocio A y una fecha futura, para que pase todas
        // las validaciones del controller y llegue de verdad al Service.
        $edicion = fn (int $id) => $this->datosEdicion($id, ['notas' => 'x', 'fecha_reserva' => '2026-06-12', 'tenant_id' => $this->negocioB]);

        $inexistenteEliminar = $this->eliminar(999999)->getContent();
        $inexistenteEditar = $this->editar($edicion(999999))->getContent();

        foreach ([$pagadaB, $noPagadaB] as $idB) {
            // tenant_id en el cuerpo: se ignora, manda la sesión.
            $this->assertSame($inexistenteEliminar, $this->withSession($this->sesion())
                ->postJson('request/reserva/eliminar', ['id_reserva' => $idB, 'tenant_id' => $this->negocioB])->getContent());

            $this->assertSame($inexistenteEditar, $this->editar($edicion($idB))->getContent());
        }

        $this->assertStringNotContainsString('comisión', $inexistenteEliminar.$inexistenteEditar);
        $this->assertSame($antesPagada, $this->fila($pagadaB));
        $this->assertSame($antesNoPagada, $this->fila($noPagadaB));
    }

    /* ================= INTEGRIDAD DE LA LIQUIDACIÓN ================= */

    /**
     * Se liquida por el camino real y se intenta alterar la cita por los tres
     * caminos (cambiar estado, eliminar, editar). Los datos que definieron la
     * comisión siguen siendo los de la liquidación, el informe del periodo no
     * la vuelve a contar y el pago guardado no cambia.
     */
    public function test_tras_intentar_los_tres_caminos_la_cita_sigue_como_se_liquido(): void
    {
        $pago = DB::table('pagos_comisiones')->where('tenant_id', $this->negocioA)->first();
        $antes = $this->fila($this->pagada);

        $this->withSession($this->sesion())
            ->postJson('request/reserva/cambiar-estado', ['id_reserva' => $this->pagada, 'estado_reserva' => 'cancelada'])
            ->assertJsonPath('error', 1)
            ->assertJsonPath('mensaje', ReservaController::MENSAJE_ESTADO_PAGADA);
        $this->eliminar($this->pagada)->assertJsonPath('error', 1);
        $this->editar($this->datosEdicion($this->pagada, [
            'id_recurso' => $this->facial, 'id_empleado' => $this->sofia,
            'fecha_reserva' => '2026-06-12', 'hora_inicio' => '11:00',
        ]))->assertJsonPath('error', 1);

        $despues = $this->fila($this->pagada);
        foreach (['id_recurso', 'id_empleado', 'fecha_reserva', 'hora_inicio', 'hora_fin', 'estado_reserva', 'estado', 'id_pago_comision'] as $campo) {
            $this->assertSame($antes[$campo], $despues[$campo], "Cambió $campo de una cita ya liquidada");
        }

        $this->assertEquals($pago, DB::table('pagos_comisiones')->where('id_pago_comision', $pago->id_pago_comision)->first());

        // El informe del periodo solo trae lo pendiente: la de control (no pagada), nunca la pagada.
        $informe = (new SvcComision)->generarInforme($this->negocioA, '2026-06-01', '2026-06-07', $this->laura);
        $this->assertSame(1, $informe[0]['servicios'][0]['cantidad_citas']);
    }

    /* ================= TRANSACCIÓN Y BLOQUEO DE FILA ================= */

    /**
     * La lectura de la reserva (con su id_pago_comision) y la escritura van en
     * la MISMA transacción: si no, un pago marcado entre las dos podría colarse.
     * Se observa con los eventos de la conexión: la consulta a reservas tiene
     * que ocurrir después de abrir la transacción y antes de confirmarla.
     */
    public function test_eliminar_y_editar_leen_y_escriben_dentro_de_una_transaccion(): void
    {
        foreach ([
            'editar' => fn () => $this->editar($this->datosEdicion($this->noPagada, ['hora_inicio' => '14:00', 'notas' => 'tx'])),
            'eliminar' => fn () => $this->eliminar($this->noPagada),
        ] as $operacion => $llamar) {
            $eventos = [];
            Event::listen(TransactionBeginning::class, function () use (&$eventos) {
                $eventos[] = 'inicio';
            });
            Event::listen(TransactionCommitted::class, function () use (&$eventos) {
                $eventos[] = 'commit';
            });
            DB::listen(function (QueryExecuted $q) use (&$eventos) {
                if (preg_match('/^(select .* from "reservas"|update "reservas")/i', $q->sql) && ! str_contains($q->sql, 'join')) {
                    $eventos[] = str_starts_with(strtolower($q->sql), 'update') ? 'update' : 'select';
                }
            });

            $llamar()->assertJsonPath('error', 0);

            $inicio = array_search('inicio', $eventos, true);
            $update = array_search('update', $eventos, true);
            $commit = array_search('commit', $eventos, true);

            $this->assertNotFalse($inicio, "$operacion: no abrió una transacción");
            $this->assertNotFalse($update, "$operacion: no escribió la reserva");
            $selectDentro = array_filter(array_slice($eventos, $inicio, $update - $inicio, true), fn ($e) => $e === 'select');
            $this->assertNotEmpty($selectDentro, "$operacion: la reserva no se leyó dentro de la transacción");
            $this->assertNotFalse($commit, "$operacion: la transacción no se confirmó");
            $this->assertGreaterThan($update, $commit, "$operacion: la escritura quedó fuera de la transacción");

            Event::forget(TransactionBeginning::class);
            Event::forget(TransactionCommitted::class);
            DB::flushQueryLog();
        }
    }

    /**
     * SQLite (la base de las pruebas) no tiene bloqueo de fila: lockForUpdate()
     * se compila a nada, así que ninguna prueba de comportamiento puede ver si
     * falta. Guardián estructural: la lectura de eliminar() y editar() (como
     * la de cambiarEstado()) tiene que pedir el bloqueo.
     */
    public function test_eliminar_editar_y_cambiar_estado_bloquean_la_fila_que_leen(): void
    {
        $codigo = File::get(app_path('Service/SvcReserva.php'));

        foreach (['eliminar', 'editar', 'cambiarEstado'] as $metodo) {
            $inicio = strpos($codigo, 'public function '.$metodo.'(');
            $this->assertNotFalse($inicio, "No se encontró SvcReserva::$metodo()");
            $cuerpo = substr($codigo, $inicio, strpos($codigo, "\n    }\n", $inicio) - $inicio);

            $this->assertStringContainsString('DB::transaction(', $cuerpo, "$metodo() debe correr en una transacción");
            $this->assertStringContainsString('->lockForUpdate()', $cuerpo, "$metodo() debe bloquear la fila que lee");
            $this->assertStringContainsString('id_pago_comision', $cuerpo, "$metodo() debe revisar id_pago_comision");
        }
    }
}
