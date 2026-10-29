<?php

namespace Tests\Feature;

use App\Exports\BinderCeldasSeguras;
use App\Exports\LibroPagosExport;
use App\Mail\ComprobantePagoPlataforma;
use App\Http\Middleware\VerificarSesion;
use App\Service\SvcModulo;
use App\Service\SvcPagoPlataforma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Libro de pagos y comprobante (interno del super admin). HTTP real con
 * withSession, SQLite en memoria, reloj congelado, Mail::fake.
 */
class PagoPlataformaTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 10:00:00');
        Mail::fake();

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

    private function crearNegocio(string $nombre, int $estado = 1, ?string $acento = 'azul'): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'rubro' => 'spa',
            'color_acento' => $acento,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => $estado,
        ]);
    }

    private function crearAdmin(int $tenantId, string $email, int $estado = 1, int $idRol = 1): int
    {
        return DB::table('usuarios')->insertGetId([
            'tenant_id' => $tenantId,
            'id_rol' => $idRol,
            'usuario' => 'u'.uniqid(),
            'nombre' => 'Admin',
            'email' => $email,
            'clave' => bcrypt('Clave-2026'),
            'usuario_registra' => 'test',
            'fecha_registro' => now(),
            'estado' => $estado,
        ]);
    }

    private function sesionSuper(): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 99, 'usuario' => 'super', 'nombre_usuario' => 'Super',
            'tenant_id' => null, 'id_rol' => 3,
        ];
    }

    private function sesionAdmin(int $tenantId): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 500 + $tenantId, 'usuario' => 'admin', 'nombre_usuario' => 'Admin',
            'tenant_id' => $tenantId, 'id_rol' => 1,
        ];
    }

    private function sesionEmpleado(int $tenantId): array
    {
        return ['id_rol' => 2] + $this->sesionAdmin($tenantId);
    }

    private function activar(array $cuerpo)
    {
        return $this->withSession($this->sesionSuper())->postJson('request/superadmin/activar-modulo', $cuerpo);
    }

    private function activarConPago(int $negocio, array $extra = [])
    {
        return $this->activar(array_merge([
            'id_negocio' => $negocio,
            'clave_modulo' => 'comisiones',
            'origen' => 'pagado_fuera',
            'vigente_hasta' => Carbon::today()->copy()->addMonth()->toDateString(),
            'registrar_pago' => '1',
            'monto' => '85000',
            'metodo' => 'transferencia',
            'referencia' => 'TRF-001',
        ], $extra));
    }

    /* ================= REGISTRAR ================= */

    public function test_activar_con_pago_crea_el_registro_activa_el_modulo_y_encola_un_comprobante(): void
    {
        $this->crearAdmin($this->negocioA, 'admin@a.test');

        $this->activarConPago($this->negocioA)->assertJsonPath('error', 0);

        $pago = DB::table('pagos_plataforma')->where('tenant_id', $this->negocioA)->first();
        $this->assertNotNull($pago);
        $this->assertSame(1, (int) $pago->consecutivo);
        $this->assertSame('Cálculo de comisiones', $pago->concepto);
        $this->assertSame(85000, (int) $pago->monto);
        $this->assertSame(Carbon::today()->toDateString(), Carbon::parse($pago->periodo_desde)->toDateString());
        $this->assertSame(Carbon::today()->copy()->addMonth()->toDateString(), Carbon::parse($pago->periodo_hasta)->toDateString());

        $this->assertTrue(app(SvcModulo::class)->estaActivo($this->negocioA, 'comisiones'));

        Mail::assertQueued(ComprobantePagoPlataforma::class, 1);
    }

    public function test_con_cortesia_no_crea_pago_ni_correo(): void
    {
        $this->crearAdmin($this->negocioA, 'admin@a.test');

        $this->activar([
            'id_negocio' => $this->negocioA, 'clave_modulo' => 'comisiones', 'origen' => 'cortesia',
            'registrar_pago' => '1', 'monto' => '85000', 'metodo' => 'transferencia',
        ])->assertJsonPath('error', 0);

        $this->assertDatabaseCount('pagos_plataforma', 0);
        Mail::assertNothingQueued();
    }

    public function test_casilla_marcada_sin_monto_es_rechazada_sin_cambiar_nada(): void
    {
        $r = $this->activar([
            'id_negocio' => $this->negocioA, 'clave_modulo' => 'comisiones', 'origen' => 'pagado_fuera',
            'registrar_pago' => '1', 'metodo' => 'transferencia',
        ]);

        $this->assertSame(1, $r->json('error'));
        $this->assertDatabaseCount('pagos_plataforma', 0);
        $this->assertDatabaseMissing('negocio_modulos', ['tenant_id' => $this->negocioA]);
    }

    public function test_rechaza_montos_invalidos_y_metodo_fuera_de_lista(): void
    {
        foreach (['0', '-5', '12.5', 'texto', '100000001'] as $monto) {
            $r = $this->activarConPago($this->negocioA, ['monto' => $monto]);
            $this->assertSame(1, $r->json('error'), "El monto $monto debe rechazarse");
        }

        $this->assertSame(1, $this->activarConPago($this->negocioA, ['metodo' => 'bitcoin'])->json('error'));

        $this->assertDatabaseCount('pagos_plataforma', 0);
    }

    /* ================= ATOMICIDAD Y CONSECUTIVOS ================= */

    public function test_si_falla_el_registro_no_queda_modulo_activado_ni_consecutivo_gastado(): void
    {
        // Trigger que revienta al insertar en pagos_plataforma: simula un fallo
        // DESPUÉS de activar el módulo, dentro de la misma transacción.
        DB::statement("CREATE TRIGGER t_boom AFTER INSERT ON pagos_plataforma BEGIN SELECT RAISE(ABORT, 'boom'); END");

        $r = $this->activarConPago($this->negocioA);

        $this->assertSame(1, $r->json('error'));
        $this->assertDatabaseCount('pagos_plataforma', 0);
        $this->assertFalse(app(SvcModulo::class)->estaActivo($this->negocioA, 'comisiones'), 'El módulo NO debe quedar activo');

        DB::statement('DROP TRIGGER t_boom');

        // El consecutivo no se gastó: el primer pago bueno es el 1.
        $this->crearAdmin($this->negocioA, 'admin@a.test');
        $this->activarConPago($this->negocioA)->assertJsonPath('error', 0);
        $this->assertSame(1, (int) DB::table('pagos_plataforma')->where('tenant_id', $this->negocioA)->value('consecutivo'));
    }

    public function test_consecutivos_consecutivos_y_no_se_reutiliza_el_de_un_anulado(): void
    {
        $this->crearAdmin($this->negocioA, 'admin@a.test');
        $this->crearAdmin($this->negocioB, 'admin@b.test');

        $this->activarConPago($this->negocioA)->assertJsonPath('error', 0); // 1
        $this->activarConPago($this->negocioB)->assertJsonPath('error', 0); // 2
        $this->activarConPago($this->negocioA, ['clave_modulo' => 'ia'])->assertJsonPath('error', 0); // 3

        $this->assertSame([1, 2, 3], DB::table('pagos_plataforma')->orderBy('consecutivo')->pluck('consecutivo')->map(fn ($c) => (int) $c)->all());

        // Anular el MÁS ALTO (3) no libera su número: el siguiente es 4, no 3.
        // Si se reutilizara el 3 (anulado), chocaría con el índice único y el
        // alta fallaría; por eso esta prueba custodia que nunca se reutiliza.
        $id3 = DB::table('pagos_plataforma')->where('consecutivo', 3)->value('id_pago');
        app(SvcPagoPlataforma::class)->anular((int) $id3, 99, 'prueba de anulacion');

        $this->activarConPago($this->negocioB, ['clave_modulo' => 'chatbot'])->assertJsonPath('error', 0);
        $nuevo = (int) DB::table('pagos_plataforma')->where('tenant_id', $this->negocioB)->where('concepto', 'Asistente virtual (chatbot)')->value('consecutivo');
        $this->assertSame(4, $nuevo, 'El nuevo pago debe tomar el 4, nunca reutilizar el 3 anulado');
    }

    public function test_la_base_rechaza_un_consecutivo_duplicado(): void
    {
        $this->crearAdmin($this->negocioA, 'admin@a.test');
        $this->activarConPago($this->negocioA)->assertJsonPath('error', 0);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('pagos_plataforma')->insert([
            'tenant_id' => $this->negocioA, 'concepto' => 'Duplicado', 'periodo_desde' => Carbon::today()->toDateString(),
            'monto' => 1000, 'metodo' => 'otro', 'consecutivo' => 1, 'id_usuario_registra' => 99, 'fecha_registro' => now(),
        ]);
    }

    /* ================= COMPROBANTE ================= */

    public function test_el_comprobante_va_escapado_sin_la_palabra_factura_como_titulo(): void
    {
        $pago = [
            'id_pago' => 1, 'tenant_id' => $this->negocioA, 'consecutivo' => 'CP-0001', 'fecha' => '2026-11-10',
            'nombre_negocio' => '<img src=x onerror=alert(1)> Spa', 'color_acento' => 'verde',
            'concepto' => 'Cálculo de comisiones', 'periodo' => '2026-11-10 a 2026-12-10',
            'monto_formato' => '$85.000', 'metodo' => 'transferencia', 'referencia' => '=HACK()',
        ];

        $html = (new ComprobantePagoPlataforma($pago))->render();

        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;img', $html);
        $this->assertStringContainsString('no reemplaza una factura de venta', $html);
        // "Comprobante de pago" es el título; "Factura" no aparece como título.
        $this->assertStringContainsString('Comprobante de pago', $html);
        $this->assertStringNotContainsString('<h1 style="margin:0; font-size:20px; color:#ffffff; font-weight:bold;">Factura', $html);
        $this->assertStringContainsString('$85.000', $html);
        $this->assertStringContainsString('#16a34a', $html); // acento verde
    }

    public function test_el_comprobante_llega_solo_a_admins_activos_del_negocio_incluso_suspendido(): void
    {
        $suspendido = $this->crearNegocio('Suspendido', 0);
        $this->crearAdmin($suspendido, 'activo@susp.test', 1);
        $this->crearAdmin($suspendido, 'inactivo@susp.test', 0);
        $this->crearAdmin($suspendido, 'empleado@susp.test', 1, 2);
        $this->crearAdmin($this->negocioA, 'otro@a.test', 1);

        $this->activarConPago($suspendido)->assertJsonPath('error', 0);

        Mail::assertQueued(ComprobantePagoPlataforma::class, function ($mail) {
            return $mail->hasTo('activo@susp.test')
                && ! $mail->hasTo('inactivo@susp.test')
                && ! $mail->hasTo('empleado@susp.test')
                && ! $mail->hasTo('otro@a.test');
        });
    }

    public function test_sin_admin_con_correo_el_pago_queda_sin_enviar(): void
    {
        // Sin admin: el pago se crea pero el comprobante no se envía.
        $this->activarConPago($this->negocioA)->assertJsonPath('error', 0);

        Mail::assertNothingQueued();
        $this->assertNull(DB::table('pagos_plataforma')->where('tenant_id', $this->negocioA)->value('comprobante_enviado_en'));
    }

    public function test_reenviar_encola_otro_y_solo_para_vigentes(): void
    {
        $this->crearAdmin($this->negocioA, 'admin@a.test');
        $this->activarConPago($this->negocioA)->assertJsonPath('error', 0);
        $id = DB::table('pagos_plataforma')->where('tenant_id', $this->negocioA)->value('id_pago');

        $this->withSession($this->sesionSuper())->postJson('request/superadmin/pagos/reenviar', ['id_pago' => $id])->assertJsonPath('error', 0);
        Mail::assertQueued(ComprobantePagoPlataforma::class, 2); // el del alta + el reenvío

        // Anulado: no se puede reenviar.
        app(SvcPagoPlataforma::class)->anular((int) $id, 99, 'anulado para prueba');
        $this->withSession($this->sesionSuper())->postJson('request/superadmin/pagos/reenviar', ['id_pago' => $id])->assertJsonPath('error', 1);
    }

    /* ================= ANULAR ================= */

    public function test_anular_requiere_motivo_valido_no_dos_veces_y_no_cambia_la_vigencia(): void
    {
        $this->crearAdmin($this->negocioA, 'admin@a.test');
        $this->activarConPago($this->negocioA)->assertJsonPath('error', 0);
        $id = DB::table('pagos_plataforma')->where('tenant_id', $this->negocioA)->value('id_pago');
        $vigenciaAntes = DB::table('negocio_modulos')->where('tenant_id', $this->negocioA)->value('vigente_hasta');

        // Motivo muy corto.
        $this->withSession($this->sesionSuper())->postJson('request/superadmin/pagos/anular', ['id_pago' => $id, 'motivo' => 'no'])
            ->assertJsonPath('error', 1);

        // Motivo válido.
        $this->withSession($this->sesionSuper())->postJson('request/superadmin/pagos/anular', ['id_pago' => $id, 'motivo' => 'Pago devuelto al negocio'])
            ->assertJsonPath('error', 0);
        $this->assertNotNull(DB::table('pagos_plataforma')->where('id_pago', $id)->value('anulado_en'));

        // No dos veces.
        $this->withSession($this->sesionSuper())->postJson('request/superadmin/pagos/anular', ['id_pago' => $id, 'motivo' => 'Otro intento'])
            ->assertJsonPath('error', 1);

        // La vigencia del módulo NO cambió.
        $this->assertSame($vigenciaAntes, DB::table('negocio_modulos')->where('tenant_id', $this->negocioA)->value('vigente_hasta'));
        $this->assertTrue(app(SvcModulo::class)->estaActivo($this->negocioA, 'comisiones'));

        // Anular no envía correo (solo el del alta).
        Mail::assertQueued(ComprobantePagoPlataforma::class, 1);
    }

    /* ================= TOTALES Y FILTROS ================= */

    public function test_el_total_del_mes_suma_solo_vigentes_del_mes_filtrado(): void
    {
        $this->crearAdmin($this->negocioA, 'admin@a.test');
        $this->activarConPago($this->negocioA, ['monto' => '85000'])->assertJsonPath('error', 0);
        $this->activarConPago($this->negocioA, ['clave_modulo' => 'ia', 'monto' => '15000'])->assertJsonPath('error', 0);

        // Anular uno: sale del total.
        $idIa = DB::table('pagos_plataforma')->where('concepto', 'Asistente creativo (IA)')->value('id_pago');
        app(SvcPagoPlataforma::class)->anular((int) $idIa, 99, 'anulado de prueba');

        $resp = $this->withSession($this->sesionSuper())->getJson('request/superadmin/pagos/listar?mes=2026-11')->assertJsonPath('error', 0);
        $this->assertSame(85000, $resp->json('data.totales.total'));
        $this->assertSame(1, $resp->json('data.totales.cantidad'));
    }

    public function test_mes_invalido_es_rechazado(): void
    {
        foreach (['2026-13', 'texto', '2026-1'] as $mes) {
            $this->withSession($this->sesionSuper())->getJson('request/superadmin/pagos/listar?mes='.$mes)
                ->assertJsonPath('error', 1);
        }
    }

    public function test_el_resumen_del_dashboard_sigue_el_mes_congelado(): void
    {
        $this->crearAdmin($this->negocioA, 'admin@a.test');
        $this->activarConPago($this->negocioA, ['monto' => '50000'])->assertJsonPath('error', 0);

        $this->assertSame(50000, $this->withSession($this->sesionSuper())->getJson('request/superadmin/pagos/resumen')->json('data.resumen.total'));

        // Otro mes: la tarjeta no cuenta ese pago.
        Carbon::setTestNow('2026-12-10 10:00:00');
        $this->assertSame(0, $this->withSession($this->sesionSuper())->getJson('request/superadmin/pagos/resumen')->json('data.resumen.total'));
    }

    /* ================= EXPORTACIÓN ================= */

    public function test_la_exportacion_neutraliza_formulas_de_excel(): void
    {
        $export = new LibroPagosExport([['CP-0001', '2026-11-10', 'Negocio', 'Concepto', 'periodo', 85000, 'transferencia', '=HYPERLINK("http://malo")', 'Vigente']]);

        $this->assertInstanceOf(BinderCeldasSeguras::class, $export);

        // El binder marca como texto (quote prefix) un valor que empieza con "=".
        $hoja = (new Spreadsheet)->getActiveSheet();
        $celda = $hoja->getCell('A1');
        $export->bindValue($celda, '=HYPERLINK("http://malo")');
        $this->assertTrue($celda->getStyle()->getQuotePrefix());
    }

    /* ================= AISLAMIENTO Y PRIVACIDAD ================= */

    public function test_admin_empleado_y_anonimo_no_acceden_a_ningun_endpoint_del_libro(): void
    {
        $this->crearAdmin($this->negocioA, 'admin@a.test');
        $this->activarConPago($this->negocioA)->assertJsonPath('error', 0);
        $id = DB::table('pagos_plataforma')->value('id_pago');

        $endpoints = [
            ['GET', 'request/superadmin/pagos/listar', null],
            ['GET', 'request/superadmin/pagos/resumen', null],
            ['POST', 'request/superadmin/pagos/anular', ['id_pago' => $id, 'motivo' => 'intento ajeno']],
            ['POST', 'request/superadmin/pagos/reenviar', ['id_pago' => $id]],
        ];

        foreach (['admin' => $this->sesionAdmin($this->negocioA), 'empleado' => $this->sesionEmpleado($this->negocioA), 'anonimo' => null] as $quien => $sesion) {
            foreach ($endpoints as [$metodo, $url, $cuerpo]) {
                $cuerpo = $cuerpo ?? [];
                $req = $sesion === null ? $this : $this->withSession($sesion);
                $resp = $metodo === 'GET' ? $req->getJson($url) : $req->postJson($url, $cuerpo);
                $this->assertSame(1, $resp->json('error'), "$quien no debe usar $metodo $url");
                $this->assertSame([], (array) $resp->json('data'), "$quien no debe recibir datos de $url");
            }
        }

        // Nada se anuló por esos intentos.
        $this->assertNull(DB::table('pagos_plataforma')->where('id_pago', $id)->value('anulado_en'));
    }

    public function test_el_listado_solo_devuelve_campos_permitidos(): void
    {
        $this->crearAdmin($this->negocioA, 'admin@a.test');
        $this->activarConPago($this->negocioA)->assertJsonPath('error', 0);

        $pagos = $this->withSession($this->sesionSuper())->getJson('request/superadmin/pagos/listar')->json('data.pagos');

        $this->assertSame([
            'id_pago', 'consecutivo', 'fecha', 'nombre_negocio', 'concepto', 'periodo',
            'monto', 'monto_formato', 'metodo', 'referencia', 'anulado', 'comprobante_enviado',
        ], array_keys($pagos[0]));
    }
}
