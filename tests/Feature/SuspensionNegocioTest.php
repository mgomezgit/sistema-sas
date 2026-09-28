<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Mail\ReservaRecordatorio;
use App\Mail\ResumenStockBajo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Suspensión de un negocio por el super admin (negocios.estado = 0).
 *
 * Suspender corta el acceso por todos los caminos: el login, las sesiones que
 * ya estaban abiertas (en la siguiente petición), los correos automáticos del
 * Scheduler y la página pública. Y NO toca ningún dato del negocio.
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * M3 — Sin el chequeo de negocios.estado en VerificarSesion (la condición se
 *      reemplazó por false): 11 tests, 10 passed, 1 FAILED —
 *      test_una_sesion_abierta_se_corta_en_la_siguiente_peticion. La sesión
 *      del negocio suspendido siguió trabajando. Restaurado: 11 passed.
 *
 * M5 — Sin el filtro de negocios.estado en SvcReserva::listarParaRecordatorio
 *      y en SvcProducto::listarStockBajoTodosLosNegocios: 11 tests, 9 passed,
 *      2 FAILED — las dos del Scheduler ("The unexpected [ReservaRecordatorio]
 *      / [ResumenStockBajo] mailable was queued"). Restaurado: 11 passed.
 */
class SuspensionNegocioTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = $this->crearNegocio('Negocio A', 'negocio-a');
        $this->negocioB = $this->crearNegocio('Negocio B', 'negocio-b');

        $this->crearUsuario($this->negocioA, 1, 'admin.a', 'admin.a@negocio.test');
        $this->crearUsuario($this->negocioB, 1, 'admin.b', 'admin.b@negocio.test');
        $this->crearUsuario(null, 3, 'super', 'super@plataforma.test');
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre, string $slug): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'slug' => $slug,
            'rubro' => 'spa',
            'dias_atencion' => '0,1,2,3,4,5,6',
            'hora_apertura' => '08:00:00',
            'hora_cierre' => '18:00:00',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearUsuario(?int $tenantId, int $idRol, string $usuario, string $email): int
    {
        return DB::table('usuarios')->insertGetId([
            'usuario' => $usuario,
            'nombre' => ucfirst($usuario),
            'email' => $email,
            'clave' => bcrypt('clave123'),
            'tenant_id' => $tenantId,
            'id_rol' => $idRol,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function suspender(int $idNegocio): void
    {
        DB::table('negocios')->where('id_negocio', $idNegocio)->update(['estado' => 0]);
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

    private function sesionSuperAdmin(): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 3,
            'usuario' => 'super',
            'nombre_usuario' => 'Super',
            'tenant_id' => null,
            'id_rol' => 3,
        ];
    }

    private function login(string $email, string $clave = 'clave123')
    {
        return $this->postJson('request/autenticacion/login', ['email' => $email, 'clave' => $clave]);
    }

    private function crearClienteYReserva(int $tenantId, string $email, string $fecha): array
    {
        $idCliente = DB::table('clientes')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => 'Cliente '.$tenantId,
            'telefono' => '3000000000',
            'email' => $email,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $idRecurso = DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => 'Masaje',
            'duracion_minutos' => 60,
            'precio' => 50000,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $idReserva = DB::table('reservas')->insertGetId([
            'tenant_id' => $tenantId,
            'id_cliente' => $idCliente,
            'id_recurso' => $idRecurso,
            'id_empleado' => null,
            'fecha_reserva' => $fecha,
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado_reserva' => 'confirmada',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        return ['id_cliente' => $idCliente, 'id_recurso' => $idRecurso, 'id_reserva' => $idReserva];
    }

    /* ================= 1) LOGIN ================= */

    public function test_el_login_de_un_negocio_suspendido_se_rechaza_con_mensaje_claro(): void
    {
        $this->suspender($this->negocioA);

        $respuesta = $this->login('admin.a@negocio.test');

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertSame(VerificarSesion::MENSAJE_NEGOCIO_INACTIVO, $respuesta->json('mensaje'));
        $respuesta->assertSessionMissing('app_sesion');
    }

    /**
     * Con la clave equivocada el mensaje es el genérico de siempre: el aviso de
     * "cuenta inactiva" no puede confirmarle a un extraño que el correo existe.
     */
    public function test_con_clave_equivocada_no_se_revela_que_el_negocio_esta_suspendido(): void
    {
        $this->suspender($this->negocioA);

        $respuesta = $this->login('admin.a@negocio.test', 'clave-equivocada');

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertNotSame(VerificarSesion::MENSAJE_NEGOCIO_INACTIVO, $respuesta->json('mensaje'));
    }

    public function test_los_usuarios_de_otro_negocio_siguen_entrando(): void
    {
        $this->suspender($this->negocioA);

        $this->assertSame(0, $this->login('admin.b@negocio.test')->json('error'));
    }

    public function test_el_super_admin_entra_aunque_todos_los_negocios_esten_suspendidos(): void
    {
        $this->suspender($this->negocioA);
        $this->suspender($this->negocioB);

        $this->assertSame(0, $this->login('super@plataforma.test')->json('error'));
    }

    /* ================= 2) SESIONES YA ABIERTAS ================= */

    /** Ver M3 en el encabezado de la clase. */
    public function test_una_sesion_abierta_se_corta_en_la_siguiente_peticion(): void
    {
        $sesion = $this->sesionAdmin($this->negocioA);

        // Antes de suspender, la sesión trabaja con normalidad.
        $this->assertSame(0, $this->withSession($sesion)->getJson('request/cliente/listar')->json('error'));

        $this->suspender($this->negocioA);

        // request/*: JSON de error con el motivo, y la sesión queda cerrada.
        $respuestaRequest = $this->withSession($sesion)->getJson('request/cliente/listar');
        $this->assertSame(1, $respuestaRequest->json('error'));
        $this->assertSame(VerificarSesion::MENSAJE_NEGOCIO_INACTIVO, $respuestaRequest->json('mensaje'));
        $respuestaRequest->assertSessionMissing('app_sesion');

        // backoffice/*: vuelta al login con el aviso para mostrar allí.
        $this->withSession($sesion)
            ->get('backoffice/clientes')
            ->assertRedirect(url('/login'))
            ->assertSessionHas('aviso_login', VerificarSesion::MENSAJE_NEGOCIO_INACTIVO);
    }

    /**
     * La pantalla de login muestra el motivo del corte. Esta prueba existe
     * porque la primera versión del aviso rompió /login con un 500 (Blade
     * compiló una directiva escrita dentro de un comentario de JS) y ninguna
     * prueba renderizaba esa pantalla.
     */
    public function test_la_pantalla_de_login_carga_y_muestra_el_aviso_de_suspension(): void
    {
        $this->get('/login')->assertStatus(200);

        $this->withSession(['aviso_login' => VerificarSesion::MENSAJE_NEGOCIO_INACTIVO])
            ->get('/login')
            ->assertStatus(200)
            ->assertSee(json_encode(VerificarSesion::MENSAJE_NEGOCIO_INACTIVO), false);
    }

    public function test_la_sesion_de_otro_negocio_no_se_ve_afectada(): void
    {
        $this->suspender($this->negocioA);

        $sesionB = $this->sesionAdmin($this->negocioB);

        $this->assertSame(0, $this->withSession($sesionB)->getJson('request/cliente/listar')->json('error'));
        $this->withSession($sesionB)->get('backoffice/clientes')->assertStatus(200);
    }

    public function test_la_sesion_del_super_admin_nunca_se_corta(): void
    {
        $this->suspender($this->negocioA);
        $this->suspender($this->negocioB);

        $this->assertSame(
            0,
            $this->withSession($this->sesionSuperAdmin())->getJson('request/superadmin/resumen')->json('error')
        );
    }

    /* ================= 3) REACTIVAR Y DATOS INTACTOS ================= */

    public function test_suspender_y_reactivar_por_el_endpoint_no_toca_datos_y_restaura_el_acceso(): void
    {
        $datos = $this->crearClienteYReserva($this->negocioA, 'cliente.a@correo.test', date('Y-m-d'));

        $clienteAntes = (array) DB::table('clientes')->where('id_cliente', $datos['id_cliente'])->first();
        $reservaAntes = (array) DB::table('reservas')->where('id_reserva', $datos['id_reserva'])->first();
        $negocioAntes = (array) DB::table('negocios')->where('id_negocio', $this->negocioA)->first();

        // Suspender por el endpoint real del super admin.
        $this->withSession($this->sesionSuperAdmin())
            ->postJson('request/superadmin/cambiar-estado-negocio', ['id_negocio' => $this->negocioA, 'estado' => 0])
            ->assertJsonPath('error', 0);

        $this->assertSame(VerificarSesion::MENSAJE_NEGOCIO_INACTIVO, $this->login('admin.a@negocio.test')->json('mensaje'));

        // Ningún dato cambió: ni el cliente, ni la reserva, ni el negocio más
        // allá de su columna estado.
        $this->assertEquals($clienteAntes, (array) DB::table('clientes')->where('id_cliente', $datos['id_cliente'])->first());
        $this->assertEquals($reservaAntes, (array) DB::table('reservas')->where('id_reserva', $datos['id_reserva'])->first());

        $negocioSuspendido = (array) DB::table('negocios')->where('id_negocio', $this->negocioA)->first();
        $this->assertSame(0, (int) $negocioSuspendido['estado']);
        unset($negocioAntes['estado'], $negocioSuspendido['estado']);
        $this->assertEquals($negocioAntes, $negocioSuspendido, 'Suspender solo puede cambiar negocios.estado');

        // Reactivar devuelve el acceso.
        $this->withSession($this->sesionSuperAdmin())
            ->postJson('request/superadmin/cambiar-estado-negocio', ['id_negocio' => $this->negocioA, 'estado' => 1])
            ->assertJsonPath('error', 0);

        $this->assertSame(0, $this->login('admin.a@negocio.test')->json('error'));
        $this->assertEquals($reservaAntes, (array) DB::table('reservas')->where('id_reserva', $datos['id_reserva'])->first());
    }

    /* ================= 4) SCHEDULER ================= */

    public function test_los_recordatorios_ignoran_a_un_negocio_suspendido(): void
    {
        Mail::fake();

        $manana = now()->addDay()->format('Y-m-d');
        $this->crearClienteYReserva($this->negocioA, 'cliente.a@correo.test', $manana);
        $this->crearClienteYReserva($this->negocioB, 'cliente.b@correo.test', $manana);

        $this->suspender($this->negocioB);

        Artisan::call('reservas:enviar-recordatorios');

        Mail::assertQueued(ReservaRecordatorio::class, fn ($mail) => $mail->hasTo('cliente.a@correo.test'));
        Mail::assertNotQueued(ReservaRecordatorio::class, fn ($mail) => $mail->hasTo('cliente.b@correo.test'));
        Mail::assertQueuedCount(1);
    }

    public function test_el_resumen_de_stock_bajo_ignora_a_un_negocio_suspendido(): void
    {
        Mail::fake();

        foreach ([$this->negocioA, $this->negocioB] as $idNegocio) {
            DB::table('productos')->insert([
                'tenant_id' => $idNegocio,
                'nombre' => 'Shampoo',
                'cantidad_actual' => 1,
                'cantidad_minima' => 5,
                'usuario_registra' => 'test',
                'fecha_registro' => date('Y-m-d H:i:s'),
                'estado' => 1,
            ]);
        }

        $this->suspender($this->negocioB);

        Artisan::call('productos:enviar-resumen-stock-bajo');

        Mail::assertQueued(ResumenStockBajo::class, fn ($mail) => $mail->hasTo('admin.a@negocio.test'));
        Mail::assertNotQueued(ResumenStockBajo::class, fn ($mail) => $mail->hasTo('admin.b@negocio.test'));
        Mail::assertQueuedCount(1);
    }

    /* ================= 5) PÁGINA PÚBLICA ================= */

    /**
     * Agendar en un negocio suspendido responde EXACTAMENTE igual que en un
     * slug que no existe: desde fuera no se distingue "suspendido" de
     * "inexistente", y no se crea nada.
     */
    public function test_agendar_en_un_negocio_suspendido_responde_como_un_slug_inexistente(): void
    {
        $idRecurso = DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $this->negocioA,
            'nombre' => 'Masaje',
            'duracion_minutos' => 60,
            'precio' => 50000,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $this->suspender($this->negocioA);

        $cuerpo = [
            'nombre' => 'Cliente Publico',
            'telefono' => '3001112233',
            'email' => 'publico@correo.test',
            'id_recurso' => $idRecurso,
            'fecha_reserva' => now()->addDays(2)->format('Y-m-d'),
            'hora_inicio' => '10:00',
        ];

        $clientesAntes = DB::table('clientes')->count();
        $reservasAntes = DB::table('reservas')->count();

        $suspendido = $this->postJson('publico/negocio-a/agendar', $cuerpo);
        $inexistente = $this->postJson('publico/slug-que-no-existe/agendar', $cuerpo);

        $suspendido->assertStatus(404);
        $this->assertSame($inexistente->getStatusCode(), $suspendido->getStatusCode());
        // Se compara el mensaje y no el cuerpo entero: con APP_DEBUG activo el
        // cuerpo trae la traza, que incluye la línea de ESTA prueba desde la
        // que se hizo cada llamada, y por eso nunca coincide byte a byte.
        $this->assertSame(
            $inexistente->json('message'),
            $suspendido->json('message'),
            'La respuesta no puede delatar que el negocio existe'
        );

        $this->assertSame($clientesAntes, DB::table('clientes')->count(), 'No debe crearse ningún cliente');
        $this->assertSame($reservasAntes, DB::table('reservas')->count(), 'No debe crearse ninguna reserva');
    }
}
