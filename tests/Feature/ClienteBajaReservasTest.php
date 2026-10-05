<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Mail\ReservaEstadoActualizado;
use App\Service\SvcReserva;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Baja de un cliente con reservas futuras: el admin elige cancelarlas o
 * conservarlas (cancelar_reservas_futuras = 1 / 0), y sin el parámetro todo
 * sigue como antes. Camino real: HTTP con withSession() sobre SQLite.
 *
 * Reloj congelado: miércoles 2026-06-10 a las 12:00. "Futura" se decide con
 * Carbon::now(), así que ninguna prueba depende del día real en que se corre.
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * MB1 — Sin ->where('tenant_id', $tenantId) en SvcReserva::consultaFuturasDeCliente()
 *       (el filtro de negocio de contar y de la cancelación masiva): 19
 *       tests, 17 passed, 2 FAILED —
 *       test_contar_un_cliente_de_otro_negocio_responde_igual_que_uno_inexistente
 *       ("2 is identical to 0": contó las reservas de B) y
 *       test_la_cancelacion_masiva_nunca_toca_una_reserva_de_otro_negocio
 *       ("3 is identical to 2": metió la reserva de B). Restaurado: 19 passed.
 * MB2 — Sin DB::transaction() en SvcCliente::aplicarBaja() (el cuerpo corre
 *       suelto): 19 tests, 18 passed, 1 FAILED —
 *       test_si_algo_falla_a_mitad_de_camino_no_queda_nada_a_medias ("El
 *       cliente debe seguir activo": quedó inactivo con una reserva ya
 *       cancelada). Restaurado: 19 passed.
 */
class ClienteBajaReservasTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    private int $adminA;

    private int $recursoA;

    private int $recursoB;

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

        $this->recursoA = $this->crearRecurso($this->negocioA);
        $this->recursoB = $this->crearRecurso($this->negocioB);
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

    private function crearRecurso(int $tenantId): int
    {
        return DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $tenantId, 'nombre' => 'Masaje', 'duracion_minutos' => 60, 'precio' => 50000,
            'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01 08:00:00', 'estado' => 1,
        ]);
    }

    private function crearCliente(int $tenantId, array $extra = []): int
    {
        return DB::table('clientes')->insertGetId(array_merge([
            'tenant_id' => $tenantId, 'nombre' => 'Cliente', 'telefono' => '3000000000', 'email' => null,
            'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01 08:00:00', 'estado' => 1,
        ], $extra));
    }

    private function crearReserva(int $tenantId, int $idCliente, string $fecha, string $hora, array $extra = []): int
    {
        $inicio = Carbon::parse($fecha.' '.$hora);

        return DB::table('reservas')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'id_cliente' => $idCliente,
            'id_recurso' => $tenantId === $this->negocioA ? $this->recursoA : $this->recursoB,
            'id_empleado' => null,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $inicio->format('H:i:s'),
            'hora_fin' => $inicio->copy()->addHour()->format('H:i:s'),
            'estado_reserva' => 'pendiente',
            'origen' => 'backoffice',
            'usuario_registra' => 'test',
            'fecha_registro' => '2026-06-01 08:00:00',
            'estado' => 1,
        ], $extra));
    }

    /** Dos futuras (una mañana, una hoy más tarde) y una ya pasada. */
    private function clienteConReservas(int $tenantId, ?string $email = null): array
    {
        $idCliente = $this->crearCliente($tenantId, ['email' => $email]);

        return [
            'cliente' => $idCliente,
            'futura_manana' => $this->crearReserva($tenantId, $idCliente, '2026-06-11', '10:00'),
            'futura_hoy' => $this->crearReserva($tenantId, $idCliente, '2026-06-10', '15:00', ['estado_reserva' => 'confirmada']),
            'pasada' => $this->crearReserva($tenantId, $idCliente, '2026-06-09', '10:00'),
        ];
    }

    private function sesion(int $idRol = 1, ?int $tenantId = null, ?int $idUsuario = null): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => $idUsuario ?? $this->adminA,
            'usuario' => 'admin.a',
            'nombre_usuario' => 'Admin A',
            'tenant_id' => $idRol === 3 ? null : ($tenantId ?? $this->negocioA),
            'id_rol' => $idRol,
        ];
    }

    private function contar(int $idCliente, ?array $sesion = null)
    {
        return $this->withSession($sesion ?? $this->sesion())
            ->getJson('request/cliente/reservas-futuras?id_cliente='.$idCliente);
    }

    private function eliminar(int $idCliente, ?int $cancelar = null)
    {
        $cuerpo = ['id_cliente' => $idCliente];

        if ($cancelar !== null) {
            $cuerpo['cancelar_reservas_futuras'] = $cancelar;
        }

        return $this->withSession($this->sesion())->postJson('request/cliente/eliminar', $cuerpo);
    }

    private function editar(int $idCliente, int $estado, ?int $cancelar = null)
    {
        $cuerpo = ['id_cliente' => $idCliente, 'nombre' => 'Cliente', 'telefono' => '3000000000', 'estado' => $estado];

        if ($cancelar !== null) {
            $cuerpo['cancelar_reservas_futuras'] = $cancelar;
        }

        return $this->withSession($this->sesion())->postJson('request/cliente/editar', $cuerpo);
    }

    private function estadoReserva(int $idReserva): string
    {
        return DB::table('reservas')->where('id_reserva', $idReserva)->value('estado_reserva');
    }

    private function estadoCliente(int $idCliente): int
    {
        return (int) DB::table('clientes')->where('id_cliente', $idCliente)->value('estado');
    }

    /* ================= CONTAR ================= */

    public function test_contar_solo_incluye_pendientes_y_confirmadas_futuras(): void
    {
        $r = $this->clienteConReservas($this->negocioA);
        $c = $r['cliente'];

        // Ninguna de estas cuenta.
        $this->crearReserva($this->negocioA, $c, '2026-06-12', '10:00', ['estado_reserva' => 'cancelada']);
        $this->crearReserva($this->negocioA, $c, '2026-06-12', '11:00', ['estado_reserva' => 'completada']);
        $this->crearReserva($this->negocioA, $c, '2026-06-10', '11:59', []);           // hoy, un minuto antes de "ahora"
        $this->crearReserva($this->negocioA, $c, '2026-06-12', '12:00', ['estado' => 0]); // eliminada

        $this->contar($c)->assertJsonPath('error', 0)->assertJsonPath('data.total', 2);
    }

    public function test_contar_cambia_con_el_reloj(): void
    {
        $r = $this->clienteConReservas($this->negocioA);

        // A las 16:00 la de hoy a las 15:00 ya pasó.
        Carbon::setTestNow(Carbon::parse('2026-06-10 16:00:00'));

        $this->contar($r['cliente'])->assertJsonPath('data.total', 1);
    }

    /* ================= SIN EL PARÁMETRO: COMPORTAMIENTO DE SIEMPRE ================= */

    public function test_baja_sin_parametro_desde_la_papelera_no_toca_las_reservas(): void
    {
        $r = $this->clienteConReservas($this->negocioA);

        $respuesta = $this->eliminar($r['cliente']);

        $respuesta->assertJsonPath('error', 0);
        $this->assertSame([], $respuesta->json('data'));
        $this->assertSame(0, $this->estadoCliente($r['cliente']));
        $this->assertSame('pendiente', $this->estadoReserva($r['futura_manana']));
        $this->assertSame('confirmada', $this->estadoReserva($r['futura_hoy']));
        $this->assertSame(0, DB::table('historial_estados_reserva')->count());
    }

    public function test_baja_sin_parametro_desde_editar_no_toca_las_reservas(): void
    {
        $r = $this->clienteConReservas($this->negocioA);

        $this->editar($r['cliente'], 0)->assertJsonPath('error', 0);

        $this->assertSame(0, $this->estadoCliente($r['cliente']));
        $this->assertSame('pendiente', $this->estadoReserva($r['futura_manana']));
        $this->assertSame(0, DB::table('historial_estados_reserva')->count());
    }

    /* ================= CON EL PARÁMETRO ================= */

    public function test_baja_cancelando_deja_las_futuras_canceladas_con_su_historial_y_las_pasadas_intactas(): void
    {
        $r = $this->clienteConReservas($this->negocioA);

        $respuesta = $this->eliminar($r['cliente'], 1);

        $respuesta->assertJsonPath('error', 0)
            ->assertJsonPath('data.reservas_canceladas', 2)
            ->assertJsonPath('data.reservas_omitidas', 0);

        $this->assertSame(0, $this->estadoCliente($r['cliente']));
        $this->assertSame('cancelada', $this->estadoReserva($r['futura_manana']));
        $this->assertSame('cancelada', $this->estadoReserva($r['futura_hoy']));
        $this->assertSame('pendiente', $this->estadoReserva($r['pasada']));

        $historial = DB::table('historial_estados_reserva')->orderBy('id_reserva')->get();
        $this->assertCount(2, $historial);
        $this->assertEqualsCanonicalizing(
            [$r['futura_manana'], $r['futura_hoy']],
            $historial->pluck('id_reserva')->map(fn ($id) => (int) $id)->all()
        );
        foreach ($historial as $fila) {
            $this->assertSame($this->adminA, (int) $fila->id_usuario, 'El historial debe nombrar al admin que dio de baja');
            $this->assertSame('cancelada', $fila->estado_nuevo);
            $this->assertSame($this->negocioA, (int) $fila->tenant_id);
        }
        $this->assertSame('confirmada', $historial->firstWhere('id_reserva', $r['futura_hoy'])->estado_anterior);
    }

    public function test_baja_desde_editar_cancelando_tambien_cancela(): void
    {
        $r = $this->clienteConReservas($this->negocioA);

        $this->editar($r['cliente'], 0, 1)
            ->assertJsonPath('error', 0)
            ->assertJsonPath('data.reservas_canceladas', 2);

        $this->assertSame(0, $this->estadoCliente($r['cliente']));
        $this->assertSame('cancelada', $this->estadoReserva($r['futura_manana']));
    }

    public function test_baja_conservando_las_reservas_no_las_toca_y_siguen_recibiendo_recordatorio(): void
    {
        $r = $this->clienteConReservas($this->negocioA, 'cliente@a.test');

        $this->eliminar($r['cliente'], 0)
            ->assertJsonPath('error', 0)
            ->assertJsonPath('data.reservas_canceladas', 0);

        $this->assertSame(0, $this->estadoCliente($r['cliente']));
        $this->assertSame('pendiente', $this->estadoReserva($r['futura_manana']));

        $paraRecordar = collect((new SvcReserva)->listarParaRecordatorio('2026-06-11'))->pluck('id_reserva')->map(fn ($id) => (int) $id);
        $this->assertContains($r['futura_manana'], $paraRecordar->all());
    }

    public function test_una_reserva_con_comision_pagada_se_omite_y_se_cuenta_aparte(): void
    {
        $r = $this->clienteConReservas($this->negocioA);

        $idEmpleado = DB::table('empleados')->insertGetId([
            'tenant_id' => $this->negocioA, 'nombre' => 'Laura', 'telefono' => '3100000000',
            'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01 08:00:00', 'estado' => 1,
        ]);
        $idPago = DB::table('pagos_comisiones')->insertGetId([
            'tenant_id' => $this->negocioA, 'id_empleado' => $idEmpleado, 'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2026-06-30', 'monto_total' => 5000, 'fecha_pago' => '2026-06-09 10:00:00',
            'usuario_registra' => 'test', 'fecha_registro' => '2026-06-09 10:00:00', 'estado' => 1,
        ]);
        DB::table('reservas')->where('id_reserva', $r['futura_manana'])->update(['id_pago_comision' => $idPago, 'id_empleado' => $idEmpleado]);

        $this->eliminar($r['cliente'], 1)
            ->assertJsonPath('error', 0)
            ->assertJsonPath('data.reservas_canceladas', 1)
            ->assertJsonPath('data.reservas_omitidas', 1);

        $this->assertSame('pendiente', $this->estadoReserva($r['futura_manana']));
        $this->assertSame('cancelada', $this->estadoReserva($r['futura_hoy']));
        $this->assertSame(0, DB::table('historial_estados_reserva')->where('id_reserva', $r['futura_manana'])->count());
    }

    /* ================= CORREOS ================= */

    public function test_un_correo_por_reserva_cancelada_si_el_cliente_tiene_email(): void
    {
        Mail::fake();
        $r = $this->clienteConReservas($this->negocioA, 'cliente@a.test');

        $this->eliminar($r['cliente'], 1)->assertJsonPath('error', 0);

        Mail::assertQueuedCount(2);
        foreach ([$r['futura_manana'], $r['futura_hoy']] as $idReserva) {
            Mail::assertQueued(ReservaEstadoActualizado::class, function (ReservaEstadoActualizado $mail) use ($idReserva) {
                return $mail->hasTo('cliente@a.test')
                    && $mail->estadoReserva === 'cancelada'
                    && (int) $mail->reserva['id_reserva'] === $idReserva;
            });
        }
    }

    public function test_sin_email_no_se_envia_nada(): void
    {
        Mail::fake();
        $r = $this->clienteConReservas($this->negocioA, null);

        $this->eliminar($r['cliente'], 1)->assertJsonPath('data.reservas_canceladas', 2);

        Mail::assertNothingQueued();
    }

    public function test_con_el_parametro_en_cero_no_se_envia_nada(): void
    {
        Mail::fake();
        $r = $this->clienteConReservas($this->negocioA, 'cliente@a.test');

        $this->eliminar($r['cliente'], 0)->assertJsonPath('error', 0);

        Mail::assertNothingQueued();
    }

    /* ================= SOLO EN LA TRANSICIÓN DE ACTIVO A INACTIVO ================= */

    public function test_el_parametro_se_ignora_si_el_cliente_ya_estaba_inactivo(): void
    {
        Mail::fake();
        $r = $this->clienteConReservas($this->negocioA, 'cliente@a.test');
        DB::table('clientes')->where('id_cliente', $r['cliente'])->update(['estado' => 0]);

        $this->eliminar($r['cliente'], 1)
            ->assertJsonPath('error', 0)
            ->assertJsonPath('data.reservas_canceladas', 0);
        $this->editar($r['cliente'], 0, 1)->assertJsonPath('data.reservas_canceladas', 0);

        $this->assertSame('pendiente', $this->estadoReserva($r['futura_manana']));
        $this->assertSame(0, DB::table('historial_estados_reserva')->count());
        Mail::assertNothingQueued();
    }

    public function test_reactivar_ignora_el_parametro_y_no_toca_reservas(): void
    {
        $r = $this->clienteConReservas($this->negocioA);
        DB::table('clientes')->where('id_cliente', $r['cliente'])->update(['estado' => 0]);

        $this->editar($r['cliente'], 1, 1)
            ->assertJsonPath('error', 0)
            ->assertJsonPath('data.reservas_canceladas', 0);

        $this->assertSame(1, $this->estadoCliente($r['cliente']));
        $this->assertSame('pendiente', $this->estadoReserva($r['futura_manana']));
        $this->assertSame('confirmada', $this->estadoReserva($r['futura_hoy']));
    }

    public function test_un_valor_invalido_del_parametro_se_rechaza(): void
    {
        $r = $this->clienteConReservas($this->negocioA);

        $this->withSession($this->sesion())
            ->postJson('request/cliente/eliminar', ['id_cliente' => $r['cliente'], 'cancelar_reservas_futuras' => 'si'])
            ->assertJsonPath('error', 1);

        $this->assertSame(1, $this->estadoCliente($r['cliente']));
    }

    /* ================= ATOMICIDAD (mutación MB2) ================= */

    /**
     * Falla forzada a mitad de camino con un trigger real de SQLite: la
     * SEGUNDA cancelación (la de mañana; la primera es la de hoy 15:00) no
     * puede escribir su historial. Para entonces el cliente ya se marcó
     * inactivo y la primera reserva ya se canceló: todo eso tiene que
     * deshacerse.
     */
    public function test_si_algo_falla_a_mitad_de_camino_no_queda_nada_a_medias(): void
    {
        Mail::fake();
        $r = $this->clienteConReservas($this->negocioA, 'cliente@a.test');

        DB::unprepared(
            'CREATE TRIGGER falla_forzada BEFORE INSERT ON historial_estados_reserva '
            .'WHEN NEW.id_reserva = '.$r['futura_manana'].' BEGIN SELECT RAISE(ABORT, \'falla forzada\'); END;'
        );

        $this->eliminar($r['cliente'], 1)->assertJsonPath('error', 1);

        $this->assertSame(1, $this->estadoCliente($r['cliente']), 'El cliente debe seguir activo');
        $this->assertSame('confirmada', $this->estadoReserva($r['futura_hoy']), 'La primera cancelación debe deshacerse');
        $this->assertSame('pendiente', $this->estadoReserva($r['futura_manana']));
        $this->assertSame(0, DB::table('historial_estados_reserva')->count());
        Mail::assertNothingQueued();
    }

    /* ================= ROLES ================= */

    public function test_empleado_y_super_admin_son_rechazados_en_el_endpoint_nuevo(): void
    {
        $r = $this->clienteConReservas($this->negocioA);

        $this->contar($r['cliente'], $this->sesion(2))->assertJsonPath('error', 1)->assertJsonPath('data', []);
        $this->contar($r['cliente'], $this->sesion(3))->assertJsonPath('error', 1)->assertJsonPath('data', []);
    }

    /* ================= AISLAMIENTO ENTRE NEGOCIOS (mutación MB1) ================= */

    public function test_contar_un_cliente_de_otro_negocio_responde_igual_que_uno_inexistente(): void
    {
        $deB = $this->clienteConReservas($this->negocioB);

        $ajeno = $this->contar($deB['cliente'])->assertJsonPath('error', 0)->json();
        $inexistente = $this->contar(999999)->json();

        $this->assertSame(0, $ajeno['data']['total']);
        $this->assertSame($inexistente, $ajeno);
    }

    public function test_dar_de_baja_un_cliente_de_otro_negocio_no_toca_nada_aunque_se_mande_su_id(): void
    {
        $deB = $this->clienteConReservas($this->negocioB, 'b@b.test');
        Mail::fake();

        $this->withSession($this->sesion())
            ->postJson('request/cliente/eliminar', [
                'id_cliente' => $deB['cliente'],
                'cancelar_reservas_futuras' => 1,
                'tenant_id' => $this->negocioB,
            ])
            ->assertJsonPath('error', 1);

        $this->withSession($this->sesion())
            ->postJson('request/cliente/editar', [
                'id_cliente' => $deB['cliente'], 'nombre' => 'X', 'telefono' => '1', 'estado' => 0,
                'cancelar_reservas_futuras' => 1, 'tenant_id' => $this->negocioB,
            ])
            ->assertJsonPath('error', 1);

        $this->assertSame(1, $this->estadoCliente($deB['cliente']));
        $this->assertSame('pendiente', $this->estadoReserva($deB['futura_manana']));
        $this->assertSame('confirmada', $this->estadoReserva($deB['futura_hoy']));
        $this->assertSame(0, DB::table('historial_estados_reserva')->count());
        Mail::assertNothingQueued();
    }

    /**
     * Defensa en profundidad: aun con un dato corrupto (una reserva del
     * negocio B que apunta al id de un cliente de A), la cancelación masiva
     * de A no debe tocarla. La consulta filtra por el negocio de la sesión.
     */
    public function test_la_cancelacion_masiva_nunca_toca_una_reserva_de_otro_negocio(): void
    {
        $deA = $this->clienteConReservas($this->negocioA);
        $corrupta = $this->crearReserva($this->negocioB, $deA['cliente'], '2026-06-12', '10:00');

        $this->contar($deA['cliente'])->assertJsonPath('data.total', 2);

        $this->eliminar($deA['cliente'], 1)
            ->assertJsonPath('error', 0)
            ->assertJsonPath('data.reservas_canceladas', 2);

        $this->assertSame('pendiente', $this->estadoReserva($corrupta));
        $this->assertSame(0, DB::table('historial_estados_reserva')->where('tenant_id', $this->negocioB)->count());
    }
}
