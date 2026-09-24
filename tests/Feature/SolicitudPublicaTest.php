<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Mail\NuevaSolicitudPublica;
use App\Mail\ReservaConfirmada;
use App\Service\SvcReserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Solicitudes de cita pedidas desde la página pública.
 *
 * Lo que más se vigila aquí: que el cliente NO reciba un correo diciéndole que
 * su reserva está confirmada, porque no lo está. Una solicitud pública es una
 * petición pendiente de que el negocio la revise, y prometerle lo contrario al
 * cliente es peor que no avisarle de nada.
 */
class SolicitudPublicaTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    private int $recursoA;

    private int $recursoB;

    private SvcReserva $svcReserva;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->svcReserva = new SvcReserva;

        $this->negocioA = $this->crearNegocio('Spa Fashion', 'spa-fashion');
        $this->negocioB = $this->crearNegocio('Casa Canela', 'casa-canela');

        $this->recursoA = $this->crearRecurso($this->negocioA, 'Masaje del A');
        $this->recursoB = $this->crearRecurso($this->negocioB, 'Facial del B');
    }

    private function crearNegocio(string $nombre, string $slug): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'slug' => $slug,
            'rubro' => 'spa',
            'telefono_contacto' => '3001234567',
            // Atiende los siete días, de 6 a 22: así la vigencia del horario no
            // hace fallar pruebas que no van de eso.
            'dias_atencion' => '1,2,3,4,5,6,7',
            'hora_apertura' => '06:00:00',
            'hora_cierre' => '22:00:00',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearRecurso(int $tenantId, string $nombre, int $estado = 1): int
    {
        return DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $tenantId,
            'categoria' => 'Masajes',
            'nombre' => $nombre,
            'duracion_minutos' => 60,
            'precio' => 100000,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => $estado,
        ]);
    }

    private function crearAdmin(int $tenantId, string $email): int
    {
        $idRol = DB::table('roles')->where('nombre_rol', 'admin')->value('id_rol');

        return DB::table('usuarios')->insertGetId([
            'tenant_id' => $tenantId,
            'id_rol' => $idRol,
            'usuario' => 'admin'.$tenantId,
            'nombre' => 'Admin del negocio',
            'email' => $email,
            'clave' => bcrypt('clave12345'),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearCliente(int $tenantId, string $nombre, string $telefono, ?string $email = null): int
    {
        return DB::table('clientes')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'telefono' => $telefono,
            'email' => $email,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function manana(): string
    {
        return now()->addDay()->toDateString();
    }

    private function datosValidos(array $sobreescribir = []): array
    {
        return array_merge([
            'nombre' => 'Laura Gómez',
            'telefono' => '3001112233',
            'email' => 'laura@test.local',
            'id_recurso' => $this->recursoA,
            'fecha_reserva' => $this->manana(),
            'hora_inicio' => '10:00',
            'notas' => 'Prefiero por la mañana',
        ], $sobreescribir);
    }

    /* ================= 1) LA SOLICITUD SE CREA COMO PENDIENTE ================= */

    public function test_una_solicitud_valida_crea_una_reserva_pendiente_del_publico(): void
    {
        $id = $this->svcReserva->crearSolicitudPublica($this->negocioA, $this->datosValidos());

        $this->assertIsInt($id);

        $reserva = DB::table('reservas')->where('id_reserva', $id)->first();

        $this->assertSame($this->negocioA, (int) $reserva->tenant_id);
        $this->assertSame('pendiente', $reserva->estado_reserva);
        $this->assertSame('publico', $reserva->origen, 'La marca de origen es lo que la distingue de una del backoffice');
        $this->assertNull($reserva->id_empleado, 'Repartir la cita es decisión del negocio, no de quien la pide');
        $this->assertSame($this->recursoA, (int) $reserva->id_recurso);
        $this->assertSame('Prefiero por la mañana', $reserva->notas);
    }

    public function test_la_hora_de_fin_se_calcula_desde_la_duracion_del_servicio(): void
    {
        $id = $this->svcReserva->crearSolicitudPublica($this->negocioA, $this->datosValidos(['hora_inicio' => '10:00']));

        $reserva = DB::table('reservas')->where('id_reserva', $id)->first();

        $this->assertSame('10:00:00', substr($reserva->hora_inicio, 0, 8));
        $this->assertSame('11:00:00', substr($reserva->hora_fin, 0, 8), 'El servicio dura 60 minutos');
    }

    /**
     * Una reserva del backoffice tiene que seguir naciendo como 'admin'.
     */
    public function test_una_reserva_del_backoffice_conserva_el_origen_admin(): void
    {
        $idCliente = $this->crearCliente($this->negocioA, 'Cliente Del Admin', '3009998877');

        $id = $this->svcReserva->crear([
            'tenant_id' => $this->negocioA,
            'id_cliente' => $idCliente,
            'id_recurso' => $this->recursoA,
            'id_empleado' => null,
            'fecha_reserva' => $this->manana(),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '10:00:00',
            'estado_reserva' => 'pendiente',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $this->assertSame('admin', DB::table('reservas')->where('id_reserva', $id)->value('origen'));
    }

    /* ================= 2) EL CLIENTE: BUSCAR O CREAR POR TELÉFONO ================= */

    public function test_una_solicitud_de_alguien_nuevo_crea_su_ficha_de_cliente(): void
    {
        $this->svcReserva->crearSolicitudPublica($this->negocioA, $this->datosValidos());

        $cliente = DB::table('clientes')->where('telefono', '3001112233')->first();

        $this->assertNotNull($cliente);
        $this->assertSame($this->negocioA, (int) $cliente->tenant_id);
        $this->assertSame('Laura Gómez', $cliente->nombre);
        $this->assertSame('laura@test.local', $cliente->email);
    }

    public function test_una_solicitud_de_alguien_conocido_reutiliza_su_ficha(): void
    {
        $idExistente = $this->crearCliente($this->negocioA, 'Laura G.', '3001112233', 'vieja@test.local');

        $id = $this->svcReserva->crearSolicitudPublica($this->negocioA, $this->datosValidos());

        $this->assertSame(1, DB::table('clientes')->where('telefono', '3001112233')->count(), 'No puede duplicarse la ficha');
        $this->assertSame($idExistente, (int) DB::table('reservas')->where('id_reserva', $id)->value('id_cliente'));
    }

    public function test_los_datos_del_cliente_se_refrescan_si_llegan_distintos(): void
    {
        $idExistente = $this->crearCliente($this->negocioA, 'Laura G.', '3001112233', 'vieja@test.local');

        $this->svcReserva->crearSolicitudPublica($this->negocioA, $this->datosValidos());

        $cliente = DB::table('clientes')->where('id_cliente', $idExistente)->first();

        $this->assertSame('Laura Gómez', $cliente->nombre);
        $this->assertSame('laura@test.local', $cliente->email);
    }

    /**
     * Un dato guardado no se pisa con uno vacío: quien no deja correo ahora no
     * puede borrar el que dejó la última vez.
     */
    public function test_un_correo_vacio_no_borra_el_que_el_cliente_ya_tenia(): void
    {
        $idExistente = $this->crearCliente($this->negocioA, 'Laura Gómez', '3001112233', 'laura@test.local');

        $this->svcReserva->crearSolicitudPublica($this->negocioA, $this->datosValidos(['email' => null]));

        $this->assertSame('laura@test.local', DB::table('clientes')->where('id_cliente', $idExistente)->value('email'));
    }

    /**
     * El mismo teléfono en dos negocios son dos personas distintas para el
     * sistema: cada negocio tiene su propia cartera de clientes.
     */
    public function test_el_cliente_de_otro_negocio_no_se_reutiliza(): void
    {
        $delB = $this->crearCliente($this->negocioB, 'Laura Del B', '3001112233');

        $id = $this->svcReserva->crearSolicitudPublica($this->negocioA, $this->datosValidos());

        $idClienteUsado = (int) DB::table('reservas')->where('id_reserva', $id)->value('id_cliente');

        $this->assertNotSame($delB, $idClienteUsado);
        $this->assertSame($this->negocioA, (int) DB::table('clientes')->where('id_cliente', $idClienteUsado)->value('tenant_id'));
    }

    /* ================= 3) EL CORREO: LA REGLA CENTRAL ================= */

    /**
     * ⭐ LA PRUEBA MÁS IMPORTANTE DE ESTE MÓDULO.
     *
     * Crear una solicitud pública NO puede mandarle al cliente el correo de
     * "tu reserva está confirmada": su cita no está confirmada, está esperando
     * a que el negocio la mire.
     */
    public function test_crear_una_solicitud_publica_NO_le_manda_al_cliente_el_correo_de_confirmacion(): void
    {
        Mail::fake();

        $id = $this->svcReserva->crearSolicitudPublica($this->negocioA, $this->datosValidos());

        $this->assertIsInt($id, 'La solicitud sí tiene que crearse');

        Mail::assertNotQueued(ReservaConfirmada::class);
        Mail::assertNothingQueued();
    }

    /**
     * Y tampoco si el cliente ya existía con correo registrado: no es que el
     * correo no se mande por no tener a quién, es que no se manda nunca.
     */
    public function test_tampoco_manda_correo_cuando_el_cliente_ya_tenia_email_registrado(): void
    {
        $this->crearCliente($this->negocioA, 'Laura Gómez', '3001112233', 'laura@test.local');

        Mail::fake();

        $this->svcReserva->crearSolicitudPublica($this->negocioA, $this->datosValidos());

        Mail::assertNotQueued(ReservaConfirmada::class);
    }

    public function test_el_endpoint_publico_avisa_al_admin_y_no_al_cliente(): void
    {
        Mail::fake();

        $this->crearAdmin($this->negocioA, 'admin@spafashion.test');

        $this->postJson('publico/spa-fashion/agendar', $this->datosValidos())
            ->assertJsonPath('error', 0);

        // Al negocio sí.
        Mail::assertQueued(NuevaSolicitudPublica::class, function ($correo) {
            return $correo->hasTo('admin@spafashion.test');
        });

        // Al cliente no.
        Mail::assertNotQueued(ReservaConfirmada::class);
        Mail::assertNotQueued(NuevaSolicitudPublica::class, function ($correo) {
            return $correo->hasTo('laura@test.local');
        });
    }

    public function test_el_aviso_al_admin_lleva_los_datos_de_la_solicitud(): void
    {
        Mail::fake();

        $this->crearAdmin($this->negocioA, 'admin@spafashion.test');

        $this->postJson('publico/spa-fashion/agendar', $this->datosValidos())->assertJsonPath('error', 0);

        Mail::assertQueued(NuevaSolicitudPublica::class, function ($correo) {
            return $correo->solicitud['nombre_cliente'] === 'Laura Gómez'
                && $correo->solicitud['telefono_cliente'] === '3001112233'
                && $correo->solicitud['nombre_recurso'] === 'Masaje del A'
                && $correo->nombreNegocio === 'Spa Fashion';
        });
    }

    /**
     * Sin admin con correo no hay a quién avisar, pero la solicitud se guarda
     * igual: el aviso es un extra, no una condición.
     */
    public function test_sin_admin_con_correo_la_solicitud_se_guarda_de_todas_formas(): void
    {
        Mail::fake();

        $this->postJson('publico/spa-fashion/agendar', $this->datosValidos())->assertJsonPath('error', 0);

        $this->assertSame(1, DB::table('reservas')->where('origen', 'publico')->count());
        Mail::assertNothingQueued();
    }

    /**
     * ⭐ CONTROL DE REGRESIÓN: el flujo del backoffice tiene que seguir
     * mandando su correo de confirmación como siempre.
     */
    public function test_el_flujo_normal_del_backoffice_SIGUE_enviando_su_correo_de_confirmacion(): void
    {
        Mail::fake();

        $idCliente = $this->crearCliente($this->negocioA, 'Cliente Con Correo', '3009998877', 'cliente@test.local');

        $this->withSession([
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 1,
            'usuario' => 'admin.test',
            'nombre_usuario' => 'Admin Test',
            'tenant_id' => $this->negocioA,
            'id_rol' => 1,
        ])->postJson('request/reserva/crear', [
            'id_cliente' => $idCliente,
            'id_recurso' => $this->recursoA,
            'fecha_reserva' => $this->manana(),
            'hora_inicio' => '10:00',
        ])->assertJsonPath('error', 0);

        Mail::assertQueued(ReservaConfirmada::class, function ($correo) {
            return $correo->hasTo('cliente@test.local');
        });
    }

    /* ================= 4) VALIDACIONES ================= */

    public function test_una_fecha_pasada_es_rechazada(): void
    {
        $resultado = $this->svcReserva->crearSolicitudPublica(
            $this->negocioA,
            $this->datosValidos(['fecha_reserva' => now()->subDay()->toDateString()])
        );

        $this->assertFalse($resultado);
        $this->assertSame(0, DB::table('reservas')->count());
    }

    public function test_una_hora_fuera_del_horario_de_atencion_es_rechazada(): void
    {
        // El negocio cierra a las 22:00; una cita de 60 min a las 21:30 se pasa.
        $resultado = $this->svcReserva->crearSolicitudPublica(
            $this->negocioA,
            $this->datosValidos(['hora_inicio' => '21:30'])
        );

        $this->assertFalse($resultado);
        $this->assertSame(0, DB::table('reservas')->count());
    }

    public function test_un_dia_en_que_el_negocio_no_atiende_es_rechazado(): void
    {
        DB::table('negocios')->where('id_negocio', $this->negocioA)->update(['dias_atencion' => '1,2,3']);

        // Un sábado (día 6), que no está entre los días configurados.
        $sabado = now()->next(\Carbon\Carbon::SATURDAY)->toDateString();

        $resultado = $this->svcReserva->crearSolicitudPublica(
            $this->negocioA,
            $this->datosValidos(['fecha_reserva' => $sabado])
        );

        $this->assertFalse($resultado);
        $this->assertSame(0, DB::table('reservas')->count());
    }

    public function test_un_servicio_inactivo_no_se_puede_reservar(): void
    {
        $inactivo = $this->crearRecurso($this->negocioA, 'Servicio Retirado', 0);

        $resultado = $this->svcReserva->crearSolicitudPublica($this->negocioA, $this->datosValidos(['id_recurso' => $inactivo]));

        $this->assertFalse($resultado);
        $this->assertSame(0, DB::table('reservas')->count());
    }

    /**
     * Ni una solicitud a medias: si el recurso no vale, tampoco puede quedar
     * creado el cliente que venía en la misma petición.
     */
    public function test_una_solicitud_rechazada_no_deja_el_cliente_creado(): void
    {
        $resultado = $this->svcReserva->crearSolicitudPublica($this->negocioA, $this->datosValidos(['id_recurso' => 99999]));

        $this->assertFalse($resultado);
        $this->assertSame(0, DB::table('clientes')->count(), 'La transacción tiene que revertir también el alta del cliente');
    }

    public function test_el_endpoint_exige_los_campos_obligatorios(): void
    {
        $respuesta = $this->postJson('publico/spa-fashion/agendar', ['notas' => 'solo notas']);

        $respuesta->assertJsonPath('error', 1);
        $this->assertSame(0, DB::table('reservas')->count());
    }

    public function test_el_endpoint_rechaza_un_correo_con_formato_invalido(): void
    {
        $this->postJson('publico/spa-fashion/agendar', $this->datosValidos(['email' => 'esto-no-es-un-correo']))
            ->assertJsonPath('error', 1);

        $this->assertSame(0, DB::table('reservas')->count());
    }

    public function test_un_slug_inexistente_devuelve_404_al_agendar(): void
    {
        $this->postJson('publico/no-existe/agendar', $this->datosValidos())->assertNotFound();
    }

    /* ================= 5) AISLAMIENTO DE TENANT ================= */

    /**
     * El servicio de OTRO negocio no se puede reservar mandando su id a mano.
     */
    public function test_no_se_puede_pedir_cita_con_el_servicio_de_otro_negocio(): void
    {
        $resultado = $this->svcReserva->crearSolicitudPublica(
            $this->negocioA,
            $this->datosValidos(['id_recurso' => $this->recursoB])
        );

        $this->assertFalse($resultado);
        $this->assertSame(0, DB::table('reservas')->count());
    }

    public function test_el_endpoint_no_deja_colar_el_servicio_de_otro_negocio(): void
    {
        $this->postJson('publico/spa-fashion/agendar', $this->datosValidos(['id_recurso' => $this->recursoB]))
            ->assertJsonPath('error', 1);

        $this->assertSame(0, DB::table('reservas')->count());
    }

    /**
     * El negocio sale del slug: mandar un tenant_id en el cuerpo no lo cambia.
     */
    public function test_el_tenant_del_cuerpo_de_la_peticion_se_ignora(): void
    {
        $this->postJson('publico/spa-fashion/agendar', $this->datosValidos(['tenant_id' => $this->negocioB]))
            ->assertJsonPath('error', 0);

        $reserva = DB::table('reservas')->first();

        $this->assertSame($this->negocioA, (int) $reserva->tenant_id, 'El slug manda, no el cuerpo');
    }

    /* ================= 6) HONEYPOT ================= */

    public function test_el_campo_trampa_relleno_no_crea_nada(): void
    {
        Mail::fake();

        $respuesta = $this->postJson('publico/spa-fashion/agendar', $this->datosValidos([
            'sitio_web' => 'http://spam.example.com',
        ]));

        // Responde como si todo hubiera ido bien...
        $respuesta->assertJsonPath('error', 0);

        // ...pero no creó absolutamente nada.
        $this->assertSame(0, DB::table('reservas')->count());
        $this->assertSame(0, DB::table('clientes')->count());
        Mail::assertNothingQueued();
    }

    /**
     * Y la respuesta es indistinguible de la de una solicitud buena: un bot no
     * puede deducir cuál fue el filtro que lo paró.
     */
    public function test_la_respuesta_de_la_trampa_es_identica_a_la_de_una_solicitud_buena(): void
    {
        $conTrampa = $this->postJson('publico/spa-fashion/agendar', $this->datosValidos([
            'telefono' => '3001112233',
            'sitio_web' => 'http://spam.example.com',
        ]));

        $buena = $this->postJson('publico/spa-fashion/agendar', $this->datosValidos([
            'telefono' => '3004445566',
        ]));

        $this->assertSame($buena->getStatusCode(), $conTrampa->getStatusCode());
        $this->assertSame($buena->json(), $conTrampa->json());
    }

    public function test_el_campo_trampa_vacio_no_estorba(): void
    {
        $this->postJson('publico/spa-fashion/agendar', $this->datosValidos(['sitio_web' => '']))
            ->assertJsonPath('error', 0);

        $this->assertSame(1, DB::table('reservas')->count());
    }
}
