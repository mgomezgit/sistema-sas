<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Mail\ReservaEstadoActualizado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Campana de "Solicitudes pendientes": listado, aislamiento multi-tenant, y el
 * endpoint de carga individual que usa el modal cuando se abre desde
 * ?reserva=ID en vez de desde una fila ya cargada en la tabla.
 *
 * Todo se ejerce por los endpoints reales con withSession() (la autenticación
 * de este proyecto usa sesión propia, no el Auth de Laravel), sin mocks de
 * capas intermedias: si la protección real se rompe, la prueba se rompe.
 *
 * ================= PRUEBAS DE MUTACIÓN DEL TENANT_ID =================
 *
 * El aislamiento se verificó rompiendo el código a propósito, en los DOS
 * puntos donde el filtro por negocio decide qué se puede leer.
 *
 * MUTACIÓN 1 — SvcReserva::listarSolicitudesPendientes().
 *   1. Se quitó ->where('r.tenant_id', $tenantId).
 *   2. php artisan test --filter=SolicitudPendienteCampanaTest
 *      Resultado: 13 tests, 11 passed, 2 FAILED:
 *        - test_aislamiento_las_solicitudes_de_otro_negocio_no_aparecen:
 *          "Failed asserting that 2 is identical to 1." (el negocio A vio la
 *          solicitud del negocio B mezclada con la suya)
 *        - test_un_tenant_id_en_la_query_se_ignora: mismo fallo — sin el WHERE,
 *          ese tenant_id de la query también se cuela.
 *   3. Se restauró la línea tal cual estaba.
 *
 * MUTACIÓN 2 — SvcReserva::listarById() (la que usa el endpoint obtener()).
 *   1. Se quitó ->where('r.tenant_id', $tenantId).
 *   2. php artisan test --filter=SolicitudPendienteCampanaTest
 *      Resultado: 13 tests, 12 passed, 1 FAILED:
 *        - test_obtener_una_reserva_de_otro_negocio_por_id_no_devuelve_nada:
 *          "El endpoint obtener() no puede devolver una reserva de otro negocio
 *           Failed asserting that Array &0 ['nombre_cliente' => 'Cliente
 *           Ajeno', ...] is null."
 *      Este es justo el endpoint que abre el modal desde ?reserva=ID: con el
 *      filtro roto, un admin del negocio A podía leer el cliente, teléfono y
 *      servicio de una reserva del negocio B adivinando su ID.
 *      Se comprobó además que ReservaTest (la suite existente del módulo)
 *      SIGUE PASANDO con este filtro roto: listarById() no tenía ninguna
 *      prueba de aislamiento propia hasta este archivo — un hueco real que
 *      esta tarea cierra, no solo una obligación por regla.
 *   3. Se restauró la línea tal cual estaba.
 *
 * Tras restaurar ambas: 13 passed. Es decir: las pruebas no pasan "por
 * casualidad" — fallan exactamente cuando el WHERE de tenant_id desaparece,
 * que es lo que deben custodiar.
 */
class SolicitudPendienteCampanaTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    private int $otroTenantId;

    private int $idCliente;

    private int $idRecurso;

    private int $idClienteAjeno;

    private int $idRecursoAjeno;

    private string $lunesFuturo;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->tenantId = $this->crearNegocio('Spa de Pruebas');
        $this->otroTenantId = $this->crearNegocio('Negocio Ajeno');

        $this->idCliente = $this->crearCliente($this->tenantId, 'Cliente Uno', '3001111111');
        $this->idRecurso = $this->crearRecurso($this->tenantId, 'Masaje', 60);

        $this->idClienteAjeno = $this->crearCliente($this->otroTenantId, 'Cliente Ajeno', '3009999999');
        $this->idRecursoAjeno = $this->crearRecurso($this->otroTenantId, 'Servicio Ajeno', 60);

        $this->lunesFuturo = date('Y-m-d', strtotime('monday next week'));
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'rubro' => 'spa',
            'dias_atencion' => '1,2,3,4,5,6,0',
            'hora_apertura' => '08:00:00',
            'hora_cierre' => '18:00:00',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearCliente(int $tenantId, string $nombre, string $telefono): int
    {
        return DB::table('clientes')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'telefono' => $telefono,
            'email' => strtolower(str_replace(' ', '.', $nombre)).'@correo.test',
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

    /** Inserta una reserva, saltándose las validaciones del endpoint de crear. */
    private function insertarReserva(int $tenantId, int $idCliente, int $idRecurso, array $sobreescribir = []): int
    {
        return DB::table('reservas')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'id_cliente' => $idCliente,
            'id_recurso' => $idRecurso,
            'id_empleado' => null,
            'fecha_reserva' => $this->lunesFuturo,
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado_reserva' => 'pendiente',
            'origen' => 'publico',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ], $sobreescribir));
    }

    private function sesionAdmin(?int $tenantId = null): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 1,
            'usuario' => 'admin.test',
            'nombre_usuario' => 'Admin Test',
            'tenant_id' => $tenantId ?? $this->tenantId,
            'id_rol' => 1,
        ];
    }

    private function sesionEmpleado(?int $tenantId = null): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 2,
            'usuario' => 'empleado.test',
            'nombre_usuario' => 'Empleado Test',
            'tenant_id' => $tenantId ?? $this->tenantId,
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

    /* ================= 1) FILTRO ESTADO + ORIGEN ================= */

    public function test_solo_trae_pendientes_de_origen_publico(): void
    {
        // La que sí debe aparecer.
        $this->insertarReserva($this->tenantId, $this->idCliente, $this->idRecurso, [
            'origen' => 'publico',
            'estado_reserva' => 'pendiente',
        ]);

        // Pendiente, pero creada desde el backoffice: no es una "solicitud".
        $this->insertarReserva($this->tenantId, $this->idCliente, $this->idRecurso, [
            'origen' => 'admin',
            'estado_reserva' => 'pendiente',
        ]);

        // Del público, pero ya confirmada: ya no está pendiente de revisar.
        $this->insertarReserva($this->tenantId, $this->idCliente, $this->idRecurso, [
            'origen' => 'publico',
            'estado_reserva' => 'confirmada',
        ]);

        $respuesta = $this->withSession($this->sesionAdmin())->getJson('request/reserva/solicitudes-pendientes');

        $respuesta->assertStatus(200);
        $this->assertSame(0, $respuesta->json('error'));
        $this->assertSame(1, $respuesta->json('data.total'));
        $this->assertCount(1, $respuesta->json('data.solicitudes'));
    }

    public function test_responde_vacio_cuando_no_hay_solicitudes(): void
    {
        $respuesta = $this->withSession($this->sesionAdmin())->getJson('request/reserva/solicitudes-pendientes');

        $this->assertSame(0, $respuesta->json('data.total'));
        $this->assertSame([], $respuesta->json('data.solicitudes'));
    }

    public function test_ordena_por_fecha_y_hora_ascendente(): void
    {
        $this->insertarReserva($this->tenantId, $this->idCliente, $this->idRecurso, [
            'fecha_reserva' => $this->lunesFuturo,
            'hora_inicio' => '15:00:00',
        ]);
        $this->insertarReserva($this->tenantId, $this->idCliente, $this->idRecurso, [
            'fecha_reserva' => $this->lunesFuturo,
            'hora_inicio' => '09:00:00',
        ]);

        $respuesta = $this->withSession($this->sesionAdmin())->getJson('request/reserva/solicitudes-pendientes');
        $horas = array_column($respuesta->json('data.solicitudes'), 'hora_inicio');

        $this->assertSame(['09:00:00', '15:00:00'], $horas);
    }

    public function test_trae_cliente_telefono_servicio_fecha_y_hora(): void
    {
        $this->insertarReserva($this->tenantId, $this->idCliente, $this->idRecurso);

        $solicitud = $this->withSession($this->sesionAdmin())
            ->getJson('request/reserva/solicitudes-pendientes')
            ->json('data.solicitudes.0');

        $this->assertSame('Cliente Uno', $solicitud['nombre_cliente']);
        $this->assertSame('3001111111', $solicitud['telefono_cliente']);
        $this->assertSame('Masaje', $solicitud['nombre_servicio']);
        $this->assertSame($this->lunesFuturo, $solicitud['fecha_reserva']);
        $this->assertSame('10:00:00', $solicitud['hora_inicio']);
    }

    /* ================= 2) AISLAMIENTO MULTI-TENANT ================= */

    /** Ver la nota de MUTACIÓN 1 en el encabezado de la clase. */
    public function test_aislamiento_las_solicitudes_de_otro_negocio_no_aparecen(): void
    {
        $this->insertarReserva($this->tenantId, $this->idCliente, $this->idRecurso);
        $this->insertarReserva($this->otroTenantId, $this->idClienteAjeno, $this->idRecursoAjeno);

        $respuesta = $this->withSession($this->sesionAdmin())->getJson('request/reserva/solicitudes-pendientes');

        $this->assertSame(1, $respuesta->json('data.total'));

        $nombres = array_column($respuesta->json('data.solicitudes'), 'nombre_cliente');
        $this->assertNotContains('Cliente Ajeno', $nombres, 'El negocio A no debe ver clientes del negocio B');

        $telefonos = array_column($respuesta->json('data.solicitudes'), 'telefono_cliente');
        $this->assertNotContains('3009999999', $telefonos);
    }

    public function test_un_tenant_id_en_la_query_se_ignora(): void
    {
        $this->insertarReserva($this->tenantId, $this->idCliente, $this->idRecurso);
        $this->insertarReserva($this->otroTenantId, $this->idClienteAjeno, $this->idRecursoAjeno);

        // El admin de A manda el tenant_id de B en la query string: debe seguir
        // viendo solo lo suyo, porque el Controller usa session('tenant_id'), no
        // lo que venga en la petición.
        $respuesta = $this->withSession($this->sesionAdmin())
            ->getJson('request/reserva/solicitudes-pendientes?tenant_id='.$this->otroTenantId);

        $this->assertSame(1, $respuesta->json('data.total'));
        $this->assertSame('Cliente Uno', $respuesta->json('data.solicitudes.0.nombre_cliente'));
    }

    public function test_el_endpoint_rechaza_a_empleado(): void
    {
        $respuesta = $this->withSession($this->sesionEmpleado())->getJson('request/reserva/solicitudes-pendientes');

        $this->assertSame(1, $respuesta->json('error'));
    }

    public function test_el_endpoint_rechaza_a_super_admin(): void
    {
        $respuesta = $this->withSession($this->sesionSuperAdmin())->getJson('request/reserva/solicitudes-pendientes');

        $this->assertSame(1, $respuesta->json('error'));
    }

    /* ================= 3) ENDPOINT obtener() — carga por ?reserva=ID ================= */

    public function test_obtener_devuelve_la_reserva_propia(): void
    {
        $idReserva = $this->insertarReserva($this->tenantId, $this->idCliente, $this->idRecurso);

        $respuesta = $this->withSession($this->sesionAdmin())
            ->getJson('request/reserva/obtener?id_reserva='.$idReserva);

        $this->assertSame(0, $respuesta->json('error'));
        $this->assertSame($idReserva, $respuesta->json('data.reserva.id_reserva'));
        $this->assertSame('Cliente Uno', $respuesta->json('data.reserva.nombre_cliente'));
    }

    /** Ver la nota de MUTACIÓN 2 en el encabezado de la clase. */
    public function test_obtener_una_reserva_de_otro_negocio_por_id_no_devuelve_nada(): void
    {
        $idReservaAjena = $this->insertarReserva($this->otroTenantId, $this->idClienteAjeno, $this->idRecursoAjeno);

        $respuesta = $this->withSession($this->sesionAdmin())
            ->getJson('request/reserva/obtener?id_reserva='.$idReservaAjena);

        $this->assertSame(0, $respuesta->json('error'), 'No debe reportarse como error, solo como "no encontrada"');
        $this->assertNull(
            $respuesta->json('data.reserva'),
            'El endpoint obtener() no puede devolver una reserva de otro negocio'
        );
    }

    public function test_obtener_un_id_inexistente_no_rompe_y_devuelve_null(): void
    {
        $respuesta = $this->withSession($this->sesionAdmin())
            ->getJson('request/reserva/obtener?id_reserva=999999');

        $respuesta->assertStatus(200);
        $this->assertSame(0, $respuesta->json('error'));
        $this->assertNull($respuesta->json('data.reserva'));
    }

    public function test_obtener_rechaza_a_empleado(): void
    {
        $idReserva = $this->insertarReserva($this->tenantId, $this->idCliente, $this->idRecurso);

        $respuesta = $this->withSession($this->sesionEmpleado())
            ->getJson('request/reserva/obtener?id_reserva='.$idReserva);

        $this->assertSame(1, $respuesta->json('error'));
    }

    /* ================= 4) CONFIRMAR SACA DE LA LISTA Y NOTIFICA ================= */

    public function test_confirmar_con_empleado_saca_la_solicitud_y_encola_un_correo(): void
    {
        Mail::fake();

        $idEmpleado = $this->crearEmpleado($this->tenantId, 'Empleada Uno');
        $idReserva = $this->insertarReserva($this->tenantId, $this->idCliente, $this->idRecurso);

        $respuestaEditar = $this->withSession($this->sesionAdmin())->postJson('request/reserva/editar', [
            'id_reserva' => $idReserva,
            'id_cliente' => $this->idCliente,
            'id_recurso' => $this->idRecurso,
            'id_empleado' => $idEmpleado,
            'fecha_reserva' => $this->lunesFuturo,
            'hora_inicio' => '10:00',
        ]);
        $this->assertSame(0, $respuestaEditar->json('error'));

        $respuestaEstado = $this->withSession($this->sesionAdmin())->postJson('request/reserva/cambiar-estado', [
            'id_reserva' => $idReserva,
            'estado_reserva' => 'confirmada',
        ]);
        $this->assertSame(0, $respuestaEstado->json('error'));

        $respuestaCampana = $this->withSession($this->sesionAdmin())->getJson('request/reserva/solicitudes-pendientes');
        $this->assertSame(0, $respuestaCampana->json('data.total'), 'Una solicitud confirmada ya no debe listarse como pendiente');

        Mail::assertQueued(ReservaEstadoActualizado::class, function (ReservaEstadoActualizado $mail) {
            return $mail->hasTo('cliente.uno@correo.test');
        });
        Mail::assertQueuedCount(1);
    }
}
