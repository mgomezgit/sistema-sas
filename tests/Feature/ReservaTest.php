<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pruebas del módulo de Reservas contra los endpoints reales del
 * ReservaController.
 *
 * Corren sobre la base SQLite en memoria que define phpunit.xml, así que la
 * base de datos real del proyecto no se toca en ningún momento.
 *
 * La autenticación de este proyecto no usa el Auth de Laravel sino la sesión
 * propia, por eso cada petición se emite con withSession() en vez de actingAs().
 */
class ReservaTest extends TestCase
{
    use RefreshDatabase;

    /** Negocio principal sobre el que se hacen casi todas las pruebas. */
    private int $tenantId;

    /** Segundo negocio, para comprobar el aislamiento entre inquilinos. */
    private int $otroTenantId;

    private int $idCliente;

    private int $idRecurso;

    /** Empleada "Aura", usada en los casos de solapamiento. */
    private int $idAura;

    /** Empleada "Lorena", para probar que dos empleados sí pueden coincidir. */
    private int $idLorena;

    private int $idClienteAjeno;

    private int $idRecursoAjeno;

    private int $idEmpleadoAjeno;

    /** Lunes futuro: día hábil y posterior a hoy en cualquier ejecución. */
    private string $lunesFuturo;

    /** Lunes ya pasado, para los casos de fechas anteriores a hoy. */
    private string $lunesPasado;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'administrador', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        // Negocio de lunes a viernes, de 08:00 a 18:00.
        $this->tenantId = $this->crearNegocio('Spa de Pruebas', '1,2,3,4,5', '08:00:00', '18:00:00');
        $this->otroTenantId = $this->crearNegocio('Negocio Ajeno', '1,2,3,4,5', '08:00:00', '18:00:00');

        $this->idCliente = $this->crearCliente($this->tenantId, 'Cliente Uno');
        // 60 minutos exactos: simplifica comprobar los cálculos de hora_fin.
        $this->idRecurso = $this->crearRecurso($this->tenantId, 'Masaje', 60);
        $this->idAura = $this->crearEmpleado($this->tenantId, 'Aura');
        $this->idLorena = $this->crearEmpleado($this->tenantId, 'Lorena');

        $this->idClienteAjeno = $this->crearCliente($this->otroTenantId, 'Cliente Ajeno');
        $this->idRecursoAjeno = $this->crearRecurso($this->otroTenantId, 'Servicio Ajeno', 60);
        $this->idEmpleadoAjeno = $this->crearEmpleado($this->otroTenantId, 'Empleado Ajeno');

        $this->lunesFuturo = date('Y-m-d', strtotime('monday next week'));
        $this->lunesPasado = date('Y-m-d', strtotime('monday last week'));
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre, ?string $dias, ?string $apertura, ?string $cierre): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'rubro' => 'spa',
            'dias_atencion' => $dias,
            'hora_apertura' => $apertura,
            'hora_cierre' => $cierre,
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

    /**
     * Sesión de un administrador del negocio principal, tal como la arma el
     * login real (incluida la clave que valida el middleware de sesión).
     */
    private function sesionAdministrador(?int $tenantId = null): array
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

    /** POST a un endpoint del módulo con la sesión de administrador puesta. */
    private function postComoAdmin(string $url, array $datos, ?array $sesion = null)
    {
        return $this->withSession($sesion ?? $this->sesionAdministrador())->postJson($url, $datos);
    }

    /** Datos base de una reserva válida; cada test cambia lo que necesita. */
    private function datosReserva(array $sobreescribir = []): array
    {
        return array_merge([
            'id_cliente' => $this->idCliente,
            'id_recurso' => $this->idRecurso,
            'fecha_reserva' => $this->lunesFuturo,
            'hora_inicio' => '10:00',
        ], $sobreescribir);
    }

    /** Inserta una reserva directamente, saltándose las validaciones. */
    private function insertarReserva(array $sobreescribir = []): int
    {
        return DB::table('reservas')->insertGetId(array_merge([
            'tenant_id' => $this->tenantId,
            'id_cliente' => $this->idCliente,
            'id_recurso' => $this->idRecurso,
            'id_empleado' => null,
            'fecha_reserva' => $this->lunesFuturo,
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado_reserva' => 'pendiente',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ], $sobreescribir));
    }

    /* ================= SOLAPAMIENTO DE HORARIOS ================= */

    /**
     * Dos citas de la MISMA empleada que se pisan en el tiempo (10:00-11:00 y
     * 10:30-11:30) no pueden coexistir: la segunda debe rechazarse.
     */
    public function test_no_permite_dos_reservas_del_mismo_empleado_en_horario_solapado(): void
    {
        $primera = $this->postComoAdmin('request/reserva/crear', $this->datosReserva([
            'id_empleado' => $this->idAura,
            'hora_inicio' => '10:00',
        ]));

        $primera->assertJsonPath('error', 0);

        $segunda = $this->postComoAdmin('request/reserva/crear', $this->datosReserva([
            'id_empleado' => $this->idAura,
            'hora_inicio' => '10:30',
        ]));

        $segunda->assertJsonPath('error', 1);
        $this->assertStringContainsString('ya tiene una reserva en ese horario', $segunda->json('mensaje'));

        // Solo debe haber quedado guardada la primera.
        $this->assertSame(1, DB::table('reservas')->where('id_empleado', $this->idAura)->count());
    }

    /**
     * El choque es por empleado, no por negocio: Aura y Lorena pueden atender
     * a la misma hora sin estorbarse.
     */
    public function test_permite_reservas_de_empleados_distintos_en_el_mismo_horario(): void
    {
        $deAura = $this->postComoAdmin('request/reserva/crear', $this->datosReserva([
            'id_empleado' => $this->idAura,
            'hora_inicio' => '10:00',
        ]));

        $deLorena = $this->postComoAdmin('request/reserva/crear', $this->datosReserva([
            'id_empleado' => $this->idLorena,
            'hora_inicio' => '10:00',
        ]));

        $deAura->assertJsonPath('error', 0);
        $deLorena->assertJsonPath('error', 0);

        $this->assertSame(2, DB::table('reservas')->count());
    }

    /**
     * Citas pegadas pero sin superponerse (10:00-11:00 y 11:00-12:00) son
     * válidas: el fin de una coincide exactamente con el inicio de la otra.
     */
    public function test_permite_reservas_consecutivas_sin_solape_del_mismo_empleado(): void
    {
        $primera = $this->postComoAdmin('request/reserva/crear', $this->datosReserva([
            'id_empleado' => $this->idAura,
            'hora_inicio' => '10:00',
        ]));

        $segunda = $this->postComoAdmin('request/reserva/crear', $this->datosReserva([
            'id_empleado' => $this->idAura,
            'hora_inicio' => '11:00',
        ]));

        $primera->assertJsonPath('error', 0);
        $segunda->assertJsonPath('error', 0);

        $this->assertSame(2, DB::table('reservas')->where('id_empleado', $this->idAura)->count());
    }

    /* ================= HORARIO DE ATENCIÓN ================= */

    /**
     * El negocio atiende de lunes a viernes: un sábado debe rechazarse.
     */
    public function test_rechaza_reserva_en_dia_que_el_negocio_no_atiende(): void
    {
        $sabadoFuturo = date('Y-m-d', strtotime($this->lunesFuturo.' +5 days'));

        $respuesta = $this->postComoAdmin('request/reserva/crear', $this->datosReserva([
            'fecha_reserva' => $sabadoFuturo,
        ]));

        $respuesta->assertJsonPath('error', 1);
        $respuesta->assertJsonPath('mensaje', 'El negocio no atiende en la fecha u horario seleccionados');
        $this->assertSame(0, DB::table('reservas')->count());
    }

    /**
     * Dos casos fuera de la jornada 08:00-18:00: empezar antes de abrir, y
     * terminar después de cerrar (17:30 + 60 min = 18:30).
     */
    public function test_rechaza_reserva_fuera_del_horario_de_atencion(): void
    {
        $antesDeAbrir = $this->postComoAdmin('request/reserva/crear', $this->datosReserva([
            'hora_inicio' => '07:00',
        ]));

        $antesDeAbrir->assertJsonPath('error', 1);
        $antesDeAbrir->assertJsonPath('mensaje', 'El negocio no atiende en la fecha u horario seleccionados');

        $terminaDespuesDeCerrar = $this->postComoAdmin('request/reserva/crear', $this->datosReserva([
            'hora_inicio' => '17:30',
        ]));

        $terminaDespuesDeCerrar->assertJsonPath('error', 1);
        $terminaDespuesDeCerrar->assertJsonPath('mensaje', 'El negocio no atiende en la fecha u horario seleccionados');

        $this->assertSame(0, DB::table('reservas')->count());
    }

    /* ================= FECHAS PASADAS ================= */

    /**
     * No se puede agendar hacia atrás en el tiempo.
     */
    public function test_rechaza_reserva_en_fecha_pasada(): void
    {
        $respuesta = $this->postComoAdmin('request/reserva/crear', $this->datosReserva([
            'fecha_reserva' => $this->lunesPasado,
        ]));

        $respuesta->assertJsonPath('error', 1);
        $respuesta->assertJsonPath('mensaje', 'No es posible crear reservas en fechas pasadas');
        $this->assertSame(0, DB::table('reservas')->count());
    }

    /**
     * Una cita que ya ocurrió sí se puede corregir (por ejemplo, sus notas)
     * mientras no se mueva de día.
     */
    public function test_permite_editar_reserva_pasada_sin_cambiar_su_fecha(): void
    {
        $idReserva = $this->insertarReserva([
            'fecha_reserva' => $this->lunesPasado,
            'estado_reserva' => 'completada',
        ]);

        $respuesta = $this->postComoAdmin('request/reserva/editar', $this->datosReserva([
            'id_reserva' => $idReserva,
            'fecha_reserva' => $this->lunesPasado,
            'notas' => 'Nota corregida despues de la cita',
        ]));

        $respuesta->assertJsonPath('error', 0);
        $this->assertSame(
            'Nota corregida despues de la cita',
            DB::table('reservas')->where('id_reserva', $idReserva)->value('notas')
        );
    }

    /**
     * Pero moverla a otro día que también está en el pasado sí se bloquea.
     */
    public function test_rechaza_mover_reserva_pasada_a_otra_fecha_pasada(): void
    {
        $idReserva = $this->insertarReserva([
            'fecha_reserva' => $this->lunesPasado,
            'estado_reserva' => 'completada',
        ]);

        $otraFechaPasada = date('Y-m-d', strtotime($this->lunesPasado.' +1 day'));

        $respuesta = $this->postComoAdmin('request/reserva/editar', $this->datosReserva([
            'id_reserva' => $idReserva,
            'fecha_reserva' => $otraFechaPasada,
        ]));

        $respuesta->assertJsonPath('error', 1);
        $respuesta->assertJsonPath('mensaje', 'No es posible crear reservas en fechas pasadas');

        // La reserva debe seguir en su fecha original.
        $this->assertSame(
            $this->lunesPasado,
            DB::table('reservas')->where('id_reserva', $idReserva)->value('fecha_reserva')
        );
    }

    /* ================= AISLAMIENTO ENTRE NEGOCIOS ================= */

    /**
     * Un cliente que pertenece a otro negocio no es utilizable, aunque el id
     * exista en la tabla.
     */
    public function test_rechaza_id_cliente_de_otro_tenant(): void
    {
        $respuesta = $this->postComoAdmin('request/reserva/crear', $this->datosReserva([
            'id_cliente' => $this->idClienteAjeno,
        ]));

        $respuesta->assertJsonPath('error', 1);
        $respuesta->assertJsonPath('mensaje', 'El cliente seleccionado no es válido');
        $this->assertSame(0, DB::table('reservas')->count());
    }

    /** Lo mismo para un servicio de otro negocio. */
    public function test_rechaza_id_recurso_de_otro_tenant(): void
    {
        $respuesta = $this->postComoAdmin('request/reserva/crear', $this->datosReserva([
            'id_recurso' => $this->idRecursoAjeno,
        ]));

        $respuesta->assertJsonPath('error', 1);
        $respuesta->assertJsonPath('mensaje', 'El recurso seleccionado no es válido');
        $this->assertSame(0, DB::table('reservas')->count());
    }

    /** Y para un empleado de otro negocio. */
    public function test_rechaza_id_empleado_de_otro_tenant(): void
    {
        $respuesta = $this->postComoAdmin('request/reserva/crear', $this->datosReserva([
            'id_empleado' => $this->idEmpleadoAjeno,
        ]));

        $respuesta->assertJsonPath('error', 1);
        $respuesta->assertJsonPath('mensaje', 'El empleado seleccionado no es válido');
        $this->assertSame(0, DB::table('reservas')->count());
    }

    /**
     * El super admin no pertenece a ningún negocio (tenant_id null), así que
     * no tiene una agenda sobre la cual operar.
     */
    public function test_super_admin_no_puede_gestionar_reservas(): void
    {
        $sesionSuperAdmin = [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 99,
            'usuario' => 'superadmin.test',
            'nombre_usuario' => 'Super Admin',
            'tenant_id' => null,
            'id_rol' => 3,
        ];

        $respuesta = $this->postComoAdmin('request/reserva/crear', $this->datosReserva(), $sesionSuperAdmin);

        $respuesta->assertJsonPath('error', 1);
        $this->assertStringContainsString('cuenta de cada negocio', $respuesta->json('mensaje'));
        $this->assertSame(0, DB::table('reservas')->count());
    }

    /* ================= CÁLCULO DE HORA FIN ================= */

    /**
     * La hora de fin no la manda el formulario: la calcula el backend sumando
     * la duración del servicio a la hora de inicio.
     */
    public function test_calcula_correctamente_hora_fin_segun_duracion_del_recurso(): void
    {
        $idRecursoDe90 = $this->crearRecurso($this->tenantId, 'Ritual largo', 90);

        $respuesta = $this->postComoAdmin('request/reserva/crear', $this->datosReserva([
            'id_recurso' => $idRecursoDe90,
            'hora_inicio' => '09:15',
        ]));

        $respuesta->assertJsonPath('error', 0);

        $reserva = DB::table('reservas')->where('id_reserva', $respuesta->json('data.id_reserva'))->first();

        // 09:15 + 90 min = 10:45. La hora de inicio se compara recortada porque
        // SQLite (la base de las pruebas) guarda el texto tal cual llega del
        // formulario, mientras que MySQL normaliza la columna TIME a H:i:s.
        $this->assertSame('09:15', substr($reserva->hora_inicio, 0, 5));
        $this->assertSame('10:45:00', $reserva->hora_fin);
    }
}
