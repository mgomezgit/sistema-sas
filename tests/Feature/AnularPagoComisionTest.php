<?php

namespace Tests\Feature;

use App\Http\Controllers\Request\ReservaController;
use App\Http\Middleware\VerificarSesion;
use App\Service\SvcComision;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Anular un pago de comisión marcado por error. El pago NUNCA se borra ni
 * cambia su monto_total: queda con quién, cuándo y por qué. Sus citas quedan
 * libres (id_pago_comision = null), vuelven al informe como pendientes y se
 * pueden volver a liquidar. Camino real: HTTP con withSession() sobre SQLite
 * en memoria; el pago se marca por el endpoint real de Comisiones.
 *
 * Reloj congelado: miércoles 2026-06-10 a las 12:00. Las citas pagadas son del
 * viernes 2026-06-05 (ya pasadas y completadas, como quedan tras liquidar).
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * Las 25 pasaron a la primera, así que se mutó el código para confirmar que
 * de verdad detectan cada falla (después de cada una: restaurado, 25 passed).
 *
 * M1 — Sin ->where('tenant_id', $tenantId) en la lectura bloqueada del pago:
 *      25 tests, 24 passed, 1 FAILED — la de aislamiento: la respuesta para
 *      el pago de B dejó de ser idéntica a la de un id inexistente (el admin
 *      de A recibió "anulado"). Los datos de B no cambiaron igual, porque la
 *      liberación y la marca filtran por tenant por su cuenta: una segunda
 *      barrera independiente.
 * M2 — Sin transacción (DB::transaction( -> call_user_func(: mismo cuerpo):
 *      25 tests, 22 passed, 3 FAILED — la de atomicidad con el trigger ("Las
 *      citas quedaron libres con el pago sin anular"), la de eventos de
 *      transacción y el guardián estructural.
 * M3 — Sin el chequeo de "ya anulado": 25 tests, 23 passed, 2 FAILED — la
 *      doble anulación (la segunda devolvió error 0) y el guardián.
 * M4 — Sin poner id_pago_comision en null (se dejaba el mismo id): 25 tests,
 *      19 passed, 6 FAILED (4 failures + 2 errors) — liberar las citas,
 *      volver al informe y re-liquidar, editar/cambiar estado/eliminar las
 *      liberadas, doble anulación, historial (cantidad_citas) y "no cuenta
 *      como pagado".
 * M5 — La ruta nueva con ->withoutMiddleware('verificar.modulo:comisiones'):
 *      25 tests, 23 passed, 2 FAILED — el guardián de rutas ("request/
 *      comisiones/pagos/anular sin verificar.modulo:comisiones") y la de
 *      módulo inactivo (se pudo anular con el módulo apagado).
 * M5b — La ruta nueva sin sesion.activa ni restringir.empleado: 25 tests,
 *      23 passed, 2 FAILED — el guardián (nombró los dos middlewares) y la
 *      de roles (el empleado pudo anular).
 */
class AnularPagoComisionTest extends TestCase
{
    use RefreshDatabase;

    const MOTIVO = 'Se marco el periodo equivocado';

    private int $negocioA;

    private int $negocioB;

    private int $adminA;

    private int $adminB;

    private int $cliente;

    private int $masaje;

    private int $laura;

    private int $sofia;

    /** Las tres citas de Laura del 2026-06-05, todas en el pago $pagoLaura. */
    private array $citasLaura;

    private int $citaSofia;

    private int $pagoLaura;

    private int $pagoSofia;

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
        $this->adminA = $this->crearAdmin($this->negocioA, 'admin.a', 'Admin A');
        $this->adminB = $this->crearAdmin($this->negocioB, 'admin.b', 'Admin B');

        $this->cliente = $this->crearCliente($this->negocioA, 'Ana');
        $this->masaje = $this->crearRecurso($this->negocioA, 'Masaje', 60, 50000);
        $this->laura = $this->crearEmpleado($this->negocioA, 'Laura');
        $this->sofia = $this->crearEmpleado($this->negocioA, 'Sofia');

        $this->citasLaura = [
            $this->crearReserva($this->negocioA, $this->cliente, $this->masaje, $this->laura, '2026-06-05', '10:00:00', '11:00:00'),
            $this->crearReserva($this->negocioA, $this->cliente, $this->masaje, $this->laura, '2026-06-05', '14:00:00', '15:00:00'),
            $this->crearReserva($this->negocioA, $this->cliente, $this->masaje, $this->laura, '2026-06-05', '16:00:00', '17:00:00'),
        ];
        $this->citaSofia = $this->crearReserva($this->negocioA, $this->cliente, $this->masaje, $this->sofia, '2026-06-05', '10:00:00', '11:00:00');

        $this->pagoLaura = $this->marcarPagado($this->laura);
        $this->pagoSofia = $this->marcarPagado($this->sofia);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre): int
    {
        $id = DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre, 'rubro' => 'spa', 'dias_atencion' => '1,2,3,4,5,6,7',
            'hora_apertura' => '06:00:00', 'hora_cierre' => '22:00:00',
            'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01 08:00:00', 'estado' => 1,
        ]);

        $this->activarModulo($id, true);

        return $id;
    }

    private function activarModulo(int $tenantId, bool $activo): void
    {
        DB::table('negocio_modulos')->updateOrInsert(
            ['tenant_id' => $tenantId, 'id_modulo' => DB::table('modulos_plataforma')->where('clave', 'comisiones')->value('id_modulo')],
            ['activo' => $activo, 'fecha_activacion' => '2026-06-01 08:00:00', 'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01 08:00:00']
        );
    }

    private function crearAdmin(int $tenantId, string $usuario, string $nombre): int
    {
        return DB::table('usuarios')->insertGetId([
            'tenant_id' => $tenantId, 'id_rol' => 1, 'usuario' => $usuario, 'nombre' => $nombre,
            'email' => $usuario.'@spa.test', 'clave' => bcrypt('ClaveSegura2026'),
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

    private function crearReserva(int $tenantId, int $cliente, int $recurso, int $empleado, string $fecha, string $inicio, string $fin): int
    {
        return DB::table('reservas')->insertGetId([
            'tenant_id' => $tenantId, 'id_cliente' => $cliente, 'id_recurso' => $recurso, 'id_empleado' => $empleado,
            'fecha_reserva' => $fecha, 'hora_inicio' => $inicio, 'hora_fin' => $fin,
            'estado_reserva' => 'completada', 'origen' => 'backoffice',
            'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01 08:00:00', 'estado' => 1,
        ]);
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

    private function sesionB(): array
    {
        return $this->sesion(1, $this->negocioB, $this->adminB);
    }

    /** Liquida la semana del 2026-06-01 por el endpoint real y devuelve el id del pago nuevo. */
    private function marcarPagado(int $idEmpleado, ?array $sesion = null): int
    {
        $antes = (int) DB::table('pagos_comisiones')->max('id_pago_comision');

        $this->withSession($sesion ?? $this->sesion())
            ->postJson('request/comisiones/marcar-pagado', ['id_empleado' => $idEmpleado, 'fecha_inicio' => '2026-06-01', 'fecha_fin' => '2026-06-07'])
            ->assertJsonPath('error', 0);

        $nuevo = (int) DB::table('pagos_comisiones')->max('id_pago_comision');
        $this->assertGreaterThan($antes, $nuevo, 'marcar-pagado no creó el pago');

        return $nuevo;
    }

    private function anular(int $idPago, string $motivo = self::MOTIVO, ?array $sesion = null, array $extra = [])
    {
        return $this->withSession($sesion ?? $this->sesion())
            ->postJson('request/comisiones/pagos/anular', array_merge(['id_pago' => $idPago, 'motivo' => $motivo], $extra));
    }

    private function pago(int $idPago): array
    {
        return (array) DB::table('pagos_comisiones')->where('id_pago_comision', $idPago)->first();
    }

    private function reserva(int $idReserva): array
    {
        return (array) DB::table('reservas')->where('id_reserva', $idReserva)->first();
    }

    private function idsPagoDe(array $idsReservas): array
    {
        return array_map(fn ($id) => $this->reserva($id)['id_pago_comision'], $idsReservas);
    }

    private function historial(?array $sesion = null): array
    {
        return $this->withSession($sesion ?? $this->sesion())
            ->getJson('request/comisiones/historial-pagos')
            ->assertJsonPath('error', 0)
            ->json('data.pagos');
    }

    private function informeLaura(): array
    {
        return $this->withSession($this->sesion())
            ->getJson('request/comisiones/informe?fecha_inicio=2026-06-01&fecha_fin=2026-06-07&id_empleado='.$this->laura)
            ->assertJsonPath('error', 0)
            ->json('data.comisiones');
    }

    /** Un pago del negocio B, con su cita, liquidado por el admin de B. */
    private function crearPagoDeB(): array
    {
        $clienteB = $this->crearCliente($this->negocioB, 'Cliente B');
        $recursoB = $this->crearRecurso($this->negocioB, 'Servicio B', 60, 70000);
        $empleadaB = $this->crearEmpleado($this->negocioB, 'Empleada B');
        $citaB = $this->crearReserva($this->negocioB, $clienteB, $recursoB, $empleadaB, '2026-06-05', '10:00:00', '11:00:00');

        return [$this->marcarPagado($empleadaB, $this->sesionB()), $citaB];
    }

    /* ================= ANULAR UN PAGO VIGENTE ================= */

    public function test_anular_un_pago_vigente_libera_sus_citas_y_lo_deja_registrado_sin_borrarlo(): void
    {
        $antes = $this->pago($this->pagoLaura);
        $estadosAntes = array_map(fn ($id) => $this->reserva($id)['estado_reserva'], $this->citasLaura);
        $historialEstadosAntes = DB::table('historial_estados_reserva')->count();

        $this->anular($this->pagoLaura)->assertJsonPath('error', 0);

        $despues = $this->pago($this->pagoLaura);

        // El pago sigue ahí, con su monto intacto, y marcado como anulado.
        $this->assertNotEmpty($despues, 'El pago no debe borrarse');
        $this->assertSame($antes['monto_total'], $despues['monto_total']);
        $this->assertEquals('15000', $despues['monto_total']);
        $this->assertSame(1, (int) $despues['estado']);
        $this->assertSame('2026-06-10 12:00:00', $despues['anulado_en']);
        $this->assertSame($this->adminA, (int) $despues['anulado_por']);
        $this->assertSame(self::MOTIVO, $despues['motivo_anulacion']);
        $this->assertSame($this->citasLaura, json_decode($despues['reservas_liberadas'], true));
        foreach (['tenant_id', 'id_empleado', 'fecha_inicio', 'fecha_fin', 'fecha_pago', 'usuario_registra', 'fecha_registro'] as $campo) {
            $this->assertSame($antes[$campo], $despues[$campo], "Cambió $campo del pago");
        }

        // Las citas quedan libres, sin tocar su estado ni su historial.
        $this->assertSame([null, null, null], $this->idsPagoDe($this->citasLaura));
        $this->assertSame($estadosAntes, array_map(fn ($id) => $this->reserva($id)['estado_reserva'], $this->citasLaura));
        $this->assertSame($historialEstadosAntes, DB::table('historial_estados_reserva')->count());

        // El otro pago del negocio no se toca.
        $this->assertNull($this->pago($this->pagoSofia)['anulado_en']);
        $this->assertSame($this->pagoSofia, (int) $this->reserva($this->citaSofia)['id_pago_comision']);
    }

    public function test_las_citas_liberadas_vuelven_al_informe_y_se_pueden_volver_a_liquidar(): void
    {
        $this->assertSame([], $this->informeLaura(), 'Antes de anular no hay nada pendiente');

        $this->anular($this->pagoLaura)->assertJsonPath('error', 0);

        $informe = $this->informeLaura();
        $this->assertSame(3, $informe[0]['servicios'][0]['cantidad_citas']);
        $this->assertEquals(15000, $informe[0]['total_comision']);

        $nuevoPago = $this->marcarPagado($this->laura);

        $this->assertNotSame($this->pagoLaura, $nuevoPago);
        $this->assertSame([$nuevoPago, $nuevoPago, $nuevoPago], array_map('intval', $this->idsPagoDe($this->citasLaura)));
        $this->assertEquals('15000', $this->pago($nuevoPago)['monto_total']);
        $this->assertSame([], $this->informeLaura());

        // El anulado sigue en el historial junto al nuevo.
        $porId = collect($this->historial())->keyBy('id_pago_comision');
        $this->assertTrue($porId[$this->pagoLaura]['anulado']);
        $this->assertFalse($porId[$nuevoPago]['anulado']);
    }

    public function test_las_citas_liberadas_se_pueden_editar_cambiar_de_estado_y_eliminar(): void
    {
        [$aEditar, $aCambiar, $aEliminar] = $this->citasLaura;

        $this->anular($this->pagoLaura)->assertJsonPath('error', 0);

        $this->withSession($this->sesion())->postJson('request/reserva/editar', [
            'id_reserva' => $aEditar, 'id_cliente' => $this->cliente, 'id_recurso' => $this->masaje,
            'id_empleado' => $this->sofia, 'fecha_reserva' => '2026-06-12', 'hora_inicio' => '11:00', 'notas' => 'Movida',
        ])->assertJsonPath('error', 0);
        $this->assertSame($this->sofia, (int) $this->reserva($aEditar)['id_empleado']);
        $this->assertSame('2026-06-12', $this->reserva($aEditar)['fecha_reserva']);

        $this->withSession($this->sesion())
            ->postJson('request/reserva/cambiar-estado', ['id_reserva' => $aCambiar, 'estado_reserva' => 'cancelada'])
            ->assertJsonPath('error', 0);
        $this->assertSame('cancelada', $this->reserva($aCambiar)['estado_reserva']);

        $this->withSession($this->sesion())
            ->postJson('request/reserva/eliminar', ['id_reserva' => $aEliminar])
            ->assertJsonPath('error', 0);
        $this->assertSame(0, (int) $this->reserva($aEliminar)['estado']);
    }

    /**
     * Regresión: mientras el pago está vigente, los tres caminos siguen
     * bloqueados, y el mensaje ahora dice dónde se anula el pago.
     */
    public function test_mientras_el_pago_esta_vigente_los_tres_caminos_siguen_bloqueados(): void
    {
        $cita = $this->citasLaura[0];
        $antes = $this->reserva($cita);

        $this->withSession($this->sesion())->postJson('request/reserva/editar', [
            'id_reserva' => $cita, 'id_cliente' => $this->cliente, 'id_recurso' => $this->masaje,
            'id_empleado' => $this->sofia, 'fecha_reserva' => '2026-06-12', 'hora_inicio' => '11:00',
        ])->assertJsonPath('error', 1)->assertJsonPath('mensaje', ReservaController::MENSAJE_EDITAR_PAGADA);

        $this->withSession($this->sesion())
            ->postJson('request/reserva/cambiar-estado', ['id_reserva' => $cita, 'estado_reserva' => 'cancelada'])
            ->assertJsonPath('error', 1)->assertJsonPath('mensaje', ReservaController::MENSAJE_ESTADO_PAGADA);

        $this->withSession($this->sesion())
            ->postJson('request/reserva/eliminar', ['id_reserva' => $cita])
            ->assertJsonPath('error', 1)->assertJsonPath('mensaje', ReservaController::MENSAJE_ELIMINAR_PAGADA);

        $this->assertSame($antes, $this->reserva($cita));

        foreach ([ReservaController::MENSAJE_EDITAR_PAGADA, ReservaController::MENSAJE_ESTADO_PAGADA, ReservaController::MENSAJE_ELIMINAR_PAGADA] as $mensaje) {
            $this->assertStringContainsString('puedes anularlo desde Comisiones, pestaña Historial de pagos', $mensaje);
        }
    }

    /* ================= DOBLE ANULACIÓN ================= */

    /**
     * Anular → re-liquidar → volver a anular el pago viejo. La segunda
     * anulación se rechaza y NO libera las citas, que ya son del pago nuevo.
     */
    public function test_anular_un_pago_ya_anulado_se_rechaza_y_no_toca_nada(): void
    {
        $this->anular($this->pagoLaura)->assertJsonPath('error', 0);
        $nuevoPago = $this->marcarPagado($this->laura);
        $anuladoAntes = $this->pago($this->pagoLaura);

        Carbon::setTestNow(Carbon::parse('2026-06-11 09:00:00'));

        $this->anular($this->pagoLaura, 'Otro motivo distinto')
            ->assertJsonPath('error', 1)
            ->assertJsonPath('mensaje', 'Este pago ya estaba anulado. Recarga el historial para ver su estado actual.');

        $this->assertSame($anuladoAntes, $this->pago($this->pagoLaura), 'La segunda anulación no debe reescribir quién, cuándo ni por qué');
        $this->assertSame([$nuevoPago, $nuevoPago, $nuevoPago], array_map('intval', $this->idsPagoDe($this->citasLaura)));
        $this->assertNull($this->pago($nuevoPago)['anulado_en']);
    }

    /* ================= VALIDACIÓN DEL MOTIVO ================= */

    public static function motivosInvalidos(): array
    {
        return [
            'vacío' => [''],
            'solo espacios' => ['      '],
            'cuatro caracteres' => ['abcd'],
            'cuatro con espacios alrededor' => ['   abcd   '],
            '201 caracteres' => [str_repeat('a', 201)],
        ];
    }

    #[DataProvider('motivosInvalidos')]
    public function test_un_motivo_invalido_se_rechaza_y_el_pago_sigue_vigente(string $motivo): void
    {
        $this->anular($this->pagoLaura, $motivo)->assertJsonPath('error', 1);

        $this->assertNull($this->pago($this->pagoLaura)['anulado_en']);
        $this->assertSame(array_fill(0, 3, $this->pagoLaura), array_map('intval', $this->idsPagoDe($this->citasLaura)));
    }

    public function test_sin_motivo_o_sin_id_de_pago_se_rechaza(): void
    {
        $this->withSession($this->sesion())
            ->postJson('request/comisiones/pagos/anular', ['id_pago' => $this->pagoLaura])
            ->assertJsonPath('error', 1);
        $this->withSession($this->sesion())
            ->postJson('request/comisiones/pagos/anular', ['motivo' => self::MOTIVO])
            ->assertJsonPath('error', 1);
        $this->withSession($this->sesion())
            ->postJson('request/comisiones/pagos/anular', ['id_pago' => 'abc', 'motivo' => self::MOTIVO])
            ->assertJsonPath('error', 1);

        $this->assertNull($this->pago($this->pagoLaura)['anulado_en']);
    }

    public function test_los_limites_de_5_y_200_caracteres_se_aceptan(): void
    {
        $this->anular($this->pagoLaura, 'abcde')->assertJsonPath('error', 0);
        $this->anular($this->pagoSofia, str_repeat('b', 200))->assertJsonPath('error', 0);

        $this->assertSame('abcde', $this->pago($this->pagoLaura)['motivo_anulacion']);
        $this->assertSame(str_repeat('b', 200), $this->pago($this->pagoSofia)['motivo_anulacion']);
    }

    /* ================= ROLES ================= */

    public function test_empleado_super_admin_y_anonimo_son_rechazados(): void
    {
        $this->anular($this->pagoLaura, self::MOTIVO, $this->sesion(2))->assertJsonPath('error', 1);
        $this->anular($this->pagoLaura, self::MOTIVO, $this->sesion(3))->assertJsonPath('error', 1);
        $this->postJson('request/comisiones/pagos/anular', ['id_pago' => $this->pagoLaura, 'motivo' => self::MOTIVO])
            ->assertJsonPath('error', 1);

        $this->assertNull($this->pago($this->pagoLaura)['anulado_en']);
        $this->assertSame(array_fill(0, 3, $this->pagoLaura), array_map('intval', $this->idsPagoDe($this->citasLaura)));
    }

    public function test_con_el_modulo_de_comisiones_inactivo_no_se_puede_anular(): void
    {
        $this->activarModulo($this->negocioA, false);

        $this->anular($this->pagoLaura)->assertJsonPath('error', 1);

        $this->assertNull($this->pago($this->pagoLaura)['anulado_en']);
    }

    /* ================= AISLAMIENTO ENTRE NEGOCIOS ================= */

    /**
     * El admin de A no puede anular un pago de B (ni con tenant_id de B en el
     * cuerpo), y la respuesta es idéntica a la de un id que no existe: no
     * revela que el pago existe. Y al revés, B no puede anular uno de A.
     */
    public function test_un_negocio_no_puede_anular_pagos_de_otro_ni_saber_si_existen(): void
    {
        [$pagoB, $citaB] = $this->crearPagoDeB();
        $antesB = $this->pago($pagoB);
        $antesA = $this->pago($this->pagoLaura);

        $inexistente = $this->anular(999999)->assertJsonPath('error', 1)->getContent();

        $this->assertSame($inexistente, $this->anular($pagoB)->getContent());
        $this->assertSame($inexistente, $this->anular($pagoB, self::MOTIVO, null, ['tenant_id' => $this->negocioB])->getContent());

        // Y en la otra dirección.
        $inexistenteB = $this->anular(999999, self::MOTIVO, $this->sesionB())->getContent();
        $this->assertSame($inexistenteB, $this->anular($this->pagoLaura, self::MOTIVO, $this->sesionB(), ['tenant_id' => $this->negocioA])->getContent());

        $this->assertSame($antesB, $this->pago($pagoB));
        $this->assertSame($pagoB, (int) $this->reserva($citaB)['id_pago_comision']);
        $this->assertSame($antesA, $this->pago($this->pagoLaura));
        $this->assertSame(array_fill(0, 3, $this->pagoLaura), array_map('intval', $this->idsPagoDe($this->citasLaura)));
    }

    /** Un tenant_id en el cuerpo se ignora: manda el de la sesión. */
    public function test_el_tenant_id_del_cuerpo_se_ignora_al_anular_el_propio(): void
    {
        [$pagoB] = $this->crearPagoDeB();

        $this->anular($this->pagoLaura, self::MOTIVO, null, ['tenant_id' => $this->negocioB])
            ->assertJsonPath('error', 0);

        $this->assertNotNull($this->pago($this->pagoLaura)['anulado_en']);
        $this->assertSame($this->negocioA, (int) $this->pago($this->pagoLaura)['tenant_id']);
        $this->assertNull($this->pago($pagoB)['anulado_en']);
    }

    /* ================= HISTORIAL ================= */

    public function test_el_historial_muestra_la_anulacion_con_nombre_y_sin_ids_de_usuario_ni_datos_ajenos(): void
    {
        [$pagoB] = $this->crearPagoDeB();
        $this->anular($pagoB, 'Motivo secreto de B', $this->sesionB())->assertJsonPath('error', 0);
        $this->anular($this->pagoLaura)->assertJsonPath('error', 0);

        $respuesta = $this->withSession($this->sesion())->getJson('request/comisiones/historial-pagos');
        $pagos = collect($respuesta->json('data.pagos'))->keyBy('id_pago_comision');

        $this->assertEqualsCanonicalizing([$this->pagoLaura, $this->pagoSofia], $pagos->keys()->all(), 'Solo pagos del propio negocio');

        $anulado = $pagos[$this->pagoLaura];
        $this->assertTrue($anulado['anulado']);
        $this->assertSame('2026-06-10 12:00:00', $anulado['anulado_en']);
        $this->assertSame('Admin A', $anulado['anulado_por']);
        $this->assertSame(self::MOTIVO, $anulado['motivo_anulacion']);
        $this->assertSame(3, $anulado['citas_liberadas']);
        $this->assertSame(0, $anulado['cantidad_citas']);
        $this->assertEquals('15000', $anulado['monto_total'], 'El monto del anulado se muestra tal cual se pagó');

        $vigente = $pagos[$this->pagoSofia];
        $this->assertFalse($vigente['anulado']);
        $this->assertNull($vigente['anulado_en']);
        $this->assertNull($vigente['anulado_por']);
        $this->assertNull($vigente['motivo_anulacion']);
        $this->assertSame(0, $vigente['citas_liberadas']);
        $this->assertSame(1, $vigente['cantidad_citas']);

        // Lista blanca de campos: nada de ids de usuario ni de la lista de reservas.
        $campos = ['id_pago_comision', 'id_empleado', 'nombre_empleado', 'fecha_inicio', 'fecha_fin', 'monto_total',
            'fecha_pago', 'estado', 'cantidad_citas', 'anulado', 'anulado_en', 'anulado_por', 'motivo_anulacion', 'citas_liberadas'];
        foreach ($pagos as $pago) {
            $this->assertEqualsCanonicalizing($campos, array_keys($pago));
        }

        $contenido = $respuesta->getContent();
        foreach (['id_usuario', 'reservas_liberadas', 'tenant_id', 'Motivo secreto de B', 'Admin B', 'Empleada B'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $contenido);
        }
    }

    public function test_si_no_queda_quien_anulo_el_historial_dice_sistema(): void
    {
        $this->anular($this->pagoLaura)->assertJsonPath('error', 0);
        DB::table('pagos_comisiones')->where('id_pago_comision', $this->pagoLaura)->update(['anulado_por' => null]);

        $pagos = collect($this->historial())->keyBy('id_pago_comision');

        $this->assertSame('Sistema', $pagos[$this->pagoLaura]['anulado_por']);
    }

    /**
     * Hoy no hay ningún total de pagos en el sistema (ni el historial ni el
     * informe suman pagos): lo que se comprueba es que un pago anulado no
     * cuenta como pagado en ningún lado, y que sus citas se cuentan UNA vez,
     * como pendientes.
     */
    public function test_un_pago_anulado_no_cuenta_como_pagado(): void
    {
        $this->anular($this->pagoLaura)->assertJsonPath('error', 0);

        $vigentes = collect($this->historial())->where('anulado', false);
        $this->assertSame([$this->pagoSofia], $vigentes->pluck('id_pago_comision')->all());
        $this->assertEquals(5000, $vigentes->sum('monto_total'));

        $informe = $this->informeLaura();
        $this->assertSame(3, $informe[0]['servicios'][0]['cantidad_citas']);
    }

    /* ================= ATOMICIDAD ================= */

    /**
     * Las citas se liberan ANTES de marcar el pago. Si marcar el pago falla
     * (aquí, un trigger de SQLite que aborta ese UPDATE), las citas tienen que
     * volver a quedar en el pago: o se hace todo, o nada.
     */
    public function test_si_marcar_el_pago_falla_las_citas_siguen_en_el_pago(): void
    {
        DB::unprepared("CREATE TRIGGER fallar_anulacion BEFORE UPDATE ON pagos_comisiones
            WHEN NEW.anulado_en IS NOT NULL BEGIN SELECT RAISE(ABORT, 'fallo forzado'); END;");

        $this->anular($this->pagoLaura)
            ->assertJsonPath('error', 1)
            ->assertJsonPath('mensaje', fn ($mensaje) => str_contains($mensaje, 'COM-PAGO-ANULAR-ERR'));

        $this->assertNull($this->pago($this->pagoLaura)['anulado_en']);
        $this->assertSame(array_fill(0, 3, $this->pagoLaura), array_map('intval', $this->idsPagoDe($this->citasLaura)), 'Las citas quedaron libres con el pago sin anular');
    }

    /* ================= TRANSACCIÓN Y BLOQUEO DE FILA ================= */

    /**
     * Lectura del pago, liberación de las citas y marca del pago en la MISMA
     * transacción. Se observa con los eventos de la conexión.
     */
    public function test_anular_lee_y_escribe_dentro_de_una_transaccion(): void
    {
        $eventos = [];
        Event::listen(TransactionBeginning::class, function () use (&$eventos) {
            $eventos[] = 'inicio';
        });
        Event::listen(TransactionCommitted::class, function () use (&$eventos) {
            $eventos[] = 'commit';
        });
        DB::listen(function (QueryExecuted $q) use (&$eventos) {
            if (preg_match('/^select .* from "pagos_comisiones"/i', $q->sql)) {
                $eventos[] = 'select_pago';
            } elseif (preg_match('/^update "reservas"/i', $q->sql)) {
                $eventos[] = 'update_reservas';
            } elseif (preg_match('/^update "pagos_comisiones"/i', $q->sql)) {
                $eventos[] = 'update_pago';
            }
        });

        $this->anular($this->pagoLaura)->assertJsonPath('error', 0);

        $inicio = array_search('inicio', $eventos, true);
        $this->assertNotFalse($inicio, 'No abrió una transacción');

        $despues = array_slice($eventos, $inicio);
        $commit = array_search('commit', $despues, true);
        $this->assertNotFalse($commit, 'La transacción no se confirmó');

        $dentro = array_slice($despues, 1, $commit - 1);
        $this->assertSame(['select_pago', 'update_reservas', 'update_pago'], $dentro, 'Lectura y escrituras deben ir juntas, dentro de la transacción');
    }

    /**
     * SQLite compila lockForUpdate() a nada: ninguna prueba de comportamiento
     * puede ver si falta. Guardián estructural: anularPago() bloquea el pago y
     * sus reservas, dentro de una transacción, y revisa que no esté anulado.
     */
    public function test_anular_bloquea_el_pago_y_sus_reservas(): void
    {
        $codigo = File::get(app_path('Service/SvcComision.php'));
        $inicio = strpos($codigo, 'public function anularPago(');
        $this->assertNotFalse($inicio);
        $cuerpo = substr($codigo, $inicio, strpos($codigo, "\n    }\n", $inicio) - $inicio);

        $this->assertStringContainsString('DB::transaction(', $cuerpo);
        $this->assertSame(2, substr_count($cuerpo, '->lockForUpdate()'), 'Debe bloquear la fila del pago y las de sus reservas');
        $this->assertStringContainsString("->where('tenant_id', \$tenantId)", $cuerpo);
        $this->assertStringContainsString('ANULACION_YA_ANULADO', $cuerpo);
    }

    /* ================= LOG ================= */

    public function test_cada_anulacion_queda_en_el_log_con_quien_pago_citas_y_motivo(): void
    {
        $canal = Mockery::spy();
        Log::shouldReceive('channel')->with('database')->andReturn($canal);

        $this->anular($this->pagoLaura, 'Periodo equivocado "junio"')->assertJsonPath('error', 0);

        $canal->shouldHaveReceived('info')->withArgs(function ($mensaje) {
            return is_string($mensaje)
                && str_contains($mensaje, 'el usuario '.$this->adminA.' anulo el pago '.$this->pagoLaura.' del negocio '.$this->negocioA)
                && str_contains($mensaje, 'reservas liberadas: 3 ('.implode(', ', $this->citasLaura).')')
                && str_contains($mensaje, 'motivo: "Periodo equivocado \"junio\""');
        })->once();
    }

    /* ================= ESCAPE DEL MOTIVO ================= */

    /**
     * El motivo es texto escrito por una persona: se guarda tal cual (el
     * escape se hace al pintar, nunca al guardar, para no ver entidades
     * literales) y la vista lo pinta con escaparTexto(). La ejecución real con
     * estos payloads se verificó con navegador (ver reporte).
     */
    public function test_un_motivo_con_html_y_comillas_se_guarda_crudo_y_la_vista_lo_escapa(): void
    {
        $payload = '<img src=x onerror=alert(1)> "dobles" y \'simples\'';

        $this->anular($this->pagoLaura, $payload)->assertJsonPath('error', 0);

        $this->assertSame($payload, $this->pago($this->pagoLaura)['motivo_anulacion']);
        $pagos = collect($this->historial())->keyBy('id_pago_comision');
        $this->assertSame($payload, $pagos[$this->pagoLaura]['motivo_anulacion']);

        $vista = File::get(resource_path('views/app/comisiones/informe.blade.php'));
        $this->assertStringContainsString('escaparTexto(fila.motivo_anulacion)', $vista);
        $this->assertStringContainsString('escaparTexto(fila.anulado_por)', $vista);
        $this->assertStringContainsString('escaparTexto(fila.anulado_en)', $vista);
        $this->assertStringContainsString("html: 'Se anulará el pago de <strong>' + escaparTexto(fila.nombre_empleado)", $vista);
    }

    /* ================= GUARDIÁN DE RUTAS ================= */

    /**
     * Toda ruta de request/comisiones/* lleva los tres middlewares del módulo.
     * Se descuentan los excluidos con withoutMiddleware(): una ruta que los
     * quita no está protegida aunque esté dentro del grupo.
     */
    public function test_toda_ruta_de_comisiones_lleva_sesion_rol_y_modulo(): void
    {
        $requeridos = ['sesion.activa', 'restringir.empleado', 'verificar.modulo:comisiones'];
        $faltantes = [];
        $uris = [];

        foreach (Route::getRoutes() as $ruta) {
            if (! str_starts_with($ruta->uri(), 'request/comisiones/')) {
                continue;
            }

            $uris[] = $ruta->uri();
            $efectivos = array_diff($ruta->gatherMiddleware(), $ruta->excludedMiddleware());

            foreach ($requeridos as $middleware) {
                if (! in_array($middleware, $efectivos, true)) {
                    $faltantes[] = $ruta->uri().' sin '.$middleware;
                }
            }
        }

        $this->assertContains('request/comisiones/pagos/anular', $uris, 'La ruta de anular no existe');
        $this->assertSame([], $faltantes, "Rutas de comisiones sin su protección:\n".implode("\n", $faltantes));
    }
}
