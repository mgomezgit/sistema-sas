<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Service\SvcModulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Vigencia de los módulos de pago: corte en tiempo real, validación, renovar,
 * desactivar, privacidad del origen/nota y listas blancas. El proceso diario y
 * sus correos viven en ModuloVencimientoCommandTest.
 *
 * Reloj congelado en un día conocido (no depende de la fecha real).
 */
class ModuloVigenciaTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 10:00:00');

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = $this->crearNegocio('Negocio A');
        $this->negocioB = $this->crearNegocio('Negocio B');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ================= AYUDANTES ================= */

    private function svc(): SvcModulo
    {
        return app(SvcModulo::class);
    }

    private function crearNegocio(string $nombre, int $estado = 1): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'rubro' => 'spa',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => $estado,
        ]);
    }

    private function idModulo(string $clave): int
    {
        return (int) DB::table('modulos_plataforma')->where('clave', $clave)->value('id_modulo');
    }

    /** Inserta una fila de módulo directamente, para fijar estados concretos. */
    private function fijarModulo(int $tenantId, string $clave, array $campos): void
    {
        DB::table('negocio_modulos')->updateOrInsert(
            ['tenant_id' => $tenantId, 'id_modulo' => $this->idModulo($clave)],
            $campos + ['fecha_registro' => now()]
        );
    }

    private function sesionAdmin(int $tenantId): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 100 + $tenantId,
            'usuario' => 'admin.test',
            'nombre_usuario' => 'Admin Test',
            'tenant_id' => $tenantId,
            'id_rol' => 1,
        ];
    }

    private function sesionEmpleado(int $tenantId): array
    {
        return ['id_rol' => 2, 'id_usuario' => 200 + $tenantId] + $this->sesionAdmin($tenantId);
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

    private function activarComo(array $cuerpo)
    {
        return $this->withSession($this->sesionSuperAdmin())
            ->postJson('request/superadmin/activar-modulo', $cuerpo);
    }

    /* ================= 1) VIGENCIA EN TIEMPO REAL ================= */

    public function test_con_vigencia_de_hoy_sigue_activo(): void
    {
        $this->fijarModulo($this->negocioA, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::today()->toDateString()]);

        $this->assertTrue($this->svc()->estaActivo($this->negocioA, 'comisiones'));
    }

    public function test_con_vigencia_de_ayer_ya_no_esta_activo_sin_correr_el_proceso(): void
    {
        $this->fijarModulo($this->negocioA, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::yesterday()->toDateString()]);

        // activo sigue en 1 en la base (el proceso diario no corrió), pero el
        // acceso se corta igual por la vigencia en tiempo real.
        $this->assertSame(1, (int) DB::table('negocio_modulos')->where('tenant_id', $this->negocioA)->value('activo'));
        $this->assertFalse($this->svc()->estaActivo($this->negocioA, 'comisiones'));
    }

    public function test_sin_fecha_de_vigencia_siempre_activo(): void
    {
        $this->fijarModulo($this->negocioA, 'comisiones', ['activo' => 1, 'vigente_hasta' => null]);

        $this->assertTrue($this->svc()->estaActivo($this->negocioA, 'comisiones'));
    }

    public function test_el_vencimiento_bloquea_la_vista_y_el_endpoint_de_comisiones(): void
    {
        $this->fijarModulo($this->negocioA, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::yesterday()->toDateString()]);

        $sesion = $this->sesionAdmin($this->negocioA);

        $this->withSession($sesion)->get('backoffice/comisiones')->assertStatus(302);
        $this->withSession($sesion)->getJson('request/comisiones/informe')
            ->assertJsonPath('error', 1);
    }

    public function test_el_vencimiento_bloquea_ia_y_chatbot_sin_correr_el_proceso(): void
    {
        $this->fijarModulo($this->negocioA, 'ia', ['activo' => 1, 'vigente_hasta' => Carbon::yesterday()->toDateString()]);
        $this->fijarModulo($this->negocioA, 'chatbot', ['activo' => 1, 'vigente_hasta' => Carbon::yesterday()->toDateString()]);

        $this->assertFalse(app(\App\Service\SvcIA::class)->disponible($this->negocioA));
        $this->assertFalse(app(\App\Service\SvcChatbot::class)->disponible($this->negocioA));
    }

    public function test_el_vencimiento_de_un_negocio_no_afecta_a_otro(): void
    {
        $this->fijarModulo($this->negocioA, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::yesterday()->toDateString()]);
        $this->fijarModulo($this->negocioB, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::tomorrow()->toDateString()]);

        $this->assertFalse($this->svc()->estaActivo($this->negocioA, 'comisiones'));
        $this->assertTrue($this->svc()->estaActivo($this->negocioB, 'comisiones'));
    }

    /* ================= 2) VALIDACIÓN AL ACTIVAR ================= */

    public function test_rechaza_fecha_pasada_invalida_o_mas_de_tres_anios(): void
    {
        foreach ([
            'pasada' => Carbon::yesterday()->toDateString(),
            'invalida' => '2026-13-40',
            'texto' => 'maniana',
            'muy lejana' => Carbon::today()->copy()->addYears(3)->addDay()->toDateString(),
        ] as $caso => $fecha) {
            $r = $this->activarComo([
                'id_negocio' => $this->negocioA,
                'clave_modulo' => 'comisiones',
                'vigente_hasta' => $fecha,
            ]);

            $this->assertSame(1, $r->json('error'), "La fecha $caso debe rechazarse");
            $this->assertDatabaseMissing('negocio_modulos', ['tenant_id' => $this->negocioA, 'id_modulo' => $this->idModulo('comisiones')]);
        }
    }

    public function test_rechaza_origen_fuera_de_la_lista_incluidos_los_reservados(): void
    {
        foreach (['plan', 'pasarela', 'cualquier_cosa'] as $origen) {
            $r = $this->activarComo([
                'id_negocio' => $this->negocioA,
                'clave_modulo' => 'comisiones',
                'origen' => $origen,
            ]);

            $this->assertSame(1, $r->json('error'), "El origen $origen debe rechazarse");
        }

        $this->assertDatabaseMissing('negocio_modulos', ['tenant_id' => $this->negocioA, 'id_modulo' => $this->idModulo('comisiones')]);
    }

    public function test_rechaza_nota_de_mas_de_200(): void
    {
        $r = $this->activarComo([
            'id_negocio' => $this->negocioA,
            'clave_modulo' => 'comisiones',
            'nota' => str_repeat('a', 201),
        ]);

        $this->assertSame(1, $r->json('error'));
        $this->assertDatabaseMissing('negocio_modulos', ['tenant_id' => $this->negocioA, 'id_modulo' => $this->idModulo('comisiones')]);
    }

    public function test_rechaza_una_clave_de_modulo_inexistente(): void
    {
        $r = $this->activarComo([
            'id_negocio' => $this->negocioA,
            'clave_modulo' => 'modulo_fantasma',
        ]);

        $this->assertSame(1, $r->json('error'));
    }

    public function test_acepta_fecha_vacia_origen_valido_y_nota(): void
    {
        $r = $this->activarComo([
            'id_negocio' => $this->negocioA,
            'clave_modulo' => 'comisiones',
            'vigente_hasta' => '',
            'origen' => 'cortesia',
            'nota' => 'Regalo de bienvenida',
        ]);

        $r->assertJsonPath('error', 0);
        $fila = DB::table('negocio_modulos')->where('tenant_id', $this->negocioA)->first();
        $this->assertNull($fila->vigente_hasta);
        $this->assertSame('cortesia', $fila->origen);
        $this->assertSame('Regalo de bienvenida', $fila->nota);
    }

    public function test_admin_empleado_y_anonimo_no_pueden_activar_ni_consultar_vencimientos(): void
    {
        $this->fijarModulo($this->negocioA, 'comisiones', ['activo' => 0, 'vigente_hasta' => null]);

        $sesiones = [
            'admin' => $this->sesionAdmin($this->negocioA),
            'empleado' => $this->sesionEmpleado($this->negocioA),
            'anonimo' => null,
        ];

        foreach ($sesiones as $quien => $sesion) {
            $peticion = $sesion === null ? $this : $this->withSession($sesion);

            $r1 = $peticion->postJson('request/superadmin/activar-modulo', [
                'id_negocio' => $this->negocioA, 'clave_modulo' => 'comisiones', 'vigente_hasta' => Carbon::tomorrow()->toDateString(),
            ]);
            $this->assertSame(1, $r1->json('error'), "$quien no debe activar");

            $peticion2 = $sesion === null ? $this : $this->withSession($sesion);
            $r2 = $peticion2->getJson('request/superadmin/modulos-por-vencer');
            $this->assertSame(1, $r2->json('error'), "$quien no debe ver modulos-por-vencer");
        }

        // El módulo quedó intacto (inactivo, sin fecha).
        $fila = DB::table('negocio_modulos')->where('tenant_id', $this->negocioA)->first();
        $this->assertSame(0, (int) $fila->activo);
        $this->assertNull($fila->vigente_hasta);
    }

    /* ================= 3) ACTIVAR / RENOVAR / DESACTIVAR ================= */

    public function test_renovar_cambia_la_fecha_reinicia_avisos_y_conserva_fecha_activacion(): void
    {
        $this->fijarModulo($this->negocioA, 'comisiones', [
            'activo' => 1,
            'vigente_hasta' => Carbon::today()->copy()->addDays(5)->toDateString(),
            'fecha_activacion' => '2026-11-01 09:00:00',
            'aviso_7_enviado_en' => '2026-11-03 06:30:00',
            'aviso_1_enviado_en' => '2026-11-09 06:30:00',
        ]);

        $nuevaFecha = Carbon::today()->copy()->addDays(30)->toDateString();
        $this->activarComo(['id_negocio' => $this->negocioA, 'clave_modulo' => 'comisiones', 'vigente_hasta' => $nuevaFecha])
            ->assertJsonPath('error', 0);

        $fila = DB::table('negocio_modulos')->where('tenant_id', $this->negocioA)->first();
        $this->assertSame($nuevaFecha, Carbon::parse($fila->vigente_hasta)->toDateString());
        $this->assertSame('2026-11-01 09:00:00', Carbon::parse($fila->fecha_activacion)->format('Y-m-d H:i:s'), 'Renovar NO toca fecha_activacion');
        $this->assertNull($fila->aviso_7_enviado_en, 'Renovar reinicia los avisos');
        $this->assertNull($fila->aviso_1_enviado_en);
    }

    public function test_activar_uno_inactivo_reinicia_fecha_activacion_y_limpia_desactivacion(): void
    {
        $this->fijarModulo($this->negocioA, 'comisiones', [
            'activo' => 0,
            'fecha_activacion' => '2026-01-01 00:00:00',
            'fecha_desactivacion' => '2026-10-01 00:00:00',
        ]);

        $this->activarComo(['id_negocio' => $this->negocioA, 'clave_modulo' => 'comisiones'])
            ->assertJsonPath('error', 0);

        $fila = DB::table('negocio_modulos')->where('tenant_id', $this->negocioA)->first();
        $this->assertSame(now()->format('Y-m-d'), Carbon::parse($fila->fecha_activacion)->format('Y-m-d'));
        $this->assertNull($fila->fecha_desactivacion);
    }

    public function test_desactivar_a_mano_conserva_vigencia_origen_y_nota(): void
    {
        $this->fijarModulo($this->negocioA, 'comisiones', [
            'activo' => 1,
            'vigente_hasta' => Carbon::today()->copy()->addDays(10)->toDateString(),
            'origen' => 'pagado_fuera',
            'nota' => 'Pago por transferencia',
        ]);

        $this->withSession($this->sesionSuperAdmin())
            ->postJson('request/superadmin/desactivar-modulo', ['id_negocio' => $this->negocioA, 'clave_modulo' => 'comisiones'])
            ->assertJsonPath('error', 0);

        $fila = DB::table('negocio_modulos')->where('tenant_id', $this->negocioA)->first();
        $this->assertSame(0, (int) $fila->activo);
        $this->assertSame('pagado_fuera', $fila->origen, 'Se conserva como historial');
        $this->assertSame('Pago por transferencia', $fila->nota);
        $this->assertNotNull($fila->vigente_hasta);
    }

    public function test_desactivar_no_toca_los_datos_del_modulo(): void
    {
        // Datos de Comisiones (tarifa) y de IA (uso): ni desactivar ni vencer los borra.
        $idEmpleado = DB::table('empleados')->insertGetId([
            'tenant_id' => $this->negocioA, 'nombre' => 'Empleada', 'telefono' => '3000000000',
            'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ]);
        $idRecurso = DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $this->negocioA, 'nombre' => 'Masaje', 'duracion_minutos' => 60, 'precio' => 100000,
            'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ]);
        $idTarifa = DB::table('comisiones_tarifas')->insertGetId([
            'tenant_id' => $this->negocioA, 'id_empleado' => $idEmpleado, 'id_recurso' => $idRecurso,
            'porcentaje_comision' => 15, 'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ]);
        DB::table('ia_uso')->insert([
            'tenant_id' => $this->negocioA, 'mes' => '2026-11', 'generaciones' => 4,
            'tokens_entrada' => 100, 'tokens_salida' => 50, 'fecha_registro' => now(),
        ]);

        $this->fijarModulo($this->negocioA, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::today()->toDateString()]);
        $this->fijarModulo($this->negocioA, 'ia', ['activo' => 1, 'vigente_hasta' => Carbon::today()->toDateString()]);

        foreach (['comisiones', 'ia'] as $clave) {
            $this->withSession($this->sesionSuperAdmin())
                ->postJson('request/superadmin/desactivar-modulo', ['id_negocio' => $this->negocioA, 'clave_modulo' => $clave]);
        }

        $this->assertDatabaseHas('comisiones_tarifas', ['id_comision_tarifa' => $idTarifa]);
        $this->assertDatabaseHas('ia_uso', ['tenant_id' => $this->negocioA, 'mes' => '2026-11', 'generaciones' => 4]);
    }

    /* ================= 4) PRIVACIDAD Y LISTAS BLANCAS ================= */

    public function test_modulos_de_negocio_devuelve_solo_los_campos_permitidos_con_origen_y_nota(): void
    {
        $this->fijarModulo($this->negocioA, 'comisiones', [
            'activo' => 1, 'vigente_hasta' => Carbon::today()->copy()->addDays(3)->toDateString(),
            'origen' => 'cortesia', 'nota' => 'Interna',
        ]);

        $modulos = $this->withSession($this->sesionSuperAdmin())
            ->getJson('request/superadmin/modulos-de-negocio?id_negocio='.$this->negocioA)
            ->assertJsonPath('error', 0)
            ->json('data.modulos');

        $comisiones = collect($modulos)->firstWhere('clave', 'comisiones');
        $this->assertSame([
            'id_modulo', 'clave', 'nombre', 'descripcion', 'activo', 'activo_vigente',
            'vencido', 'vigente_hasta', 'dias_restantes', 'origen', 'nota',
        ], array_keys($comisiones));
        // El super admin SÍ ve origen y nota (son para su panel).
        $this->assertSame('cortesia', $comisiones['origen']);
        $this->assertSame('Interna', $comisiones['nota']);
    }

    public function test_el_admin_de_negocio_no_puede_leer_origen_ni_nota(): void
    {
        $this->fijarModulo($this->negocioA, 'comisiones', [
            'activo' => 1, 'origen' => 'pagado_fuera', 'nota' => 'Secreto del super admin',
        ]);

        // El endpoint que trae origen/nota es solo del super admin.
        $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/superadmin/modulos-de-negocio?id_negocio='.$this->negocioA)
            ->assertJsonPath('error', 1)
            ->assertJsonPath('data', []);
    }

    public function test_modulos_por_vencer_whitelist_maximo_20_y_ventanas_de_7_dias(): void
    {
        // 25 negocios con un módulo que vence dentro de 3 días: deben salir 20.
        for ($i = 0; $i < 25; $i++) {
            $neg = $this->crearNegocio('Masivo '.$i);
            $this->fijarModulo($neg, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::today()->copy()->addDays(3)->toDateString()]);
        }

        // Fuera de ventana: vence en 8 días y vencido hace 8 días (no deben salir).
        $this->fijarModulo($this->negocioA, 'ia', ['activo' => 1, 'vigente_hasta' => Carbon::today()->copy()->addDays(8)->toDateString()]);
        $this->fijarModulo($this->negocioB, 'chatbot', ['activo' => 1, 'vigente_hasta' => Carbon::today()->copy()->subDays(8)->toDateString()]);

        $modulos = $this->withSession($this->sesionSuperAdmin())
            ->getJson('request/superadmin/modulos-por-vencer')
            ->assertJsonPath('error', 0)
            ->json('data.modulos');

        $this->assertCount(20, $modulos);
        $this->assertSame(['nombre_negocio', 'nombre_modulo', 'vigente_hasta', 'dias', 'origen'], array_keys($modulos[0]));
    }

    public function test_modulos_por_vencer_incluye_vencidos_recientes(): void
    {
        $this->fijarModulo($this->negocioA, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::today()->copy()->subDays(6)->toDateString()]);

        $modulos = $this->withSession($this->sesionSuperAdmin())
            ->getJson('request/superadmin/modulos-por-vencer')->json('data.modulos');

        $this->assertCount(1, $modulos);
        $this->assertSame(-6, $modulos[0]['dias']);
    }

    /* ================= 5) EL COMANDO ESTÁ PROGRAMADO UNA VEZ AL DÍA ================= */

    public function test_el_comando_esta_programado_una_vez_al_dia_sin_chocar_con_otros(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        $expresiones = [];
        $nuestra = null;

        foreach ($schedule->events() as $evento) {
            $expresiones[] = $evento->expression;

            if (str_contains($evento->command ?? '', 'modulos:procesar-vencimientos')) {
                $nuestra = $evento->expression;
            }
        }

        $this->assertNotNull($nuestra, 'El comando debe estar programado');
        $this->assertSame('30 6 * * *', $nuestra, 'Una vez al día a las 06:30');

        // Ninguna otra tarea comparte exactamente esa expresión.
        $this->assertSame(1, count(array_filter($expresiones, fn ($e) => $e === '30 6 * * *')));
    }
}
