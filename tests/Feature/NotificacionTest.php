<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Mail\ReservaConfirmada;
use App\Mail\ReservaEstadoActualizado;
use App\Mail\ReservaRecordatorio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Notificaciones por correo del módulo de Reservas.
 *
 * Usa Mail::fake() para comprobar que se encola el Mailable correcto (clase,
 * destinatario y datos de la reserva), nunca un mock de una capa intermedia:
 * las reservas y clientes se crean/editan por los endpoints reales, así que si
 * la lógica de negocio real se rompe (por ejemplo, deja de resolver el email
 * del cliente) la prueba también se rompe.
 */
class NotificacionTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    private int $otroTenantId;

    private int $idRecurso;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->tenantId = $this->crearNegocio('Spa de Pruebas');
        $this->otroTenantId = $this->crearNegocio('Otro Negocio');

        // 60 minutos exactos: simplifica comprobar hora_fin en los correos.
        $this->idRecurso = $this->crearRecurso($this->tenantId, 'Masaje', 60);
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'rubro' => 'spa',
            'dias_atencion' => '1,2,3,4,5',
            'hora_apertura' => '08:00:00',
            'hora_cierre' => '18:00:00',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearCliente(int $tenantId, string $nombre, ?string $email): int
    {
        return DB::table('clientes')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'telefono' => '3000000000',
            'email' => $email,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearRecurso(int $tenantId, string $nombre, int $duracion): int
    {
        return DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'duracion_minutos' => $duracion,
            'precio' => 50000,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    /** Inserta una reserva directamente, saltándose las validaciones del endpoint. */
    private function insertarReserva(int $tenantId, int $idCliente, int $idRecurso, array $sobreescribir = []): int
    {
        return DB::table('reservas')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'id_cliente' => $idCliente,
            'id_recurso' => $idRecurso,
            'id_empleado' => null,
            'fecha_reserva' => date('Y-m-d', strtotime('monday next week')),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado_reserva' => 'pendiente',
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

    /* ================= 1) CORREO AL CREAR RESERVA ================= */

    public function test_crear_reserva_encola_reserva_confirmada_al_cliente_correcto_con_los_datos_correctos(): void
    {
        Mail::fake();

        $idCliente = $this->crearCliente($this->tenantId, 'Cliente Con Email', 'cliente@test.local');
        $lunesFuturo = date('Y-m-d', strtotime('monday next week'));

        $respuesta = $this->withSession($this->sesionAdmin($this->tenantId))
            ->postJson('request/reserva/crear', [
                'id_cliente' => $idCliente,
                'id_recurso' => $this->idRecurso,
                'fecha_reserva' => $lunesFuturo,
                'hora_inicio' => '10:00',
            ]);

        $respuesta->assertJsonPath('error', 0);

        Mail::assertQueued(ReservaConfirmada::class, function (ReservaConfirmada $mail) use ($lunesFuturo) {
            return $mail->hasTo('cliente@test.local')
                && $mail->nombreNegocio === 'Spa de Pruebas'
                && $mail->reserva['nombre_cliente'] === 'Cliente Con Email'
                && $mail->reserva['nombre_recurso'] === 'Masaje'
                && $mail->reserva['fecha_reserva'] === $lunesFuturo
                && substr($mail->reserva['hora_inicio'], 0, 5) === '10:00'
                && $mail->reserva['hora_fin'] === '11:00:00';
        });

        Mail::assertQueuedCount(1);
    }

    public function test_crear_reserva_no_envia_correo_si_el_cliente_no_tiene_email(): void
    {
        Mail::fake();

        $idCliente = $this->crearCliente($this->tenantId, 'Cliente Sin Email', null);

        $respuesta = $this->withSession($this->sesionAdmin($this->tenantId))
            ->postJson('request/reserva/crear', [
                'id_cliente' => $idCliente,
                'id_recurso' => $this->idRecurso,
                'fecha_reserva' => date('Y-m-d', strtotime('monday next week')),
                'hora_inicio' => '10:00',
            ]);

        $respuesta->assertJsonPath('error', 0);

        Mail::assertNothingQueued();
    }

    /* ================= 2) CORREO AL CAMBIAR ESTADO ================= */

    public function test_cambiar_estado_a_confirmada_encola_reserva_estado_actualizado_con_el_estado_correcto(): void
    {
        Mail::fake();

        $idCliente = $this->crearCliente($this->tenantId, 'Cliente Con Email', 'cliente@test.local');
        $idReserva = $this->insertarReserva($this->tenantId, $idCliente, $this->idRecurso, [
            'estado_reserva' => 'pendiente',
        ]);

        $respuesta = $this->withSession($this->sesionAdmin($this->tenantId))
            ->postJson('request/reserva/cambiar-estado', [
                'id_reserva' => $idReserva,
                'estado_reserva' => 'confirmada',
            ]);

        $respuesta->assertJsonPath('error', 0);

        Mail::assertQueued(ReservaEstadoActualizado::class, function (ReservaEstadoActualizado $mail) {
            return $mail->hasTo('cliente@test.local')
                && $mail->estadoReserva === 'confirmada'
                && $mail->nombreNegocio === 'Spa de Pruebas'
                && $mail->reserva['nombre_cliente'] === 'Cliente Con Email';
        });
    }

    /**
     * "pendiente" es el estado inicial de toda reserva: pasar a él (o quedarse
     * en él) no amerita avisar al cliente, según la regla explícita del
     * controller.
     */
    public function test_cambiar_estado_a_pendiente_no_envia_correo(): void
    {
        Mail::fake();

        $idCliente = $this->crearCliente($this->tenantId, 'Cliente Con Email', 'cliente@test.local');
        $idReserva = $this->insertarReserva($this->tenantId, $idCliente, $this->idRecurso, [
            'estado_reserva' => 'confirmada',
        ]);

        $respuesta = $this->withSession($this->sesionAdmin($this->tenantId))
            ->postJson('request/reserva/cambiar-estado', [
                'id_reserva' => $idReserva,
                'estado_reserva' => 'pendiente',
            ]);

        $respuesta->assertJsonPath('error', 0);

        Mail::assertNothingQueued();
    }

    public function test_cambiar_estado_a_cancelada_tambien_notifica_al_cliente(): void
    {
        Mail::fake();

        $idCliente = $this->crearCliente($this->tenantId, 'Cliente Con Email', 'cliente@test.local');
        $idReserva = $this->insertarReserva($this->tenantId, $idCliente, $this->idRecurso, [
            'estado_reserva' => 'confirmada',
        ]);

        $this->withSession($this->sesionAdmin($this->tenantId))
            ->postJson('request/reserva/cambiar-estado', [
                'id_reserva' => $idReserva,
                'estado_reserva' => 'cancelada',
            ]);

        Mail::assertQueued(ReservaEstadoActualizado::class, function (ReservaEstadoActualizado $mail) {
            return $mail->estadoReserva === 'cancelada' && $mail->hasTo('cliente@test.local');
        });
    }

    /* ================= 3) COMANDO DE RECORDATORIOS ================= */

    public function test_comando_recordatorios_encola_reserva_recordatorio_para_reservas_de_manana_con_email(): void
    {
        Mail::fake();

        $manana = now()->addDay()->format('Y-m-d');

        $idCliente = $this->crearCliente($this->tenantId, 'Cliente Manana', 'manana@test.local');
        $this->insertarReserva($this->tenantId, $idCliente, $this->idRecurso, [
            'fecha_reserva' => $manana,
            'estado_reserva' => 'confirmada',
        ]);

        $this->artisan('reservas:enviar-recordatorios')->assertExitCode(0);

        Mail::assertQueued(ReservaRecordatorio::class, function (ReservaRecordatorio $mail) use ($manana) {
            return $mail->hasTo('manana@test.local')
                && $mail->nombreNegocio === 'Spa de Pruebas'
                && $mail->reserva['fecha_reserva'] === $manana
                && $mail->reserva['nombre_cliente'] === 'Cliente Manana';
        });
    }

    public function test_comando_recordatorios_omite_en_silencio_reservas_sin_email_de_cliente(): void
    {
        Mail::fake();

        $manana = now()->addDay()->format('Y-m-d');

        $idCliente = $this->crearCliente($this->tenantId, 'Cliente Sin Email', null);
        $this->insertarReserva($this->tenantId, $idCliente, $this->idRecurso, [
            'fecha_reserva' => $manana,
            'estado_reserva' => 'pendiente',
        ]);

        $this->artisan('reservas:enviar-recordatorios')->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    /**
     * El comando solo mira las reservas de MAÑANA: ni las de hoy, ni las de
     * pasado mañana, deben generar un correo.
     */
    public function test_comando_recordatorios_no_incluye_reservas_de_otro_dia(): void
    {
        Mail::fake();

        $idCliente = $this->crearCliente($this->tenantId, 'Cliente Hoy', 'hoy@test.local');
        $this->insertarReserva($this->tenantId, $idCliente, $this->idRecurso, [
            'fecha_reserva' => date('Y-m-d'),
            'estado_reserva' => 'confirmada',
        ]);

        $idClienteDospasado = $this->crearCliente($this->tenantId, 'Cliente Pasado Manana', 'pasado@test.local');
        $this->insertarReserva($this->tenantId, $idClienteDospasado, $this->idRecurso, [
            'fecha_reserva' => now()->addDays(2)->format('Y-m-d'),
            'estado_reserva' => 'confirmada',
        ]);

        $this->artisan('reservas:enviar-recordatorios')->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    /** Las canceladas de mañana tampoco deben recordarse. */
    public function test_comando_recordatorios_no_incluye_reservas_canceladas(): void
    {
        Mail::fake();

        $manana = now()->addDay()->format('Y-m-d');

        $idCliente = $this->crearCliente($this->tenantId, 'Cliente Cancelado', 'cancelado@test.local');
        $this->insertarReserva($this->tenantId, $idCliente, $this->idRecurso, [
            'fecha_reserva' => $manana,
            'estado_reserva' => 'cancelada',
        ]);

        $this->artisan('reservas:enviar-recordatorios')->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    /**
     * El comando corre a nivel de sistema (no de un tenant), así que debe
     * segmentar correctamente entre negocios: cada cliente recibe el correo
     * con el nombre de SU negocio, y solo el suyo.
     */
    public function test_comando_recordatorios_segmenta_correctamente_entre_negocios(): void
    {
        Mail::fake();

        $manana = now()->addDay()->format('Y-m-d');
        $idRecursoOtro = $this->crearRecurso($this->otroTenantId, 'Corte', 30);

        $idClienteA = $this->crearCliente($this->tenantId, 'Cliente A', 'a@test.local');
        $this->insertarReserva($this->tenantId, $idClienteA, $this->idRecurso, [
            'fecha_reserva' => $manana,
            'estado_reserva' => 'confirmada',
        ]);

        $idClienteB = $this->crearCliente($this->otroTenantId, 'Cliente B', 'b@test.local');
        $this->insertarReserva($this->otroTenantId, $idClienteB, $idRecursoOtro, [
            'fecha_reserva' => $manana,
            'estado_reserva' => 'pendiente',
        ]);

        $this->artisan('reservas:enviar-recordatorios')->assertExitCode(0);

        Mail::assertQueuedCount(2);

        Mail::assertQueued(ReservaRecordatorio::class, function (ReservaRecordatorio $mail) {
            return $mail->hasTo('a@test.local') && $mail->nombreNegocio === 'Spa de Pruebas';
        });

        Mail::assertQueued(ReservaRecordatorio::class, function (ReservaRecordatorio $mail) {
            return $mail->hasTo('b@test.local') && $mail->nombreNegocio === 'Otro Negocio';
        });
    }
}
