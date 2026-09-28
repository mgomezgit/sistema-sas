<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Mail\NuevaSolicitudPublica;
use App\Mail\ReservaConfirmada;
use App\Mail\ReservaEstadoActualizado;
use App\Mail\ReservaRecordatorio;
use App\Mail\ResumenStockBajo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * XSS por el camino real: el payload entra por donde entraría un atacante
 * (el formulario anónimo de agendar, el nombre de un negocio registrado desde
 * la página pública) y se verifica cómo sale en cada pantalla que se arma en
 * el servidor, en cada correo y en cada Excel.
 *
 * Las pantallas que se arman en el navegador (tablas, campanas, agenda,
 * modal, panel de detalle) no se pueden ejecutar desde PHPUnit: se verificaron
 * con un navegador real (Playwright) durante la auditoría, y sus guardianes
 * estáticos viven en XssGuardianTest.
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * M2 — En app/dashboard.blade.php el nombre del cliente pasó a
 *      {!! $cita['nombre_cliente'] !!}: esta clase + XssGuardianTest, 15 tests,
 *      13 passed, 2 FAILED — el guardián de {!! !!} y
 *      test_una_solicitud_publica_con_payload_se_ve_escapada_en_el_dashboard_del_admin
 *      ("el payload aparece SIN escapar"). Restaurado: 15 passed.
 * M3 — En emails/nueva-solicitud-publica.blade.php las notas pasaron a
 *      {!! !!}: test_cada_correo_con_datos_de_usuario_sale_escapado FALLÓ
 *      ("Correo NuevaSolicitudPublica: el payload aparece SIN escapar").
 *      Restaurado: pasa.
 * M4 — ReporteVentasExport dejó de extender BinderCeldasSeguras: 9 tests,
 *      8 passed, 1 FAILED —
 *      test_el_reporte_de_ventas_neutraliza_textos_que_parecen_formulas ("D2
 *      debe ser texto, no fórmula"). Es decir, sin el binder el nombre del
 *      cliente SÍ se guardaba como fórmula real. Restaurado: 9 passed.
 */
class XssHttpTest extends TestCase
{
    use RefreshDatabase;

    const PAYLOAD = '<img src=x onerror=alert(1)>';

    const ESCAPADO = '&lt;img src=x onerror=alert(1)&gt;';

    private int $negocio;

    private int $idRecurso;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocio = DB::table('negocios')->insertGetId([
            'nombre_negocio' => 'Spa Normal',
            'slug' => 'spa-normal',
            'rubro' => 'spa',
            'dias_atencion' => '0,1,2,3,4,5,6',
            'hora_apertura' => '00:00:00',
            'hora_cierre' => '23:59:00',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $this->idRecurso = DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $this->negocio,
            'nombre' => 'Masaje',
            'duracion_minutos' => 60,
            'precio' => 50000,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function sesionAdmin(array $extra = []): array
    {
        return array_merge([
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 1,
            'usuario' => 'admin',
            'nombre_usuario' => 'Admin',
            'tenant_id' => $this->negocio,
            'id_rol' => 1,
            'nombre_negocio_sesion' => 'Spa Normal',
        ], $extra);
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

    /** El payload no aparece crudo en ningún lado del HTML y sí aparece escapado. */
    private function assertEscapado(string $html, string $donde): void
    {
        $this->assertStringNotContainsString(self::PAYLOAD, $html, "$donde: el payload aparece SIN escapar");
        $this->assertStringContainsString(self::ESCAPADO, $html, "$donde: el payload no aparece escapado (¿se pintó siquiera?)");
    }

    /* ================= 1) ENTRADA ANÓNIMA: SOLICITUD PÚBLICA ================= */

    /**
     * El visitante anónimo agenda con el payload en nombre, teléfono y notas;
     * el admin abre su dashboard, que lista las próximas citas de hoy
     * armadas en el servidor.
     */
    public function test_una_solicitud_publica_con_payload_se_ve_escapada_en_el_dashboard_del_admin(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::today()->setTime(6, 0));

        $respuesta = $this->postJson('publico/spa-normal/agendar', [
            'nombre' => self::PAYLOAD,
            'telefono' => self::PAYLOAD,
            'id_recurso' => $this->idRecurso,
            'fecha_reserva' => Carbon::today()->format('Y-m-d'),
            'hora_inicio' => '10:00',
            'notas' => self::PAYLOAD,
        ]);
        $this->assertSame(0, $respuesta->json('error'), (string) $respuesta->getContent());

        // Se guarda tal cual: escapar es trabajo de la salida, no de la entrada.
        $this->assertSame(self::PAYLOAD, DB::table('clientes')->where('tenant_id', $this->negocio)->value('nombre'));

        $html = $this->withSession($this->sesionAdmin())->get('backoffice/dashboard')->assertStatus(200)->getContent();

        $this->assertEscapado($html, 'Dashboard del admin');
    }

    /* ================= 2) ENTRADA ANÓNIMA: NOMBRE DE NEGOCIO ================= */

    public function test_un_nombre_de_negocio_con_payload_se_ve_escapado_en_la_pagina_publica(): void
    {
        DB::table('negocios')->where('id_negocio', $this->negocio)->update(['nombre_negocio' => self::PAYLOAD]);

        $html = $this->get('reservar/spa-normal')->assertStatus(200)->getContent();

        $this->assertEscapado($html, 'Página pública del negocio');
    }

    public function test_nombre_de_negocio_y_de_usuario_con_payload_se_ven_escapados_en_el_layout(): void
    {
        $html = $this->withSession($this->sesionAdmin([
            'nombre_negocio_sesion' => self::PAYLOAD,
            'nombre_usuario' => self::PAYLOAD,
        ]))->get('backoffice/clientes')->assertStatus(200)->getContent();

        $this->assertEscapado($html, 'Sidebar y navbar del layout');
    }

    public function test_el_selector_de_negocios_del_super_admin_escapa_el_nombre(): void
    {
        DB::table('negocios')->where('id_negocio', $this->negocio)->update(['nombre_negocio' => self::PAYLOAD]);

        $html = $this->withSession($this->sesionSuperAdmin())->get('backoffice/usuarios')->assertStatus(200)->getContent();

        $this->assertEscapado($html, 'Selector de negocios en Usuarios');
    }

    public function test_los_selectores_de_comisiones_escapan_empleado_y_servicio(): void
    {
        $idModulo = DB::table('modulos_plataforma')->where('clave', 'comisiones')->value('id_modulo');
        DB::table('negocio_modulos')->insert([
            'tenant_id' => $this->negocio,
            'id_modulo' => $idModulo,
            'activo' => true,
            'fecha_activacion' => date('Y-m-d H:i:s'),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
        ]);
        DB::table('empleados')->insert([
            'tenant_id' => $this->negocio,
            'nombre' => self::PAYLOAD,
            'telefono' => '3000000000',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
        DB::table('recursos_reservables')->where('id_recurso', $this->idRecurso)->update(['nombre' => self::PAYLOAD]);

        $html = $this->withSession($this->sesionAdmin())->get('backoffice/comisiones')->assertStatus(200)->getContent();

        $this->assertEscapado($html, 'Selectores de Comisiones');
    }

    /* ================= 3) CORREOS ================= */

    private function reservaConPayload(): array
    {
        return [
            'id_reserva' => 1,
            'nombre_cliente' => self::PAYLOAD,
            'telefono_cliente' => self::PAYLOAD,
            'nombre_recurso' => self::PAYLOAD,
            'nombre_empleado' => self::PAYLOAD,
            'fecha_reserva' => '2026-10-01',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'notas' => self::PAYLOAD,
        ];
    }

    public function test_cada_correo_con_datos_de_usuario_sale_escapado(): void
    {
        $correos = [
            'NuevaSolicitudPublica' => new NuevaSolicitudPublica($this->reservaConPayload(), self::PAYLOAD),
            'ReservaConfirmada' => new ReservaConfirmada($this->reservaConPayload(), self::PAYLOAD),
            'ReservaEstadoActualizado' => new ReservaEstadoActualizado($this->reservaConPayload(), self::PAYLOAD, 'confirmada'),
            'ReservaRecordatorio' => new ReservaRecordatorio($this->reservaConPayload(), self::PAYLOAD),
            'ResumenStockBajo' => new ResumenStockBajo(self::PAYLOAD, [
                ['nombre' => self::PAYLOAD, 'cantidad_actual' => 1, 'cantidad_minima' => 5, 'urgencia' => 'bajo'],
            ]),
        ];

        foreach ($correos as $nombre => $correo) {
            $this->assertEscapado($correo->render(), "Correo $nombre");
        }
    }

    /* ================= 4) EXCEL: INYECCIÓN DE FÓRMULAS ================= */

    /** Descarga el Excel real (sin Excel::fake) y devuelve su primera hoja. */
    private function descargarHoja(string $url)
    {
        $respuesta = $this->withSession($this->sesionAdmin())->get($url);
        $respuesta->assertStatus(200);

        return IOFactory::load($respuesta->baseResponse->getFile()->getPathname())->getActiveSheet();
    }

    private function insertarVentaCon(string $nombreCliente, string $nombreEmpleado): void
    {
        $idCliente = DB::table('clientes')->insertGetId([
            'tenant_id' => $this->negocio,
            'nombre' => $nombreCliente,
            'telefono' => '3000000000',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
        $idEmpleado = DB::table('empleados')->insertGetId([
            'tenant_id' => $this->negocio,
            'nombre' => $nombreEmpleado,
            'telefono' => '3000000000',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
        DB::table('reservas')->insert([
            'tenant_id' => $this->negocio,
            'id_cliente' => $idCliente,
            'id_recurso' => $this->idRecurso,
            'id_empleado' => $idEmpleado,
            'fecha_reserva' => '2026-01-10',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado_reserva' => 'completada',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    /**
     * Un cliente que agenda como '=HYPERLINK(...)' no puede dejar una fórmula
     * viva en el Excel que abre el admin: la celda debe ser TEXTO, con el
     * mismo valor (sin apóstrofo metido dentro) y con "quote prefix", que es
     * lo que Excel hace con un apóstrofo inicial.
     */
    public function test_el_reporte_de_ventas_neutraliza_textos_que_parecen_formulas(): void
    {
        $formula = '=HYPERLINK("http://atacante.test","Ver factura")';
        $this->insertarVentaCon($formula, '@SUM(1+1)');

        $hoja = $this->descargarHoja('request/reporte/ventas-descargar?fecha_inicio=2026-01-01&fecha_fin=2026-01-31');

        // Fila 2 (la 1 son los encabezados). D = Cliente, F = Empleado, H = Precio.
        foreach (['D2' => $formula, 'F2' => '@SUM(1+1)'] as $celda => $valorOriginal) {
            $c = $hoja->getCell($celda);
            $this->assertSame(DataType::TYPE_STRING, $c->getDataType(), "$celda debe ser texto, no fórmula");
            $this->assertSame($valorOriginal, $c->getValue(), "$celda debe conservar el texto tal cual");
            $this->assertTrue($hoja->getStyle($celda)->getQuotePrefix(), "$celda debe llevar quote prefix");
        }

        // Los números siguen siendo números.
        $this->assertSame(DataType::TYPE_NUMERIC, $hoja->getCell('H2')->getDataType());
    }

    public function test_el_reporte_de_servicios_neutraliza_textos_que_parecen_formulas(): void
    {
        $this->insertarVentaCon('Cliente Normal', 'Empleado Normal');
        DB::table('recursos_reservables')->where('id_recurso', $this->idRecurso)->update(['nombre' => '+cmd|\' /C calc\'!A0']);

        $hoja = $this->descargarHoja('request/reporte/servicios-descargar?fecha_inicio=2026-01-01&fecha_fin=2026-01-31');

        $c = $hoja->getCell('A2');
        $this->assertSame(DataType::TYPE_STRING, $c->getDataType());
        $this->assertSame('+cmd|\' /C calc\'!A0', $c->getValue());
        $this->assertTrue($hoja->getStyle('A2')->getQuotePrefix());
    }

    public function test_un_texto_normal_no_se_marca_ni_cambia(): void
    {
        $this->insertarVentaCon('Ana María', 'Luisa');

        $hoja = $this->descargarHoja('request/reporte/ventas-descargar?fecha_inicio=2026-01-01&fecha_fin=2026-01-31');

        $this->assertSame('Ana María', $hoja->getCell('D2')->getValue());
        $this->assertFalse($hoja->getStyle('D2')->getQuotePrefix());
    }
}
