<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Mail\NuevaSolicitudPublica;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Un cliente dado de baja pide cita desde la página pública.
 *
 * La solicitud entra normal (reutiliza su ficha por teléfono, el cliente
 * sigue inactivo), pero el admin lo ve: campana con cliente_inactivo, aviso
 * en el modal (estado_cliente) y una línea en el correo NuevaSolicitudPublica.
 * Desde afuera NADA cambia: la respuesta pública es idéntica byte a byte.
 *
 * Reloj congelado: miércoles 2026-06-10 a las 09:00; las citas se piden para
 * el jueves 2026-06-11.
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * MS1 — PublicoController::agendar() responde distinto para un inactivo (un
 *       setDataResponse extra cuando el cliente está de baja): 9 tests,
 *       8 passed, 1 FAILED —
 *       test_la_respuesta_publica_es_identica_byte_a_byte_para_activo_e_inactivo.
 *       Restaurado: 9 passed.
 * MS2 — Sin ->where('r.tenant_id', $tenantId) en listarSolicitudesPendientes():
 *       9 tests, 8 passed, 1 FAILED —
 *       test_el_campo_y_el_aviso_nunca_aparecen_en_la_campana_de_otro_negocio.
 *       Restaurado: 9 passed.
 * MS3 — Sin ->where('r.tenant_id', $tenantId) en SvcReserva::listarById()
 *       (lo que lee el modal): 9 tests, 8 passed, 1 FAILED —
 *       test_el_modal_recibe_el_estado_del_cliente_solo_de_la_reserva_propia.
 *       Restaurado: 9 passed.
 */
class ClienteInactivoSolicitudPublicaTest extends TestCase
{
    use RefreshDatabase;

    const PAYLOAD = '<img src=x onerror=alert(1)>';

    private int $negocioA;

    private int $negocioB;

    private int $clienteInactivoA;

    private int $clienteActivoA;

    private int $recursoA;

    private int $recursoB;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = $this->crearNegocio('Spa A', 'spa-a');
        $this->negocioB = $this->crearNegocio('Spa B', 'spa-b');

        foreach ([$this->negocioA => 'admin@a.test', $this->negocioB => 'admin@b.test'] as $tenant => $email) {
            DB::table('usuarios')->insert([
                'tenant_id' => $tenant, 'id_rol' => 1, 'usuario' => $email, 'nombre' => 'Admin', 'email' => $email,
                'clave' => bcrypt('ClaveSegura2026'), 'usuario_registra' => 'test',
                'fecha_registro' => '2026-06-01 08:00:00', 'estado' => 1,
            ]);
        }

        $this->recursoA = $this->crearRecurso($this->negocioA);
        $this->recursoB = $this->crearRecurso($this->negocioB);

        $this->clienteInactivoA = $this->crearCliente($this->negocioA, 'Ines De Baja', '3001111111', 0);
        $this->clienteActivoA = $this->crearCliente($this->negocioA, 'Ana Activa', '3002222222', 1);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre, string $slug): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre, 'slug' => $slug, 'rubro' => 'spa', 'dias_atencion' => '1,2,3,4,5,6,7',
            'hora_apertura' => '08:00:00', 'hora_cierre' => '18:00:00',
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

    private function crearCliente(int $tenantId, string $nombre, string $telefono, int $estado): int
    {
        return DB::table('clientes')->insertGetId([
            'tenant_id' => $tenantId, 'nombre' => $nombre, 'telefono' => $telefono, 'email' => null,
            'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01 08:00:00', 'estado' => $estado,
        ]);
    }

    private function pedirCita(string $slug, string $nombre, string $telefono, string $hora = '10:00', array $extra = [])
    {
        return $this->postJson('publico/'.$slug.'/agendar', array_merge([
            'nombre' => $nombre,
            'telefono' => $telefono,
            'id_recurso' => $slug === 'spa-a' ? $this->recursoA : $this->recursoB,
            'fecha_reserva' => '2026-06-11',
            'hora_inicio' => $hora,
        ], $extra));
    }

    private function sesionAdmin(int $tenantId): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 999999,
            'usuario' => 'admin',
            'nombre_usuario' => 'Admin',
            'tenant_id' => $tenantId,
            'id_rol' => 1,
        ];
    }

    private function campana(int $tenantId): array
    {
        return $this->withSession($this->sesionAdmin($tenantId))
            ->getJson('request/reserva/solicitudes-pendientes')
            ->assertJsonPath('error', 0)
            ->json('data.solicitudes');
    }

    private function ultimaReservaDe(int $idCliente)
    {
        return DB::table('reservas')->where('id_cliente', $idCliente)->orderByDesc('id_reserva')->first();
    }

    /* ================= LA SOLICITUD ENTRA, EL CLIENTE SIGUE INACTIVO ================= */

    public function test_un_cliente_inactivo_que_pide_cita_queda_como_solicitud_pendiente_y_sigue_inactivo(): void
    {
        $this->pedirCita('spa-a', 'Ines De Baja', '3001111111')->assertJsonPath('error', 0);

        $reserva = $this->ultimaReservaDe($this->clienteInactivoA);
        $this->assertNotNull($reserva, 'Se reutiliza su ficha por teléfono');
        $this->assertSame('pendiente', $reserva->estado_reserva);
        $this->assertSame('publico', $reserva->origen);
        $this->assertSame(0, (int) DB::table('clientes')->where('id_cliente', $this->clienteInactivoA)->value('estado'));
        $this->assertSame(2, DB::table('clientes')->where('tenant_id', $this->negocioA)->count(), 'No se crea una ficha nueva');

        $solicitud = collect($this->campana($this->negocioA))->firstWhere('id_reserva', $reserva->id_reserva);
        $this->assertTrue($solicitud['cliente_inactivo']);
    }

    public function test_con_un_cliente_activo_el_campo_es_falso(): void
    {
        $this->pedirCita('spa-a', 'Ana Activa', '3002222222')->assertJsonPath('error', 0);

        $reserva = $this->ultimaReservaDe($this->clienteActivoA);
        $solicitud = collect($this->campana($this->negocioA))->firstWhere('id_reserva', $reserva->id_reserva);
        $this->assertFalse($solicitud['cliente_inactivo']);
    }

    /* ================= DESDE AFUERA NO SE NOTA (mutación MS1) ================= */

    public function test_la_respuesta_publica_es_identica_byte_a_byte_para_activo_e_inactivo(): void
    {
        $activo = $this->pedirCita('spa-a', 'Ana Activa', '3002222222', '10:00');
        $inactivo = $this->pedirCita('spa-a', 'Ines De Baja', '3001111111', '11:00');

        $activo->assertJsonPath('error', 0);
        $this->assertSame($activo->getStatusCode(), $inactivo->getStatusCode());
        $this->assertSame($activo->getContent(), $inactivo->getContent());
    }

    /* ================= CORREO AL ADMIN ================= */

    public function test_el_correo_al_admin_agrega_la_linea_solo_si_el_cliente_esta_inactivo(): void
    {
        Mail::fake();
        $linea = 'Este cliente está dado de baja en tu negocio';

        $this->pedirCita('spa-a', 'Ines De Baja', '3001111111', '10:00');
        $this->pedirCita('spa-a', 'Ana Activa', '3002222222', '11:00');

        Mail::assertQueuedCount(2);
        Mail::assertQueued(NuevaSolicitudPublica::class, function (NuevaSolicitudPublica $mail) use ($linea) {
            if ($mail->solicitud['nombre_cliente'] !== 'Ines De Baja') {
                return false;
            }

            $html = $mail->render();

            return $mail->hasTo('admin@a.test')
                && $mail->solicitud['cliente_inactivo'] === true
                && substr_count($html, $linea) === 1;
        });
        Mail::assertQueued(NuevaSolicitudPublica::class, function (NuevaSolicitudPublica $mail) use ($linea) {
            return $mail->solicitud['nombre_cliente'] === 'Ana Activa'
                && $mail->solicitud['cliente_inactivo'] === false
                && ! str_contains($mail->render(), $linea);
        });
    }

    /**
     * La línea nueva no arrastra datos de más (ni el estado como número, ni
     * ids, ni el correo del cliente), y lo que escribió el visitante sigue
     * escapado aunque el cliente esté de baja.
     */
    public function test_el_correo_no_repite_datos_de_mas_y_sigue_escapado(): void
    {
        Mail::fake();
        DB::table('clientes')->where('id_cliente', $this->clienteInactivoA)->update(['email' => 'ines.privado@correo.test']);

        $this->pedirCita('spa-a', self::PAYLOAD, '3001111111', '10:00', ['notas' => self::PAYLOAD]);

        Mail::assertQueued(NuevaSolicitudPublica::class, function (NuevaSolicitudPublica $mail) {
            $html = $mail->render();

            return $mail->solicitud['cliente_inactivo'] === true
                && ! str_contains($html, self::PAYLOAD)
                && str_contains($html, e(self::PAYLOAD))
                && ! str_contains($html, 'ines.privado@correo.test')
                && ! str_contains($html, 'estado_cliente')
                && ! str_contains($html, 'cliente_inactivo')
                && array_keys($mail->solicitud) === [
                    'nombre_cliente', 'telefono_cliente', 'nombre_recurso', 'fecha_reserva',
                    'hora_inicio', 'hora_fin', 'notas', 'cliente_inactivo',
                ];
        });
    }

    /* ================= AISLAMIENTO (mutaciones MS2 y MS3) ================= */

    public function test_el_campo_y_el_aviso_nunca_aparecen_en_la_campana_de_otro_negocio(): void
    {
        $this->pedirCita('spa-a', 'Ines De Baja', '3001111111')->assertJsonPath('error', 0);
        $this->pedirCita('spa-b', 'Bea De B', '3003333333')->assertJsonPath('error', 0);

        $campanaB = $this->campana($this->negocioB);

        $this->assertCount(1, $campanaB);
        $this->assertSame('Bea De B', $campanaB[0]['nombre_cliente']);
        $this->assertFalse($campanaB[0]['cliente_inactivo']);
        $this->assertNotContains('Ines De Baja', array_column($campanaB, 'nombre_cliente'));
    }

    public function test_el_modal_recibe_el_estado_del_cliente_solo_de_la_reserva_propia(): void
    {
        $this->pedirCita('spa-a', 'Ines De Baja', '3001111111');
        $reservaA = $this->ultimaReservaDe($this->clienteInactivoA)->id_reserva;

        $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/reserva/obtener?id_reserva='.$reservaA)
            ->assertJsonPath('data.reserva.estado_cliente', 0)
            ->assertJsonPath('data.reserva.nombre_cliente', 'Ines De Baja');

        // El negocio B pidiendo el id de A a mano: nada, ni el estado del cliente.
        $ajena = $this->withSession($this->sesionAdmin($this->negocioB))
            ->getJson('request/reserva/obtener?id_reserva='.$reservaA)
            ->assertJsonPath('error', 0);
        $this->assertNull($ajena->json('data.reserva'));
        $this->assertStringNotContainsString('Ines De Baja', $ajena->getContent());
    }

    /* ================= CONFIRMAR Y EDITAR SIN REACTIVAR ================= */

    public function test_confirmar_y_editar_la_reserva_funciona_sin_reactivar_al_cliente(): void
    {
        Mail::fake();
        $this->pedirCita('spa-a', 'Ines De Baja', '3001111111');
        $idReserva = $this->ultimaReservaDe($this->clienteInactivoA)->id_reserva;

        $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/reserva/cambiar-estado', ['id_reserva' => $idReserva, 'estado_reserva' => 'confirmada'])
            ->assertJsonPath('error', 0);

        $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/reserva/editar', [
                'id_reserva' => $idReserva,
                'id_cliente' => $this->clienteInactivoA,
                'id_recurso' => $this->recursoA,
                'fecha_reserva' => '2026-06-11',
                'hora_inicio' => '14:00',
                'notas' => 'Confirmada por telefono',
            ])
            ->assertJsonPath('error', 0);

        $reserva = DB::table('reservas')->where('id_reserva', $idReserva)->first();
        $this->assertSame('confirmada', $reserva->estado_reserva);
        $this->assertSame('Confirmada por telefono', $reserva->notas);
        $this->assertSame(0, (int) DB::table('clientes')->where('id_cliente', $this->clienteInactivoA)->value('estado'));
    }

    public function test_el_calendario_y_la_agenda_tambien_traen_el_estado_del_cliente(): void
    {
        $this->pedirCita('spa-a', 'Ines De Baja', '3001111111', '10:00');
        $this->pedirCita('spa-a', 'Ana Activa', '3002222222', '11:00');
        $sesion = $this->sesionAdmin($this->negocioA);

        $agenda = collect($this->withSession($sesion)->getJson('request/reserva/listar?fecha_inicio=2026-06-11&fecha_fin=2026-06-11')->json('data.reservas'));
        $this->assertSame(0, (int) $agenda->firstWhere('nombre_cliente', 'Ines De Baja')['estado_cliente']);
        $this->assertSame(1, (int) $agenda->firstWhere('nombre_cliente', 'Ana Activa')['estado_cliente']);

        $eventos = collect($this->withSession($sesion)->getJson('request/reserva/listar-calendario?fecha_inicio=2026-06-11&fecha_fin=2026-06-11')->json('data.eventos'));
        $estados = $eventos->mapWithKeys(fn ($e) => [$e['title'] => (int) $e['extendedProps']['estado_cliente']]);
        $this->assertSame(0, $estados['Ines De Baja - Masaje']);
        $this->assertSame(1, $estados['Ana Activa - Masaje']);
    }
}
