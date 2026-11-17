<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Mail\CodigoRecuperacionClave;
use App\Mail\ComprobantePagoPlataforma;
use App\Mail\ConfirmarRegistroPublico;
use App\Mail\IntentoRegistroCorreoExistente;
use App\Mail\ModuloPorVencer;
use App\Mail\NuevaSolicitudPublica;
use App\Mail\ReservaConfirmada;
use App\Mail\ReservaEstadoActualizado;
use App\Mail\ReservaRecordatorio;
use App\Mail\ResumenStockBajo;
use App\Service\SvcModulo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActivaModulos;
use Tests\TestCase;

/**
 * Módulo de pago "correos" (Correos a tus clientas).
 *
 * Controla SOLO los tres correos a la clienta (ReservaConfirmada,
 * ReservaEstadoActualizado y ReservaRecordatorio). Sin el módulo (apagado,
 * vencido o nunca activado) no sale ninguno por ningún camino, pero todo lo
 * demás funciona igual. Los correos del sistema nunca dependen de él.
 *
 * Reloj congelado el lunes 2026-11-16 a las 09:00; el negocio de prueba
 * atiende todos los días de 06:00 a 22:00. Mail::fake en todas las pruebas.
 *
 * Negocio A: CON el módulo. Negocio B: SIN el módulo (nunca activado).
 *
 * ================= MUTACIONES (resultado en el reporte de la tarea) =================
 * M1: sin el control de SvcNotificacionReserva (correosActivos): 9 fallos
 *     (crear sin módulo, tenant_id en el cuerpo, cambiar estado admin y
 *     empleado, solicitud pública, baja de cliente, vigencia, aislamiento y
 *     el guardián). Restaurado, pasan.
 * M2: sin el filtro del comando de recordatorios: 3 fallos (sin módulo,
 *     vencido ayer, apagado). Restaurado, pasan.
 * M3: con SvcNegocio::crear activando el módulo: falla
 *     test_un_negocio_nuevo_por_registro_publico_nace_sin_el_modulo. Restaurado, pasa.
 * M4: con la migración sin activar a los existentes: falla
 *     test_la_migracion_activa_el_modulo_en_los_negocios_existentes. Restaurado, pasa.
 * (M5, el guardián, está en GuardianCorreosClientasTest.)
 */
class ModuloCorreosTest extends TestCase
{
    use ActivaModulos;
    use RefreshDatabase;

    const HOY = '2026-11-16';

    const FECHA_CITA = '2026-11-23';

    private int $negocioA;

    private int $negocioB;

    private int $recursoA;

    private int $recursoB;

    private int $clienteA;

    private int $clienteB;

    private int $empleadoA;

    private int $empleadoB;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Carbon::setTestNow(Carbon::parse(self::HOY.' 09:00:00'));

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = $this->crearNegocio('Spa Con Correos', 'spa-con-correos');
        $this->negocioB = $this->crearNegocio('Spa Sin Correos', 'spa-sin-correos');

        $this->crearAdmin($this->negocioA, 'admin.a', 'admin.a@negocio.test');
        $this->crearAdmin($this->negocioB, 'admin.b', 'admin.b@negocio.test');

        $this->recursoA = $this->crearRecurso($this->negocioA, 'Masaje A');
        $this->recursoB = $this->crearRecurso($this->negocioB, 'Masaje B');

        $this->clienteA = $this->crearCliente($this->negocioA, 'Clienta A', 'clienta.a@correo.test');
        $this->clienteB = $this->crearCliente($this->negocioB, 'Clienta B', 'clienta.b@correo.test');

        $this->empleadoA = $this->crearEmpleado($this->negocioA, 'Empleada A');
        $this->empleadoB = $this->crearEmpleado($this->negocioB, 'Empleada B');

        // Solo A tiene el módulo: B nunca lo activó (como un negocio nuevo).
        $this->activarModulo($this->negocioA, 'correos');

        // La solicitud pública necesita la página completa (módulo
        // "pagina_publica") en los dos: aquí se prueba solo el de correos.
        $this->activarModulo($this->negocioA, 'pagina_publica');
        $this->activarModulo($this->negocioB, 'pagina_publica');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre, string $slug, int $estado = 1): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'slug' => $slug,
            'rubro' => 'spa',
            'telefono_contacto' => '3001234567',
            'dias_atencion' => '1,2,3,4,5,6,7',
            'hora_apertura' => '06:00:00',
            'hora_cierre' => '22:00:00',
            'usuario_registra' => 'test',
            'fecha_registro' => now(),
            'estado' => $estado,
        ]);
    }

    private function crearAdmin(int $tenantId, string $usuario, string $email): int
    {
        return DB::table('usuarios')->insertGetId([
            'tenant_id' => $tenantId, 'id_rol' => 1, 'usuario' => $usuario, 'nombre' => 'Admin '.$usuario,
            'email' => $email, 'clave' => bcrypt('ClaveSegura2026'),
            'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ]);
    }

    private function crearRecurso(int $tenantId, string $nombre): int
    {
        return DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $tenantId, 'nombre' => $nombre, 'duracion_minutos' => 60, 'precio' => 50000,
            'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ]);
    }

    private function crearCliente(int $tenantId, string $nombre, ?string $email): int
    {
        return DB::table('clientes')->insertGetId([
            'tenant_id' => $tenantId, 'nombre' => $nombre, 'telefono' => '3000000000', 'email' => $email,
            'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ]);
    }

    private function crearEmpleado(int $tenantId, string $nombre): int
    {
        return DB::table('empleados')->insertGetId([
            'tenant_id' => $tenantId, 'nombre' => $nombre, 'telefono' => '3000000000',
            'porcentaje_comision' => 10,
            'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ]);
    }

    private function insertarReserva(int $tenantId, int $idCliente, int $idRecurso, array $extra = []): int
    {
        return DB::table('reservas')->insertGetId(array_merge([
            'tenant_id' => $tenantId, 'id_cliente' => $idCliente, 'id_recurso' => $idRecurso, 'id_empleado' => null,
            'fecha_reserva' => self::FECHA_CITA, 'hora_inicio' => '10:00:00', 'hora_fin' => '11:00:00',
            'estado_reserva' => 'pendiente', 'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ], $extra));
    }

    private function sesionAdmin(int $tenantId, string $nombreNegocio = 'Spa'): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 999999,
            'usuario' => 'admin.test',
            'nombre_usuario' => 'Admin Test',
            'tenant_id' => $tenantId,
            'id_rol' => 1,
            'nombre_negocio_sesion' => $nombreNegocio,
        ];
    }

    private function sesionEmpleado(int $tenantId, int $idEmpleado): array
    {
        return ['id_rol' => 2, 'id_empleado' => $idEmpleado, 'id_usuario' => 999998] + $this->sesionAdmin($tenantId);
    }

    private function crearReservaHttp(int $tenantId, int $idCliente, int $idRecurso, array $extra = [])
    {
        return $this->withSession($this->sesionAdmin($tenantId))->postJson('request/reserva/crear', array_merge([
            'id_cliente' => $idCliente, 'id_recurso' => $idRecurso, 'fecha_reserva' => self::FECHA_CITA, 'hora_inicio' => '10:00',
        ], $extra));
    }

    private function cambiarEstadoHttp(int $tenantId, int $idReserva, string $estado)
    {
        return $this->withSession($this->sesionAdmin($tenantId))->postJson('request/reserva/cambiar-estado', [
            'id_reserva' => $idReserva, 'estado_reserva' => $estado,
        ]);
    }

    private function assertSinCorreosAClientas(): void
    {
        Mail::assertNotQueued(ReservaConfirmada::class);
        Mail::assertNotQueued(ReservaEstadoActualizado::class);
        Mail::assertNotQueued(ReservaRecordatorio::class);
    }

    /* ================= 1) CREAR RESERVA (ADMIN) ================= */

    public function test_con_el_modulo_crear_una_reserva_envia_la_confirmacion_a_su_clienta(): void
    {
        $this->crearReservaHttp($this->negocioA, $this->clienteA, $this->recursoA)->assertJsonPath('error', 0);

        Mail::assertQueued(ReservaConfirmada::class, fn ($m) => $m->hasTo('clienta.a@correo.test'));
        Mail::assertQueuedCount(1);
    }

    public function test_sin_el_modulo_crear_una_reserva_no_envia_nada_pero_la_reserva_se_crea(): void
    {
        $respuesta = $this->crearReservaHttp($this->negocioB, $this->clienteB, $this->recursoB)->assertJsonPath('error', 0);

        $this->assertDatabaseHas('reservas', ['id_reserva' => $respuesta->json('data.id_reserva'), 'tenant_id' => $this->negocioB]);
        Mail::assertNothingQueued();

        // El correo de la clienta se conserva aunque no se le escriba.
        $this->assertSame('clienta.b@correo.test', DB::table('clientes')->where('id_cliente', $this->clienteB)->value('email'));
    }

    /** El módulo lo decide el negocio de la SESIÓN: un tenant_id en el cuerpo se ignora. */
    public function test_un_tenant_id_en_el_cuerpo_no_activa_los_correos_de_otro_negocio(): void
    {
        $this->crearReservaHttp($this->negocioB, $this->clienteB, $this->recursoB, ['tenant_id' => $this->negocioA])
            ->assertJsonPath('error', 0);

        Mail::assertNothingQueued();
        $this->assertSame(1, DB::table('reservas')->where('tenant_id', $this->negocioB)->count());
        $this->assertSame(0, DB::table('reservas')->where('tenant_id', $this->negocioA)->count());
    }

    /* ================= 2) CAMBIAR ESTADO (ADMIN Y EMPLEADO) ================= */

    public function test_cambiar_estado_admin_con_y_sin_el_modulo(): void
    {
        $reservaA = $this->insertarReserva($this->negocioA, $this->clienteA, $this->recursoA);
        $reservaB = $this->insertarReserva($this->negocioB, $this->clienteB, $this->recursoB);

        $this->cambiarEstadoHttp($this->negocioA, $reservaA, 'confirmada')->assertJsonPath('error', 0);
        $this->cambiarEstadoHttp($this->negocioB, $reservaB, 'confirmada')->assertJsonPath('error', 0);

        Mail::assertQueued(ReservaEstadoActualizado::class, fn ($m) => $m->hasTo('clienta.a@correo.test') && $m->estadoReserva === 'confirmada');
        Mail::assertNotQueued(ReservaEstadoActualizado::class, fn ($m) => $m->hasTo('clienta.b@correo.test'));
        Mail::assertQueuedCount(1);

        // Sin el módulo, el estado cambia y el historial se registra igual.
        $this->assertSame('confirmada', DB::table('reservas')->where('id_reserva', $reservaB)->value('estado_reserva'));
        $this->assertSame(1, DB::table('historial_estados_reserva')->where('id_reserva', $reservaB)->count());
    }

    public function test_cambiar_estado_desde_el_empleado_con_y_sin_el_modulo(): void
    {
        $reservaA = $this->insertarReserva($this->negocioA, $this->clienteA, $this->recursoA, ['id_empleado' => $this->empleadoA]);
        $reservaB = $this->insertarReserva($this->negocioB, $this->clienteB, $this->recursoB, ['id_empleado' => $this->empleadoB]);

        $this->withSession($this->sesionEmpleado($this->negocioA, $this->empleadoA))
            ->postJson('request/reserva/cambiar-estado-mi-cita', ['id_reserva' => $reservaA, 'estado_reserva' => 'confirmada'])
            ->assertJsonPath('error', 0);
        $this->withSession($this->sesionEmpleado($this->negocioB, $this->empleadoB))
            ->postJson('request/reserva/cambiar-estado-mi-cita', ['id_reserva' => $reservaB, 'estado_reserva' => 'confirmada'])
            ->assertJsonPath('error', 0);

        Mail::assertQueued(ReservaEstadoActualizado::class, fn ($m) => $m->hasTo('clienta.a@correo.test'));
        Mail::assertQueuedCount(1);
        $this->assertSame('confirmada', DB::table('reservas')->where('id_reserva', $reservaB)->value('estado_reserva'));
    }

    /* ================= 3) CONFIRMAR UNA SOLICITUD PÚBLICA ================= */

    public function test_confirmar_una_solicitud_publica_con_y_sin_el_modulo(): void
    {
        foreach ([[$this->negocioA, 'spa-con-correos', $this->recursoA, $this->empleadoA, 'visita.a@correo.test'],
            [$this->negocioB, 'spa-sin-correos', $this->recursoB, $this->empleadoB, 'visita.b@correo.test']] as [$negocio, $slug, $recurso, $empleado, $correo]) {
            $this->postJson("publico/{$slug}/agendar", [
                'nombre' => 'Visitante', 'telefono' => '3005556677', 'email' => $correo,
                'id_recurso' => $recurso, 'fecha_reserva' => '2026-11-18', 'hora_inicio' => '10:00',
            ])->assertJsonPath('error', 0);

            $reserva = DB::table('reservas')->where('tenant_id', $negocio)->where('origen', 'publico')->first();
            $this->assertNotNull($reserva);

            $this->withSession($this->sesionAdmin($negocio))->postJson('request/reserva/editar', [
                'id_reserva' => $reserva->id_reserva, 'id_cliente' => $reserva->id_cliente, 'id_recurso' => $recurso,
                'id_empleado' => $empleado, 'fecha_reserva' => '2026-11-18', 'hora_inicio' => '10:00',
            ])->assertJsonPath('error', 0);

            $this->cambiarEstadoHttp($negocio, $reserva->id_reserva, 'confirmada')->assertJsonPath('error', 0);
            $this->assertSame('confirmada', DB::table('reservas')->where('id_reserva', $reserva->id_reserva)->value('estado_reserva'));
        }

        Mail::assertQueued(ReservaEstadoActualizado::class, fn ($m) => $m->hasTo('visita.a@correo.test'));
        Mail::assertNotQueued(ReservaEstadoActualizado::class, fn ($m) => $m->hasTo('visita.b@correo.test'));
        Mail::assertNotQueued(ReservaConfirmada::class);

        // El aviso al ADMIN de la solicitud (NuevaSolicitudPublica) no es del
        // módulo: sale en los dos negocios.
        Mail::assertQueued(NuevaSolicitudPublica::class, fn ($m) => $m->hasTo('admin.a@negocio.test'));
        Mail::assertQueued(NuevaSolicitudPublica::class, fn ($m) => $m->hasTo('admin.b@negocio.test'));
    }

    /* ================= 4) BAJA DE CLIENTE CON CANCELACIÓN ================= */

    public function test_baja_de_cliente_cancelando_reservas_con_y_sin_el_modulo(): void
    {
        $reservasA = [
            $this->insertarReserva($this->negocioA, $this->clienteA, $this->recursoA),
            $this->insertarReserva($this->negocioA, $this->clienteA, $this->recursoA, ['hora_inicio' => '12:00:00', 'hora_fin' => '13:00:00']),
        ];
        $reservasB = [
            $this->insertarReserva($this->negocioB, $this->clienteB, $this->recursoB),
            $this->insertarReserva($this->negocioB, $this->clienteB, $this->recursoB, ['hora_inicio' => '12:00:00', 'hora_fin' => '13:00:00']),
        ];

        foreach ([[$this->negocioA, $this->clienteA, 'Clienta A'], [$this->negocioB, $this->clienteB, 'Clienta B']] as [$negocio, $cliente, $nombre]) {
            $this->withSession($this->sesionAdmin($negocio))->postJson('request/cliente/editar', [
                'id_cliente' => $cliente, 'nombre' => $nombre, 'telefono' => '3000000000', 'email' => strtolower(str_replace(' ', '.', $nombre)).'@correo.test',
                'estado' => 0, 'cancelar_reservas_futuras' => 1,
            ])->assertJsonPath('error', 0);
        }

        Mail::assertQueuedCount(2);
        Mail::assertQueued(ReservaEstadoActualizado::class, fn ($m) => $m->hasTo('clienta.a@correo.test') && $m->estadoReserva === 'cancelada');
        Mail::assertNotQueued(ReservaEstadoActualizado::class, fn ($m) => $m->hasTo('clienta.b@correo.test'));

        // Sin el módulo, la baja cancela las reservas igual.
        foreach (array_merge($reservasA, $reservasB) as $idReserva) {
            $this->assertSame('cancelada', DB::table('reservas')->where('id_reserva', $idReserva)->value('estado_reserva'));
        }

        $this->assertSame(0, (int) DB::table('clientes')->where('id_cliente', $this->clienteB)->value('estado'));
    }

    /* ================= 5) RECORDATORIOS ================= */

    private function reservaDeManana(int $negocio, int $cliente, int $recurso): int
    {
        return $this->insertarReserva($negocio, $cliente, $recurso, ['fecha_reserva' => '2026-11-17', 'estado_reserva' => 'confirmada']);
    }

    public function test_recordatorios_solo_para_negocios_con_el_modulo(): void
    {
        $this->reservaDeManana($this->negocioA, $this->clienteA, $this->recursoA);
        $this->reservaDeManana($this->negocioB, $this->clienteB, $this->recursoB);

        Artisan::call('reservas:enviar-recordatorios');

        Mail::assertQueued(ReservaRecordatorio::class, fn ($m) => $m->hasTo('clienta.a@correo.test'));
        Mail::assertNotQueued(ReservaRecordatorio::class, fn ($m) => $m->hasTo('clienta.b@correo.test'));
        Mail::assertQueuedCount(1);
    }

    public function test_recordatorios_con_el_modulo_que_vence_hoy_si_salen(): void
    {
        $this->activarModulo($this->negocioA, 'correos', self::HOY);
        $this->reservaDeManana($this->negocioA, $this->clienteA, $this->recursoA);

        Artisan::call('reservas:enviar-recordatorios');

        Mail::assertQueued(ReservaRecordatorio::class, fn ($m) => $m->hasTo('clienta.a@correo.test'));
    }

    public function test_recordatorios_con_el_modulo_vencido_ayer_no_salen(): void
    {
        $this->activarModulo($this->negocioA, 'correos', '2026-11-15');
        $this->reservaDeManana($this->negocioA, $this->clienteA, $this->recursoA);

        Artisan::call('reservas:enviar-recordatorios');

        Mail::assertNothingQueued();
    }

    public function test_recordatorios_con_el_modulo_apagado_no_salen(): void
    {
        $this->desactivarModulo($this->negocioA, 'correos');
        $this->reservaDeManana($this->negocioA, $this->clienteA, $this->recursoA);

        Artisan::call('reservas:enviar-recordatorios');

        Mail::assertNothingQueued();
    }

    public function test_un_negocio_suspendido_sigue_sin_recibir_recordatorios_aunque_tenga_el_modulo(): void
    {
        DB::table('negocios')->where('id_negocio', $this->negocioA)->update(['estado' => 0]);
        $this->reservaDeManana($this->negocioA, $this->clienteA, $this->recursoA);

        Artisan::call('reservas:enviar-recordatorios');

        Mail::assertNothingQueued();
    }

    /* ================= 6) VIGENCIA EN LOS CORREOS POR EVENTO ================= */

    public function test_los_correos_por_evento_respetan_la_vigencia_en_tiempo_real(): void
    {
        // Vence hoy: sale.
        $this->activarModulo($this->negocioA, 'correos', self::HOY);
        $this->crearReservaHttp($this->negocioA, $this->clienteA, $this->recursoA)->assertJsonPath('error', 0);
        Mail::assertQueuedCount(1);

        // Venció ayer (aunque el proceso diario no lo haya apagado): no sale.
        $this->activarModulo($this->negocioA, 'correos', '2026-11-15');
        $this->crearReservaHttp($this->negocioA, $this->clienteA, $this->recursoA, ['hora_inicio' => '14:00'])->assertJsonPath('error', 0);
        Mail::assertQueuedCount(1);

        // Apagado: no sale.
        $this->activarModulo($this->negocioA, 'correos');
        $this->desactivarModulo($this->negocioA, 'correos');
        $this->crearReservaHttp($this->negocioA, $this->clienteA, $this->recursoA, ['hora_inicio' => '16:00'])->assertJsonPath('error', 0);
        Mail::assertQueuedCount(1);

        $this->assertSame(3, DB::table('reservas')->where('tenant_id', $this->negocioA)->count());
    }

    /* ================= 7) AISLAMIENTO ENTRE NEGOCIOS ================= */

    public function test_aislamiento_nunca_llega_un_correo_de_un_negocio_a_la_clienta_de_otro(): void
    {
        // A con módulo y B sin él, creando en paralelo: solo la clienta de A.
        $this->crearReservaHttp($this->negocioA, $this->clienteA, $this->recursoA)->assertJsonPath('error', 0);
        $this->crearReservaHttp($this->negocioB, $this->clienteB, $this->recursoB)->assertJsonPath('error', 0);

        Mail::assertQueuedCount(1);
        Mail::assertQueued(ReservaConfirmada::class, fn ($m) => $m->hasTo('clienta.a@correo.test')
            && ! $m->hasTo('clienta.b@correo.test')
            && $m->negocio['nombre_negocio'] === 'Spa Con Correos');

        // A (con módulo) intentando usar la clienta o tocar la reserva de B: rechazado y sin correo.
        $this->crearReservaHttp($this->negocioA, $this->clienteB, $this->recursoA)->assertJsonPath('error', 1);

        $reservaB = $this->insertarReserva($this->negocioB, $this->clienteB, $this->recursoB, ['hora_inicio' => '15:00:00', 'hora_fin' => '16:00:00']);
        $this->cambiarEstadoHttp($this->negocioA, $reservaB, 'cancelada')->assertJsonPath('error', 1);

        Mail::assertQueuedCount(1);
        Mail::assertNotQueued(ReservaConfirmada::class, fn ($m) => $m->hasTo('clienta.b@correo.test'));
        Mail::assertNotQueued(ReservaEstadoActualizado::class);
        $this->assertSame('pendiente', DB::table('reservas')->where('id_reserva', $reservaB)->value('estado_reserva'));
    }

    /* ================= 8) LOS CORREOS DEL SISTEMA NO DEPENDEN DEL MÓDULO ================= */

    private function registrar(string $correo)
    {
        return $this->postJson('request/registro-publico/crear', [
            'nombre_negocio' => 'Spa Recien Llegado', 'telefono_contacto' => '3001234567', 'rubro' => 'spa',
            'nombre' => 'Duena Nueva', 'email' => $correo, 'clave' => 'ClaveElegida2026', 'confirmar_clave' => 'ClaveElegida2026',
        ]);
    }

    public function test_un_negocio_nuevo_por_registro_publico_nace_sin_el_modulo(): void
    {
        $this->registrar('duena.nueva@correo.test')->assertJsonPath('error', 0);

        // El correo de confirmación del registro (sistema) sale sin el módulo.
        Mail::assertQueued(ConfirmarRegistroPublico::class, fn ($m) => $m->hasTo('duena.nueva@correo.test'));
        $link = Mail::queued(ConfirmarRegistroPublico::class)->first()->urlConfirmacion;

        // Confirmar crea el negocio y entra al panel.
        $this->get($link)->assertRedirect(url('backoffice/dashboard'));

        $idNuevo = DB::table('negocios')->where('nombre_negocio', 'Spa Recien Llegado')->value('id_negocio');
        $this->assertNotNull($idNuevo, 'La confirmación debía crear el negocio');

        $idModulo = DB::table('modulos_plataforma')->where('clave', 'correos')->value('id_modulo');
        $this->assertDatabaseMissing('negocio_modulos', ['tenant_id' => $idNuevo, 'id_modulo' => $idModulo]);
        $this->assertFalse(app(SvcModulo::class)->estaActivo($idNuevo, 'correos'));
    }

    public function test_los_correos_del_sistema_siguen_saliendo_sin_el_modulo(): void
    {
        // Intento de registro con un correo ya usado (B, sin el módulo).
        $this->registrar('admin.b@negocio.test')->assertJsonPath('error', 0);
        Mail::assertQueued(IntentoRegistroCorreoExistente::class, fn ($m) => $m->hasTo('admin.b@negocio.test'));

        // Código de recuperación de clave.
        $this->postJson('request/recuperacion/solicitar', ['correo' => 'admin.b@negocio.test'])->assertOk();
        Mail::assertQueued(CodigoRecuperacionClave::class, fn ($m) => $m->hasTo('admin.b@negocio.test'));

        // Resumen de stock bajo (lo atiende Inventario, no este módulo).
        $this->activarModulo($this->negocioB, 'inventario');
        DB::table('productos')->insert([
            'tenant_id' => $this->negocioB, 'nombre' => 'Aceite', 'cantidad_actual' => 1, 'cantidad_minima' => 5,
            'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ]);
        Artisan::call('productos:enviar-resumen-stock-bajo');
        Mail::assertQueued(ResumenStockBajo::class, fn ($m) => $m->hasTo('admin.b@negocio.test'));

        // Aviso de vencimiento de un módulo a 7 días.
        $this->activarModulo($this->negocioB, 'reportes', '2026-11-23');
        Artisan::call('modulos:procesar-vencimientos');
        Mail::assertQueued(ModuloPorVencer::class, fn ($m) => $m->hasTo('admin.b@negocio.test'));

        $this->assertSinCorreosAClientas();
        $this->assertFalse(app(SvcModulo::class)->estaActivo($this->negocioB, 'correos'));
    }

    public function test_el_comprobante_de_pago_sale_sin_el_modulo(): void
    {
        $this->withSession([
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 99, 'usuario' => 'super', 'nombre_usuario' => 'Super',
            'tenant_id' => null, 'id_rol' => 3,
        ])->postJson('request/superadmin/activar-modulo', [
            'id_negocio' => $this->negocioB, 'clave_modulo' => 'reportes', 'origen' => 'pagado_fuera',
            'vigente_hasta' => '2026-12-16', 'registrar_pago' => '1', 'monto' => '85000',
            'metodo' => 'transferencia', 'referencia' => 'TRF-001',
        ])->assertJsonPath('error', 0);

        Mail::assertQueued(ComprobantePagoPlataforma::class, fn ($m) => $m->hasTo('admin.b@negocio.test'));
        $this->assertSinCorreosAClientas();
    }

    /* ================= 9) MIGRACIÓN ================= */

    public function test_la_migracion_activa_el_modulo_en_los_negocios_existentes(): void
    {
        $idModulo = DB::table('modulos_plataforma')->where('clave', 'correos')->value('id_modulo');
        $this->assertNotNull($idModulo, 'La migración siembra el catálogo');
        $this->assertSame('Correos a tus clientas', DB::table('modulos_plataforma')->where('id_modulo', $idModulo)->value('nombre'));

        // A tiene la fila (activa) y un tercer negocio la tiene apagada a mano.
        $apagado = $this->crearNegocio('Spa Apagado', 'spa-apagado');
        $this->activarModulo($apagado, 'correos');
        $this->desactivarModulo($apagado, 'correos');

        $migracion = require database_path('migrations/2026_11_17_100000_crear_modulo_correos.php');
        $migracion->up();
        $migracion->up(); // idempotente

        $fila = DB::table('negocio_modulos')->where('tenant_id', $this->negocioB)->where('id_modulo', $idModulo)->get();
        $this->assertCount(1, $fila, 'Una sola fila aunque la migración corra dos veces');
        $this->assertSame(1, (int) $fila[0]->activo);
        $this->assertNull($fila[0]->vigente_hasta);
        $this->assertSame('cortesia', $fila[0]->origen);
        $this->assertSame('Incluido al crear el modulo', $fila[0]->nota);
        $this->assertSame('migracion', $fila[0]->usuario_registra);
        $this->assertTrue(app(SvcModulo::class)->estaActivo($this->negocioB, 'correos'));

        // No reactiva lo que un super admin apagó.
        $this->assertSame(0, (int) DB::table('negocio_modulos')->where('tenant_id', $apagado)->where('id_modulo', $idModulo)->value('activo'));
    }

    /* ================= 10) TARJETA EN CONFIGURACIÓN ================= */

    private function configuracion(int $tenantId, string $nombreNegocio = 'Spa'): string
    {
        return $this->withSession($this->sesionAdmin($tenantId, $nombreNegocio))->get('backoffice/configuracion')->assertOk()->getContent();
    }

    public function test_la_tarjeta_con_el_modulo_activo_dice_que_correos_salen(): void
    {
        $html = $this->configuracion($this->negocioA);

        $this->assertStringContainsString('id="tarjeta-correos-clientas"', $html);
        $this->assertStringContainsString('id="texto-correos-activo"', $html);
        $this->assertStringContainsString('Un recordatorio el día anterior a su cita.', $html);
        $this->assertStringNotContainsString('id="texto-correos-bloqueado"', $html);
        $this->assertStringNotContainsString('id="btn-whatsapp-correos"', $html);
    }

    public function test_la_tarjeta_sin_el_modulo_muestra_candado_plan_y_whatsapp_solo_con_digitos(): void
    {
        config(['plataforma.contacto_whatsapp' => '+57 300 123 4567', 'plataforma.contacto_correo' => '']);

        $html = $this->configuracion($this->negocioB, 'Spa Sin Correos');

        $this->assertStringContainsString('id="texto-correos-bloqueado"', $html);
        $this->assertStringContainsString('bi-lock-fill', $html);
        $this->assertStringContainsString('Disponible en el plan Pro', $html);
        $this->assertStringNotContainsString('id="texto-correos-activo"', $html);

        $mensaje = rawurlencode('Hola, soy del spa Spa Sin Correos y quiero el plan Pro (Correos a tus clientas).');
        $this->assertStringContainsString('href="https://wa.me/573001234567?text='.$mensaje.'"', $html);
        $this->assertStringContainsString('href="'.url('backoffice/modulo/correos').'"', $html);
        $this->assertStringNotContainsString('id="contacto-generico-correos"', $html);
    }

    public function test_la_tarjeta_sin_contacto_usa_el_texto_generico(): void
    {
        config(['plataforma.contacto_whatsapp' => '', 'plataforma.contacto_correo' => '']);

        $html = $this->configuracion($this->negocioB);

        $this->assertStringContainsString('id="contacto-generico-correos"', $html);
        $this->assertStringContainsString('Comunícate con soporte para activarlo.', $html);
        $this->assertStringNotContainsString('wa.me', $html);
    }

    public function test_el_nombre_del_negocio_con_html_sale_escapado_en_la_tarjeta(): void
    {
        config(['plataforma.contacto_whatsapp' => '573001234567']);
        $payload = '<img src=x onerror=alert(1)>';

        $html = $this->configuracion($this->negocioB, $payload);

        $this->assertStringNotContainsString($payload, $html);
        $this->assertStringContainsString('?text='.rawurlencode('Hola, soy del spa '.$payload.' y quiero el plan Pro (Correos a tus clientas).'), $html);
    }

    public function test_la_tarjeta_rechaza_empleado_y_anonimo(): void
    {
        $this->withSession($this->sesionEmpleado($this->negocioB, $this->empleadoB))->get('backoffice/configuracion')
            ->assertRedirect();
        $this->get('backoffice/configuracion')->assertRedirect();

        $this->withSession($this->sesionEmpleado($this->negocioB, $this->empleadoB))->get('backoffice/modulo/correos')
            ->assertRedirect();
    }

    public function test_la_pantalla_no_disponible_del_modulo_correos(): void
    {
        $this->withSession($this->sesionAdmin($this->negocioB))->get('backoffice/modulo/correos')
            ->assertOk()
            ->assertSee('Correos a tus clientas')
            ->assertSee('Disponible en el plan Pro');

        // Con el módulo activo, la pantalla sobra: lleva a Configuración.
        $this->withSession($this->sesionAdmin($this->negocioA))->get('backoffice/modulo/correos')
            ->assertRedirect(url('backoffice/configuracion'));
    }

    /* ================= 11) AVISO DE BAJA EN CLIENTES ================= */

    public function test_el_aviso_de_baja_solo_promete_correo_con_el_modulo(): void
    {
        $conModulo = $this->withSession($this->sesionAdmin($this->negocioA))->get('backoffice/clientes')->assertOk()->getContent();
        $sinModulo = $this->withSession($this->sesionAdmin($this->negocioB))->get('backoffice/clientes')->assertOk()->getContent();

        $this->assertStringContainsString('por correo al cliente', $conModulo);
        $this->assertStringNotContainsString('por correo', $sinModulo);
        $this->assertStringContainsString('sus reservas futuras quedar', $sinModulo);
    }
}
