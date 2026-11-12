<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Service\SvcModulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\ActivaModulos;
use Tests\TestCase;

/**
 * Reportes convertido en módulo de pago apagable por negocio.
 *
 * Cubre lo que el módulo apaga (vista, endpoints, sidebar, tour) y la pantalla
 * de "no disponible" compartida, además del aislamiento entre negocios: un
 * negocio sin el módulo no toca a otro que sí lo tiene. El camino feliz (usar
 * Reportes con el módulo activo) vive en ReporteTest.
 *
 * Reloj congelado: el módulo "vencido" se prueba con vigente_hasta = ayer, y
 * eso depende de "hoy".
 */
class ModuloReportesTest extends TestCase
{
    use ActivaModulos;
    use RefreshDatabase;

    private int $negocioConModulo;

    private int $negocioSinModulo;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 6, 15, 10, 0, 0));

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioConModulo = $this->crearNegocio('Spa Con Modulo');
        $this->negocioSinModulo = $this->crearNegocio('Spa Sin Modulo');

        $this->activarModulo($this->negocioConModulo, 'reportes');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'rubro' => 'spa',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function sesionAdmin(int $tenantId, string $nombreNegocio = 'Spa Prueba'): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 1,
            'usuario' => 'admin.test',
            'nombre_usuario' => 'Admin Test',
            'nombre_negocio_sesion' => $nombreNegocio,
            'tenant_id' => $tenantId,
            'id_rol' => 1,
        ];
    }

    private function sesionEmpleado(int $tenantId): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 2,
            'usuario' => 'empleado.test',
            'nombre_usuario' => 'Empleado Test',
            'tenant_id' => $tenantId,
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

    /** Las cuatro rutas request/* del módulo y la del tour. */
    private function endpointsDelModulo(): array
    {
        return [
            ['get', 'request/reporte/ventas-preview?fecha_inicio=2026-01-01&fecha_fin=2026-01-31'],
            ['get', 'request/reporte/ventas-descargar?fecha_inicio=2026-01-01&fecha_fin=2026-01-31'],
            ['get', 'request/reporte/servicios-preview?fecha_inicio=2026-01-01&fecha_fin=2026-01-31'],
            ['get', 'request/reporte/servicios-descargar?fecha_inicio=2026-01-01&fecha_fin=2026-01-31'],
            ['post', 'request/negocio/marcar-reportes-tour'],
        ];
    }

    /* ================= NEGOCIO SIN MÓDULO: VISTA ================= */

    public function test_sin_modulo_la_vista_de_reportes_redirige_a_no_disponible(): void
    {
        foreach (['backoffice/reportes/ventas', 'backoffice/reportes/servicios'] as $ruta) {
            $this->withSession($this->sesionAdmin($this->negocioSinModulo))
                ->get($ruta)
                ->assertRedirect(url('backoffice/modulo/reportes'));
        }
    }

    /* ================= NEGOCIO SIN MÓDULO: ENDPOINTS ================= */

    public function test_sin_modulo_los_endpoints_responden_el_error_del_modulo(): void
    {
        Excel::fake();

        foreach ($this->endpointsDelModulo() as [$metodo, $url]) {
            $respuesta = $this->withSession($this->sesionAdmin($this->negocioSinModulo))->json($metodo, $url);

            $respuesta->assertOk()->assertJsonPath('error', 1);
            $this->assertStringContainsStringIgnoringCase('módulo', $respuesta->json('mensaje'));
        }
    }

    public function test_sin_modulo_el_tour_de_reportes_no_marca_nada(): void
    {
        $this->withSession($this->sesionAdmin($this->negocioSinModulo))
            ->postJson('request/negocio/marcar-reportes-tour')
            ->assertJsonPath('error', 1);

        $this->assertSame(0, DB::table('negocios')->where('id_negocio', $this->negocioSinModulo)->value('reportes_tour_visto'));
    }

    /* ================= NEGOCIO SIN MÓDULO: SIDEBAR ================= */

    public function test_sin_modulo_el_sidebar_muestra_candado_y_no_el_submenu(): void
    {
        $html = $this->withSession($this->sesionAdmin($this->negocioSinModulo))
            ->get('backoffice/dashboard')
            ->assertOk()
            ->getContent();

        // El candado apunta a la pantalla de no disponible...
        $this->assertStringContainsString('backoffice/modulo/reportes', $html);
        $this->assertStringContainsString('menu-item-bloqueado', $html);
        // ...y el submenú de reportes (Ventas / Por servicio) no se pinta. Se
        // comprueba por el id del submenú y por el enlace real (href absoluto),
        // no por la cadena suelta "backoffice/reportes/ventas", que también
        // aparece en el JS del onboarding como destino de un paso.
        $this->assertStringNotContainsString('id="submenu-reportes"', $html);
        $this->assertStringNotContainsString('href="'.url('backoffice/reportes/ventas').'"', $html);
    }

    public function test_con_modulo_el_sidebar_muestra_el_submenu_y_no_el_candado(): void
    {
        $html = $this->withSession($this->sesionAdmin($this->negocioConModulo))
            ->get('backoffice/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="submenu-reportes"', $html);
        $this->assertStringContainsString('backoffice/reportes/ventas', $html);
        // El candado de reportes NO aparece (no hay enlace a modulo/reportes).
        $this->assertStringNotContainsString('backoffice/modulo/reportes', $html);
    }

    /* ================= MÓDULO VENCIDO ================= */

    public function test_modulo_vencido_se_comporta_como_apagado_con_mensaje_de_vencido(): void
    {
        // Vigente hasta ayer: activo = 1 pero la vigencia ya pasó.
        $ayer = Carbon::yesterday()->toDateString();
        $this->activarModulo($this->negocioSinModulo, 'reportes', $ayer);

        // La vista redirige igual que si estuviera apagado.
        $this->withSession($this->sesionAdmin($this->negocioSinModulo))
            ->get('backoffice/reportes/ventas')
            ->assertRedirect(url('backoffice/modulo/reportes'));

        // Y la pantalla de no disponible muestra el mensaje de vencido con la fecha.
        $html = $this->withSession($this->sesionAdmin($this->negocioSinModulo))
            ->get('backoffice/modulo/reportes')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('venció el '.$ayer, $html);
    }

    /* ================= AISLAMIENTO ENTRE NEGOCIOS ================= */

    public function test_un_negocio_sin_modulo_no_afecta_a_otro_que_si_lo_tiene(): void
    {
        Excel::fake();

        // El negocio CON módulo usa los reportes con normalidad...
        $this->withSession($this->sesionAdmin($this->negocioConModulo))
            ->getJson('request/reporte/ventas-preview?fecha_inicio=2026-01-01&fecha_fin=2026-01-31')
            ->assertOk()
            ->assertJsonPath('error', 0);

        // ...mientras el negocio SIN módulo sigue bloqueado en la misma suite.
        $this->withSession($this->sesionAdmin($this->negocioSinModulo))
            ->getJson('request/reporte/ventas-preview?fecha_inicio=2026-01-01&fecha_fin=2026-01-31')
            ->assertJsonPath('error', 1);
    }

    public function test_el_super_admin_no_queda_bloqueado_por_el_modulo(): void
    {
        // El super admin (tenant null) no tiene módulo que comprobar: la vista
        // no lo manda a la pantalla de no disponible (lo reenvía a su dashboard).
        $this->withSession($this->sesionSuperAdmin())
            ->get('backoffice/reportes/ventas')
            ->assertRedirect(url('backoffice/dashboard'));
    }

    /* ================= PANTALLA "NO DISPONIBLE" ================= */

    public function test_la_pantalla_de_un_modulo_inexistente_da_404(): void
    {
        $this->withSession($this->sesionAdmin($this->negocioSinModulo))
            ->get('backoffice/modulo/modulo-que-no-existe')
            ->assertNotFound();
    }

    public function test_la_pantalla_redirige_a_la_real_si_el_negocio_tiene_el_modulo(): void
    {
        $this->withSession($this->sesionAdmin($this->negocioConModulo))
            ->get('backoffice/modulo/reportes')
            ->assertRedirect(url('backoffice/reportes/ventas'));
    }

    public function test_el_boton_de_whatsapp_lleva_solo_digitos_y_el_mensaje_del_spa(): void
    {
        config([
            'plataforma.contacto_whatsapp' => '+57 (300) 111-2233',
            'plataforma.contacto_correo' => 'soporte@plataforma.test',
            'plataforma.nombre' => 'Plataforma Reservas',
        ]);

        $html = $this->withSession($this->sesionAdmin($this->negocioSinModulo, 'Spa Lila'))
            ->get('backoffice/modulo/reportes')
            ->assertOk()
            ->getContent();

        // wa.me con el número normalizado a SOLO dígitos.
        $this->assertStringContainsString('https://wa.me/573001112233', $html);
        // Mensaje precargado con el nombre del spa y el plan (url-encoded).
        $this->assertStringContainsString(rawurlencode('Hola, soy del spa Spa Lila y quiero el plan Pro (Reportes a Excel).'), $html);
        // El correo de contacto también aparece.
        $this->assertStringContainsString('soporte@plataforma.test', $html);
    }

    public function test_sin_contacto_configurado_la_pantalla_muestra_el_texto_generico(): void
    {
        config([
            'plataforma.contacto_whatsapp' => '',
            'plataforma.contacto_correo' => '',
        ]);

        $html = $this->withSession($this->sesionAdmin($this->negocioSinModulo))
            ->get('backoffice/modulo/reportes')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Comunícate con soporte', $html);
        $this->assertStringNotContainsString('wa.me', $html);
    }

    public function test_la_pantalla_rechaza_al_empleado(): void
    {
        // Empleado: restringir.empleado lo saca a su agenda antes de ver la pantalla.
        $this->withSession($this->sesionEmpleado($this->negocioSinModulo))
            ->get('backoffice/modulo/reportes')
            ->assertRedirect(url('backoffice/mis-citas'));
    }

    public function test_la_pantalla_rechaza_al_anonimo(): void
    {
        // Sin sesión, sesion.activa lo manda al login. En su propio test para
        // que no herede la sesión de otra petición.
        $this->get('backoffice/modulo/reportes')->assertRedirect(url('/login'));
    }

    public function test_el_nombre_del_negocio_con_payload_sale_escapado_en_la_pantalla(): void
    {
        $payload = '<img src=x onerror=alert(1)> "comillas" \'simples\'';

        config(['plataforma.contacto_whatsapp' => '573001112233']);

        $html = $this->withSession($this->sesionAdmin($this->negocioSinModulo, $payload))
            ->get('backoffice/modulo/reportes')
            ->assertOk()
            ->getContent();

        // El payload va dentro del href de WhatsApp url-encoded: nunca como HTML vivo.
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString(rawurlencode($payload), $html);
    }

    /* ================= GUARDIÁN DE RUTAS ================= */

    public function test_guardian_todas_las_rutas_de_reportes_llevan_verificar_modulo(): void
    {
        $rutasEsperadas = [
            'request/reporte/ventas-preview',
            'request/reporte/ventas-descargar',
            'request/reporte/servicios-preview',
            'request/reporte/servicios-descargar',
            'request/negocio/marcar-reportes-tour',
            'backoffice/reportes/ventas',
            'backoffice/reportes/servicios',
        ];

        foreach ($rutasEsperadas as $uri) {
            $ruta = collect(Route::getRoutes()->getRoutes())
                ->first(fn ($r) => $r->uri() === $uri);

            $this->assertNotNull($ruta, "La ruta {$uri} no existe");
            $this->assertContains(
                'verificar.modulo:reportes',
                $ruta->gatherMiddleware(),
                "La ruta {$uri} no lleva verificar.modulo:reportes"
            );
        }
    }

    /* ================= MUTACIONES DOCUMENTADAS ================= */

    /**
     * Mutación (2): un negocio NUEVO no nace con el módulo. Si algún camino
     * (el registro u otro) lo activara al crear el negocio, esta prueba
     * fallaría.
     */
    public function test_un_negocio_nuevo_nace_sin_el_modulo(): void
    {
        $nuevo = $this->crearNegocio('Recien Creado');

        $this->assertFalse(app(SvcModulo::class)->estaActivo($nuevo, 'reportes'));

        $this->withSession($this->sesionAdmin($nuevo))
            ->get('backoffice/reportes/ventas')
            ->assertRedirect(url('backoffice/modulo/reportes'));
    }

    /**
     * Mutación (4): la migración activa el módulo en los negocios que YA
     * existían al correrla, como cortesía. Se reaplica su up() sobre un negocio
     * sin la fila y se comprueba la activación. Si se quitara el bucle de
     * activación de la migración, esta prueba fallaría.
     */
    public function test_la_migracion_activa_el_modulo_en_los_negocios_existentes(): void
    {
        $idModulo = DB::table('modulos_plataforma')->where('clave', 'reportes')->value('id_modulo');

        // El negocio sin módulo no tiene la fila todavía.
        $this->assertDatabaseMissing('negocio_modulos', [
            'tenant_id' => $this->negocioSinModulo,
            'id_modulo' => $idModulo,
        ]);

        $migracion = require database_path('migrations/2026_11_02_100000_crear_modulo_reportes.php');
        $migracion->up();

        $fila = DB::table('negocio_modulos')
            ->where('tenant_id', $this->negocioSinModulo)
            ->where('id_modulo', $idModulo)
            ->first();

        $this->assertNotNull($fila);
        $this->assertSame(1, (int) $fila->activo);
        $this->assertSame('cortesia', $fila->origen);
        $this->assertSame('Incluido al crear el modulo', $fila->nota);
        $this->assertNull($fila->vigente_hasta);
    }
}
