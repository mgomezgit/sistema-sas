<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Models\HistorialEstadoReserva;
use App\Service\SvcReserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Rastro de auditoría de los cambios de estado de una reserva, y bloqueo del
 * cambio cuando la comisión de la cita ya fue pagada.
 *
 * Los 3 caminos que llegan a SvcReserva::cambiarEstado() (confirmados antes de
 * tocar código):
 *   1. Admin: request/reserva/cambiar-estado, desde el modal de edición (que lo
 *      llama en una segunda petición si cambió el estado) y desde los círculos
 *      de estado del panel de detalle.
 *   2. Empleado: request/reserva/cambiar-estado-mi-cita, desde Mis Citas.
 *   3. Confirmar una solicitud pública: la campana lleva a la agenda y se
 *      confirma por el camino 1; no hay un endpoint aparte.
 * Ningún proceso de sistema (comandos, scheduler) cambia estado_reserva hoy.
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * M1 — Sin el candado de id_pago_comision en SvcReserva::cambiarEstado():
 *      17 tests, 13 passed, 4 FAILED — admin, empleado, super admin (el
 *      Service devolvió 'hecho' en vez de 'comision_pagada') y "mismo estado".
 *      Restaurado: 17 passed.
 * M2 — Sin ->where('h.tenant_id', $tenantId) en listarHistorialEstados():
 *      17 tests, 16 passed, 1 FAILED — el negocio B recibió el historial de
 *      la reserva del negocio A. Restaurado: 17 passed.
 * M3 — Sin DB::transaction() (la misma función, ejecutada sin transacción):
 *      17 tests, 16 passed, 1 FAILED — "El estado quedó cambiado sin su
 *      historial". Restaurado: 17 passed.
 * M4 — El Controller pasando null en vez de session('id_usuario'): 17 tests,
 *      13 passed, 4 FAILED — las pruebas de admin, empleado, solicitud
 *      pública y nombres del historial. Restaurado: 17 passed.
 * M5 — Sin el chequeo "la cita es del empleado" en cambiarEstadoMiCita()
 *      (endpoint que no tenía ninguna prueba antes de esta tarea): 20 tests,
 *      19 passed, 1 FAILED — el empleado cambió la cita de otro empleado.
 *      La prueba de "reserva de otro negocio" siguió pasando: el Service
 *      filtra por tenant por su cuenta, una segunda barrera independiente.
 * M6 — Sin las DOS barreras (el chequeo del Controller y el filtro de tenant
 *      de cambiarEstado()): 20 tests, 17 passed, 3 FAILED — el admin de B
 *      cambió una reserva de A, y el empleado cambió una de otro empleado y
 *      una de otro negocio. Restaurado: 20 passed.
 */
class ReservaHistorialEstadoTest extends TestCase
{
    use RefreshDatabase;

    const MENSAJE_COMISION_PAGADA = 'Esta cita ya forma parte de un pago de comisión confirmado y no se puede modificar. Si el pago se marcó por error, puedes anularlo desde Comisiones, pestaña Historial de pagos.';

    private int $negocioA;

    private int $negocioB;

    private int $adminA;

    private int $adminB;

    private int $superAdmin;

    private int $usuarioEmpleadoA;

    private int $empleadoA;

    private int $clienteA;

    private int $clienteB;

    private int $recursoA;

    private int $recursoB;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = $this->crearNegocio('Spa A', 'spa-a');
        $this->negocioB = $this->crearNegocio('Spa B', 'spa-b');
        $this->activarModuloComisiones($this->negocioA);

        $this->adminA = $this->crearUsuario($this->negocioA, 1, 'ana', 'Ana Admin');
        $this->adminB = $this->crearUsuario($this->negocioB, 1, 'beto', 'Beto Admin B');
        $this->superAdmin = $this->crearUsuario(null, 3, 'super', 'Super Plataforma');
        $this->usuarioEmpleadoA = $this->crearUsuario($this->negocioA, 2, 'laura', 'Laura Empleada');

        $this->empleadoA = DB::table('empleados')->insertGetId([
            'tenant_id' => $this->negocioA,
            'nombre' => 'Laura Empleada',
            'telefono' => '3000000000',
            'porcentaje_comision' => 10,
            'id_usuario' => $this->usuarioEmpleadoA,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $this->clienteA = $this->crearCliente($this->negocioA, 'Cliente A');
        $this->clienteB = $this->crearCliente($this->negocioB, 'Cliente B');
        $this->recursoA = $this->crearRecurso($this->negocioA, 'Masaje A');
        $this->recursoB = $this->crearRecurso($this->negocioB, 'Masaje B');
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre, string $slug): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'slug' => $slug,
            'rubro' => 'spa',
            'dias_atencion' => '1,2,3,4,5,6,7',
            'hora_apertura' => '06:00:00',
            'hora_cierre' => '22:00:00',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function activarModuloComisiones(int $tenantId): void
    {
        DB::table('negocio_modulos')->insert([
            'tenant_id' => $tenantId,
            'id_modulo' => DB::table('modulos_plataforma')->where('clave', 'comisiones')->value('id_modulo'),
            'activo' => true,
            'fecha_activacion' => date('Y-m-d H:i:s'),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
        ]);
    }

    private function crearUsuario(?int $tenantId, int $idRol, string $usuario, string $nombre): int
    {
        return DB::table('usuarios')->insertGetId([
            'tenant_id' => $tenantId,
            'id_rol' => $idRol,
            'usuario' => $usuario,
            'nombre' => $nombre,
            'email' => $usuario.'@historial.test',
            'clave' => bcrypt('ClaveSegura2026'),
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
            'telefono' => '300'.rand(1000000, 9999999),
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

    private function crearReserva(int $tenantId, string $estado = 'pendiente', array $extra = []): int
    {
        $esA = $tenantId === $this->negocioA;

        return DB::table('reservas')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'id_cliente' => $esA ? $this->clienteA : $this->clienteB,
            'id_recurso' => $esA ? $this->recursoA : $this->recursoB,
            'id_empleado' => $esA ? $this->empleadoA : null,
            'fecha_reserva' => '2026-01-10',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado_reserva' => $estado,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ], $extra));
    }

    private function sesionAdmin(int $idUsuario, int $tenantId): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => $idUsuario,
            'usuario' => 'admin',
            'nombre_usuario' => 'Admin',
            'tenant_id' => $tenantId,
            'id_rol' => 1,
        ];
    }

    private function sesionEmpleado(): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => $this->usuarioEmpleadoA,
            'usuario' => 'laura',
            'nombre_usuario' => 'Laura Empleada',
            'tenant_id' => $this->negocioA,
            'id_rol' => 2,
            'id_empleado' => $this->empleadoA,
        ];
    }

    private function sesionSuperAdmin(): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => $this->superAdmin,
            'usuario' => 'super',
            'nombre_usuario' => 'Super',
            'tenant_id' => null,
            'id_rol' => 3,
        ];
    }

    private function cambiarComoAdmin(int $idReserva, string $estado, ?int $idAdmin = null, ?int $tenantId = null)
    {
        $this->flushSession();

        return $this->withSession($this->sesionAdmin($idAdmin ?? $this->adminA, $tenantId ?? $this->negocioA))
            ->postJson('request/reserva/cambiar-estado', ['id_reserva' => $idReserva, 'estado_reserva' => $estado]);
    }

    private function cambiarComoEmpleado(int $idReserva, string $estado)
    {
        $this->flushSession();

        return $this->withSession($this->sesionEmpleado())
            ->postJson('request/reserva/cambiar-estado-mi-cita', ['id_reserva' => $idReserva, 'estado_reserva' => $estado]);
    }

    private function historialDe(int $idReserva)
    {
        return DB::table('historial_estados_reserva')->where('id_reserva', $idReserva)->orderBy('id_historial')->get();
    }

    private function estadoDe(int $idReserva): string
    {
        return DB::table('reservas')->where('id_reserva', $idReserva)->value('estado_reserva');
    }

    /** Paga la comisión de la reserva por el camino real (endpoint de Comisiones). */
    private function pagarComisionDe(int $idReserva): int
    {
        $this->flushSession();

        $idPago = $this->withSession($this->sesionAdmin($this->adminA, $this->negocioA))
            ->postJson('request/comisiones/marcar-pagado', [
                'id_empleado' => $this->empleadoA,
                'fecha_inicio' => '2026-01-01',
                'fecha_fin' => '2026-01-31',
            ])
            ->assertJsonPath('error', 0)
            ->json('data.id_pago_comision');

        $this->assertSame($idPago, DB::table('reservas')->where('id_reserva', $idReserva)->value('id_pago_comision'));

        return $idPago;
    }

    /* ================= 1) CADA CAMBIO DEJA SU FILA ================= */

    public function test_cambiar_el_estado_registra_estado_anterior_nuevo_usuario_y_fecha(): void
    {
        $idReserva = $this->crearReserva($this->negocioA, 'pendiente');

        $this->cambiarComoAdmin($idReserva, 'confirmada')->assertJsonPath('error', 0);

        $historial = $this->historialDe($idReserva);

        $this->assertCount(1, $historial);
        $this->assertSame($this->negocioA, (int) $historial[0]->tenant_id);
        $this->assertSame('pendiente', $historial[0]->estado_anterior);
        $this->assertSame('confirmada', $historial[0]->estado_nuevo);
        $this->assertSame($this->adminA, (int) $historial[0]->id_usuario);
        $this->assertNotNull($historial[0]->fecha_cambio);
    }

    /* ================= 2) LOS 3 CAMINOS ================= */

    public function test_camino_empleado_deja_rastro_con_el_usuario_del_empleado(): void
    {
        $idReserva = $this->crearReserva($this->negocioA, 'pendiente');

        $this->cambiarComoEmpleado($idReserva, 'confirmada')->assertJsonPath('error', 0);

        $historial = $this->historialDe($idReserva);
        $this->assertCount(1, $historial);
        $this->assertSame($this->usuarioEmpleadoA, (int) $historial[0]->id_usuario);
        $this->assertSame('pendiente', $historial[0]->estado_anterior);
        $this->assertSame('confirmada', $historial[0]->estado_nuevo);
    }

    public function test_camino_confirmar_una_solicitud_publica_deja_rastro_con_el_admin_que_la_confirma(): void
    {
        $manana = now()->addDay()->toDateString();

        // La solicitud entra por la página pública real, sin sesión.
        $this->postJson('publico/spa-a/agendar', [
            'nombre' => 'Visitante Web',
            'telefono' => '3119998877',
            'id_recurso' => $this->recursoA,
            'fecha_reserva' => $manana,
            'hora_inicio' => '10:00',
        ])->assertJsonPath('error', 0);

        $idReserva = (int) DB::table('reservas')->where('origen', 'publico')->value('id_reserva');
        $this->assertSame('pendiente', $this->estadoDe($idReserva));

        // Crear la solicitud no es un "cambio de estado": nace pendiente.
        $this->assertCount(0, $this->historialDe($idReserva));

        // El admin la confirma por el mismo endpoint al que lleva la campana.
        $this->cambiarComoAdmin($idReserva, 'confirmada')->assertJsonPath('error', 0);

        $historial = $this->historialDe($idReserva);
        $this->assertCount(1, $historial);
        $this->assertSame($this->adminA, (int) $historial[0]->id_usuario);
        $this->assertSame('pendiente', $historial[0]->estado_anterior);
        $this->assertSame('confirmada', $historial[0]->estado_nuevo);
    }

    /* ================= 3) EL ENDPOINT DE HISTORIAL ================= */

    public function test_el_endpoint_devuelve_los_cambios_en_orden_con_el_nombre_de_quien_actuo(): void
    {
        $idReserva = $this->crearReserva($this->negocioA, 'pendiente');

        $this->cambiarComoAdmin($idReserva, 'confirmada')->assertJsonPath('error', 0);
        $this->cambiarComoEmpleado($idReserva, 'completada')->assertJsonPath('error', 0);

        $this->flushSession();
        $historial = $this->withSession($this->sesionAdmin($this->adminA, $this->negocioA))
            ->getJson('request/reserva/historial?id_reserva='.$idReserva)
            ->assertJsonPath('error', 0)
            ->json('data.historial');

        $this->assertCount(2, $historial);
        $this->assertSame(['pendiente', 'confirmada', 'Ana Admin'], [$historial[0]['estado_anterior'], $historial[0]['estado_nuevo'], $historial[0]['nombre_usuario']]);
        $this->assertSame(['confirmada', 'completada', 'Laura Empleada'], [$historial[1]['estado_anterior'], $historial[1]['estado_nuevo'], $historial[1]['nombre_usuario']]);
    }

    /**
     * Ningún proceso de sistema cambia el estado hoy, pero el Service ya
     * acepta id_usuario null para cuando exista uno, y el historial lo
     * muestra como "Sistema".
     */
    public function test_un_cambio_de_sistema_queda_con_usuario_null_y_se_muestra_como_sistema(): void
    {
        $idReserva = $this->crearReserva($this->negocioA, 'pendiente');

        $this->assertSame(SvcReserva::CAMBIO_HECHO, (new SvcReserva)->cambiarEstado($idReserva, 'cancelada', $this->negocioA, null));

        $this->assertNull($this->historialDe($idReserva)[0]->id_usuario);
        $this->assertSame('Sistema', (new SvcReserva)->listarHistorialEstados($idReserva, $this->negocioA)[0]['nombre_usuario']);
    }

    /** El único proceso de sistema que mira reservas solo las lee: no deja historial. */
    public function test_el_comando_de_recordatorios_no_cambia_estados_ni_deja_historial(): void
    {
        $idReserva = $this->crearReserva($this->negocioA, 'confirmada', ['fecha_reserva' => now()->addDay()->toDateString()]);

        $this->artisan('reservas:enviar-recordatorios')->assertExitCode(0);

        $this->assertSame('confirmada', $this->estadoDe($idReserva));
        $this->assertSame(0, DB::table('historial_estados_reserva')->count());
    }

    /* ================= 4) COMISIÓN PAGADA: BLOQUEADO PARA TODOS =================
     * (mutación: ver la cabecera del archivo)
     */

    public function test_con_la_comision_pagada_el_admin_no_puede_cambiar_el_estado(): void
    {
        $idReserva = $this->crearReserva($this->negocioA, 'completada');
        $this->pagarComisionDe($idReserva);

        $this->cambiarComoAdmin($idReserva, 'cancelada')
            ->assertJsonPath('error', 1)
            ->assertJsonPath('mensaje', self::MENSAJE_COMISION_PAGADA);

        $this->assertSame('completada', $this->estadoDe($idReserva));
        $this->assertCount(0, $this->historialDe($idReserva));
        Mail::assertNothingQueued();
    }

    public function test_con_la_comision_pagada_el_empleado_no_puede_cambiar_el_estado(): void
    {
        $idReserva = $this->crearReserva($this->negocioA, 'completada');
        $this->pagarComisionDe($idReserva);

        $this->cambiarComoEmpleado($idReserva, 'confirmada')
            ->assertJsonPath('error', 1)
            ->assertJsonPath('mensaje', self::MENSAJE_COMISION_PAGADA);

        $this->assertSame('completada', $this->estadoDe($idReserva));
        $this->assertCount(0, $this->historialDe($idReserva));
    }

    /**
     * El super admin ya queda fuera antes de llegar al Service (sin negocio en
     * la sesión, el endpoint de reservas lo rechaza). La segunda mitad prueba
     * el candado del Service por sí solo, con el id del super admin como
     * quien actúa: tampoco hay excepción de rol ahí.
     */
    public function test_con_la_comision_pagada_el_super_admin_tampoco_puede_cambiar_el_estado(): void
    {
        $idReserva = $this->crearReserva($this->negocioA, 'completada');
        $this->pagarComisionDe($idReserva);

        $this->flushSession();
        $this->withSession($this->sesionSuperAdmin())
            ->postJson('request/reserva/cambiar-estado', ['id_reserva' => $idReserva, 'estado_reserva' => 'cancelada'])
            ->assertJsonPath('error', 1);

        $this->assertSame(
            SvcReserva::CAMBIO_COMISION_PAGADA,
            (new SvcReserva)->cambiarEstado($idReserva, 'cancelada', $this->negocioA, $this->superAdmin)
        );

        $this->assertSame('completada', $this->estadoDe($idReserva));
        $this->assertCount(0, $this->historialDe($idReserva));
    }

    /** Ni siquiera "re-guardar" el mismo estado pasa: la cita pagada queda intocable. */
    public function test_con_la_comision_pagada_tampoco_se_acepta_el_mismo_estado(): void
    {
        $idReserva = $this->crearReserva($this->negocioA, 'completada');
        $this->pagarComisionDe($idReserva);

        $this->cambiarComoEmpleado($idReserva, 'completada')->assertJsonPath('mensaje', self::MENSAJE_COMISION_PAGADA);
    }

    /* ================= 5) SIN COMISIÓN PAGADA: TODO IGUAL QUE ANTES ================= */

    public function test_sin_comision_pagada_el_cambio_funciona_igual_que_antes(): void
    {
        DB::table('clientes')->where('id_cliente', $this->clienteA)->update(['email' => 'cliente@a.test']);
        $idReserva = $this->crearReserva($this->negocioA, 'confirmada');

        $this->cambiarComoAdmin($idReserva, 'cancelada')->assertJsonPath('error', 0);

        $this->assertSame('cancelada', $this->estadoDe($idReserva));
        // El correo al cliente se sigue mandando como siempre.
        Mail::assertQueued(\App\Mail\ReservaEstadoActualizado::class, fn ($correo) => $correo->hasTo('cliente@a.test'));
    }

    /** Guardar el mismo estado no es un cambio: responde bien y no ensucia el historial. */
    public function test_guardar_el_mismo_estado_responde_bien_y_no_deja_fila(): void
    {
        $idReserva = $this->crearReserva($this->negocioA, 'confirmada');

        $this->cambiarComoAdmin($idReserva, 'confirmada')->assertJsonPath('error', 0);

        $this->assertCount(0, $this->historialDe($idReserva));
    }

    /* ================= 6) AISLAMIENTO ENTRE NEGOCIOS =================
     * (mutación: ver la cabecera del archivo)
     */

    public function test_el_historial_de_una_reserva_de_a_nunca_es_visible_desde_b(): void
    {
        $idReservaA = $this->crearReserva($this->negocioA, 'pendiente');
        $this->cambiarComoAdmin($idReservaA, 'confirmada')->assertJsonPath('error', 0);

        // El dueño lo ve...
        $this->flushSession();
        $this->withSession($this->sesionAdmin($this->adminA, $this->negocioA))
            ->getJson('request/reserva/historial?id_reserva='.$idReservaA)
            ->assertJsonCount(1, 'data.historial');

        // ...el otro negocio, pidiendo ese mismo id, no ve nada.
        $this->flushSession();
        $this->withSession($this->sesionAdmin($this->adminB, $this->negocioB))
            ->getJson('request/reserva/historial?id_reserva='.$idReservaA)
            ->assertJsonPath('error', 0)
            ->assertJsonPath('data.historial', []);
    }

    public function test_otro_negocio_no_puede_cambiar_el_estado_ni_dejar_historial_en_una_reserva_ajena(): void
    {
        $idReservaA = $this->crearReserva($this->negocioA, 'pendiente');

        $this->cambiarComoAdmin($idReservaA, 'cancelada', $this->adminB, $this->negocioB)->assertJsonPath('error', 1);

        $this->assertSame('pendiente', $this->estadoDe($idReservaA));
        $this->assertCount(0, $this->historialDe($idReservaA));
    }

    /*
     * El endpoint del empleado (cambiar-estado-mi-cita) no tenía ninguna
     * prueba antes de esta tarea. Estas tres cubren sus reglas de acceso.
     */

    public function test_un_empleado_no_puede_cambiar_la_cita_de_otro_empleado(): void
    {
        $otroEmpleado = DB::table('empleados')->insertGetId([
            'tenant_id' => $this->negocioA,
            'nombre' => 'Otro Empleado',
            'telefono' => '3001111111',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
        $idReserva = $this->crearReserva($this->negocioA, 'pendiente', ['id_empleado' => $otroEmpleado]);

        $this->cambiarComoEmpleado($idReserva, 'confirmada')->assertJsonPath('error', 1);

        $this->assertSame('pendiente', $this->estadoDe($idReserva));
        $this->assertCount(0, $this->historialDe($idReserva));
    }

    public function test_un_empleado_no_puede_cambiar_una_reserva_de_otro_negocio(): void
    {
        $idReservaB = $this->crearReserva($this->negocioB, 'pendiente');

        $this->cambiarComoEmpleado($idReservaB, 'confirmada')->assertJsonPath('error', 1);

        $this->assertSame('pendiente', $this->estadoDe($idReservaB));
        $this->assertCount(0, $this->historialDe($idReservaB));
    }

    public function test_un_empleado_no_puede_cancelar_ni_con_su_propia_cita(): void
    {
        $idReserva = $this->crearReserva($this->negocioA, 'pendiente');

        $this->cambiarComoEmpleado($idReserva, 'cancelada')->assertJsonPath('error', 1);

        $this->assertSame('pendiente', $this->estadoDe($idReserva));
    }

    public function test_un_empleado_no_puede_ver_el_historial(): void
    {
        $idReserva = $this->crearReserva($this->negocioA, 'pendiente');

        $this->withSession($this->sesionEmpleado())
            ->getJson('request/reserva/historial?id_reserva='.$idReserva)
            ->assertJsonPath('error', 1);
    }

    /* ================= 7) LA TRANSACCIÓN =================
     * (mutación: ver la cabecera del archivo)
     */

    /**
     * Se fuerza un error DESPUÉS de actualizar el estado, justo al insertar la
     * fila de historial: el estado tiene que volver atrás con ella.
     */
    public function test_si_falla_el_historial_el_cambio_de_estado_tambien_se_revierte(): void
    {
        $idReserva = $this->crearReserva($this->negocioA, 'pendiente');

        HistorialEstadoReserva::creating(function () {
            throw new \RuntimeException('Fallo forzado al registrar el historial');
        });

        $this->cambiarComoAdmin($idReserva, 'confirmada')->assertJsonPath('error', 1);

        $this->assertSame('pendiente', $this->estadoDe($idReserva), 'El estado quedó cambiado sin su historial');
        $this->assertCount(0, $this->historialDe($idReserva));
        Mail::assertNothingQueued();
    }

    /* ================= 8) APPEND-ONLY ================= */

    public function test_una_fila_del_historial_no_se_puede_modificar_ni_borrar(): void
    {
        $idReserva = $this->crearReserva($this->negocioA, 'pendiente');
        $this->cambiarComoAdmin($idReserva, 'confirmada')->assertJsonPath('error', 0);

        $fila = HistorialEstadoReserva::where('id_reserva', $idReserva)->first();

        try {
            $fila->update(['estado_nuevo' => 'cancelada']);
            $this->fail('Se pudo modificar una fila del historial');
        } catch (\LogicException) {
        }

        try {
            $fila->delete();
            $this->fail('Se pudo borrar una fila del historial');
        } catch (\LogicException) {
        }

        $this->assertSame('confirmada', $this->historialDe($idReserva)[0]->estado_nuevo);
    }
}
