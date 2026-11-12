<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Mail\ResumenStockBajo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\ActivaModulos;
use Tests\TestCase;

/**
 * Inventario convertido en módulo de pago apagable por negocio.
 *
 * Cubre lo que el módulo apaga (vista, endpoints, sidebar, campana de stock,
 * carga masiva de productos, resumen por correo, onboarding) y el aislamiento
 * entre negocios. El camino feliz de Productos vive en ProductoTest, el de la
 * carga masiva en CargaMasivaTest y el del comando en ResumenStockBajoCommandTest.
 *
 * Reloj congelado: el módulo vencido y la frontera de vigencia del comando
 * dependen de "hoy".
 */
class ModuloInventarioTest extends TestCase
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

        $this->activarModulo($this->negocioConModulo, 'inventario');
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

    private function crearAdmin(int $tenantId, string $email): void
    {
        DB::table('usuarios')->insert([
            'usuario' => 'admin.'.$tenantId,
            'nombre' => 'Admin',
            'email' => $email,
            'clave' => bcrypt('secreta123'),
            'tenant_id' => $tenantId,
            'id_rol' => 1,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearProductoStockBajo(int $tenantId): int
    {
        return DB::table('productos')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => 'Shampoo',
            'cantidad_actual' => 1,
            'cantidad_minima' => 5,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function sesionAdmin(int $tenantId): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 1,
            'usuario' => 'admin.test',
            'nombre_usuario' => 'Admin Test',
            'nombre_negocio_sesion' => 'Spa Prueba',
            'tenant_id' => $tenantId,
            'id_rol' => 1,
        ];
    }

    /** Endpoints request/* del módulo (los que un negocio sin módulo no debe usar). */
    private function endpointsDelModulo(): array
    {
        return [
            ['get', 'request/producto/listar'],
            ['get', 'request/producto/stock-bajo'],
            ['get', 'request/producto/generar-sku?nombre=Shampoo'],
            ['post', 'request/producto/crear'],
            ['post', 'request/producto/ingresar-stock'],
        ];
    }

    /* ================= NEGOCIO SIN MÓDULO: VISTA Y ENDPOINTS ================= */

    public function test_sin_modulo_la_vista_de_productos_redirige_a_no_disponible(): void
    {
        $this->withSession($this->sesionAdmin($this->negocioSinModulo))
            ->get('backoffice/productos')
            ->assertRedirect(url('backoffice/modulo/inventario'));
    }

    public function test_sin_modulo_los_endpoints_responden_el_error_del_modulo(): void
    {
        foreach ($this->endpointsDelModulo() as [$metodo, $url]) {
            $respuesta = $this->withSession($this->sesionAdmin($this->negocioSinModulo))->json($metodo, $url);

            $respuesta->assertOk()->assertJsonPath('error', 1);
            $this->assertStringContainsStringIgnoringCase('módulo', $respuesta->json('mensaje'));
        }
    }

    public function test_con_modulo_la_vista_y_los_endpoints_funcionan(): void
    {
        $this->withSession($this->sesionAdmin($this->negocioConModulo))
            ->get('backoffice/productos')
            ->assertOk();

        $this->withSession($this->sesionAdmin($this->negocioConModulo))
            ->getJson('request/producto/listar')
            ->assertOk()
            ->assertJsonPath('error', 0);
    }

    /* ================= SIDEBAR Y CAMPANA ================= */

    public function test_sin_modulo_el_sidebar_muestra_candado_y_no_la_campana(): void
    {
        $html = $this->withSession($this->sesionAdmin($this->negocioSinModulo))
            ->get('backoffice/dashboard')
            ->assertOk()
            ->getContent();

        // Candado en lugar del enlace real a Productos.
        $this->assertStringContainsString('backoffice/modulo/inventario', $html);
        $this->assertStringContainsString('menu-item-bloqueado', $html);
        $this->assertStringNotContainsString('href="'.url('backoffice/productos').'"', $html);

        // La campana de stock no llega al DOM: ni el botón ni el sondeo.
        $this->assertStringNotContainsString('id="btn-campana-stock"', $html);
        $this->assertStringNotContainsString('request/producto/stock-bajo', $html);
    }

    public function test_con_modulo_el_sidebar_muestra_productos_y_la_campana(): void
    {
        $html = $this->withSession($this->sesionAdmin($this->negocioConModulo))
            ->get('backoffice/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('href="'.url('backoffice/productos').'"', $html);
        $this->assertStringContainsString('id="btn-campana-stock"', $html);
        $this->assertStringContainsString('request/producto/stock-bajo', $html);
        $this->assertStringNotContainsString('backoffice/modulo/inventario', $html);
    }

    /* ================= CARGA MASIVA ================= */

    public function test_sin_modulo_la_carga_masiva_rechaza_productos_y_su_plantilla(): void
    {
        $sesion = $this->sesionAdmin($this->negocioSinModulo);

        // Plantilla de productos: rechazada con el error del módulo.
        $plantilla = $this->withSession($sesion)->getJson('request/carga-masiva/plantilla/productos');
        $plantilla->assertJsonPath('error', 1);
        $this->assertStringContainsStringIgnoringCase('módulo', $plantilla->json('mensaje'));

        // Importar productos: también rechazado (sin llegar a procesar archivo).
        $this->withSession($sesion)->postJson('request/carga-masiva/importar/productos')
            ->assertJsonPath('error', 1);

        // Los demás tipos (del núcleo) siguen disponibles: la plantilla de
        // empleados se descarga (200, binario), no da el error del módulo.
        $this->withSession($sesion)->get('request/carga-masiva/plantilla/empleados')->assertOk();
    }

    public function test_con_modulo_la_carga_masiva_permite_la_plantilla_de_productos(): void
    {
        $this->withSession($this->sesionAdmin($this->negocioConModulo))
            ->get('request/carga-masiva/plantilla/productos')
            ->assertOk();
    }

    /* ================= COMANDO DE RESUMEN DE STOCK ================= */

    public function test_el_comando_no_envia_a_un_negocio_sin_modulo_y_si_a_uno_con(): void
    {
        Mail::fake();

        $this->crearAdmin($this->negocioConModulo, 'con@negocio.test');
        $this->crearAdmin($this->negocioSinModulo, 'sin@negocio.test');
        $this->crearProductoStockBajo($this->negocioConModulo);
        $this->crearProductoStockBajo($this->negocioSinModulo);

        Artisan::call('productos:enviar-resumen-stock-bajo');

        Mail::assertQueued(ResumenStockBajo::class, fn ($m) => $m->hasTo('con@negocio.test'));
        Mail::assertNotQueued(ResumenStockBajo::class, fn ($m) => $m->hasTo('sin@negocio.test'));
        Mail::assertQueuedCount(1);
    }

    /**
     * Frontera de vigencia con reloj congelado: un módulo que vence HOY sigue
     * activo (envía); uno que venció AYER no (no envía).
     */
    public function test_el_comando_respeta_la_frontera_de_vigencia(): void
    {
        Mail::fake();

        $venceHoy = $this->crearNegocio('Vence Hoy');
        $vencioAyer = $this->crearNegocio('Vencio Ayer');
        $this->activarModulo($venceHoy, 'inventario', Carbon::today()->toDateString());
        $this->activarModulo($vencioAyer, 'inventario', Carbon::yesterday()->toDateString());
        $this->crearAdmin($venceHoy, 'hoy@negocio.test');
        $this->crearAdmin($vencioAyer, 'ayer@negocio.test');
        $this->crearProductoStockBajo($venceHoy);
        $this->crearProductoStockBajo($vencioAyer);

        Artisan::call('productos:enviar-resumen-stock-bajo');

        Mail::assertQueued(ResumenStockBajo::class, fn ($m) => $m->hasTo('hoy@negocio.test'));
        Mail::assertNotQueued(ResumenStockBajo::class, fn ($m) => $m->hasTo('ayer@negocio.test'));
    }

    /* ================= MÓDULO VENCIDO ================= */

    public function test_modulo_vencido_se_comporta_como_apagado(): void
    {
        $this->activarModulo($this->negocioSinModulo, 'inventario', Carbon::yesterday()->toDateString());

        $this->withSession($this->sesionAdmin($this->negocioSinModulo))
            ->get('backoffice/productos')
            ->assertRedirect(url('backoffice/modulo/inventario'));

        $this->withSession($this->sesionAdmin($this->negocioSinModulo))
            ->getJson('request/producto/listar')
            ->assertJsonPath('error', 1);
    }

    /* ================= LOS DATOS SE CONSERVAN ================= */

    public function test_apagar_el_modulo_conserva_los_productos_y_reactivar_los_devuelve(): void
    {
        // Con el módulo, el negocio tiene un producto.
        $idProducto = $this->crearProductoStockBajo($this->negocioConModulo);

        // Se apaga el módulo: la vista se bloquea, pero el producto sigue en la base.
        $this->desactivarModulo($this->negocioConModulo, 'inventario');

        $this->withSession($this->sesionAdmin($this->negocioConModulo))
            ->get('backoffice/productos')
            ->assertRedirect(url('backoffice/modulo/inventario'));

        $this->assertDatabaseHas('productos', ['id_producto' => $idProducto, 'tenant_id' => $this->negocioConModulo]);

        // Se reactiva: el producto reaparece tal cual, sin haber tenido que recrearlo.
        $this->activarModulo($this->negocioConModulo, 'inventario');

        $listado = $this->withSession($this->sesionAdmin($this->negocioConModulo))
            ->getJson('request/producto/listar');
        $listado->assertJsonPath('error', 0);
        $this->assertCount(1, $listado->json('data.productos'));
    }

    /* ================= AISLAMIENTO ENTRE NEGOCIOS ================= */

    public function test_un_negocio_sin_modulo_no_afecta_a_otro_que_si_lo_tiene(): void
    {
        $this->withSession($this->sesionAdmin($this->negocioConModulo))
            ->getJson('request/producto/listar')
            ->assertJsonPath('error', 0);

        $this->withSession($this->sesionAdmin($this->negocioSinModulo))
            ->getJson('request/producto/listar')
            ->assertJsonPath('error', 1);
    }

    /* ================= GUARDIÁN DE RUTAS ================= */

    public function test_guardian_todas_las_rutas_de_inventario_llevan_verificar_modulo(): void
    {
        $rutasEsperadas = [
            'request/producto/crear',
            'request/producto/editar',
            'request/producto/eliminar',
            'request/producto/listar',
            'request/producto/ingresar-stock',
            'request/producto/stock-bajo',
            'request/producto/generar-sku',
            'backoffice/productos',
        ];

        foreach ($rutasEsperadas as $uri) {
            $ruta = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === $uri);

            $this->assertNotNull($ruta, "La ruta {$uri} no existe");
            $this->assertContains(
                'verificar.modulo:inventario',
                $ruta->gatherMiddleware(),
                "La ruta {$uri} no lleva verificar.modulo:inventario"
            );
        }
    }

    /* ================= MUTACIÓN DOCUMENTADA ================= */

    /**
     * Mutación (migración): la migración activa el módulo en los negocios que
     * YA existían al correrla. Se reaplica su up() sobre un negocio sin la fila
     * y se comprueba la activación como cortesía.
     */
    public function test_la_migracion_activa_el_modulo_en_los_negocios_existentes(): void
    {
        $idModulo = DB::table('modulos_plataforma')->where('clave', 'inventario')->value('id_modulo');

        $this->assertDatabaseMissing('negocio_modulos', [
            'tenant_id' => $this->negocioSinModulo,
            'id_modulo' => $idModulo,
        ]);

        $migracion = require database_path('migrations/2026_11_02_100100_crear_modulo_inventario.php');
        $migracion->up();

        $fila = DB::table('negocio_modulos')
            ->where('tenant_id', $this->negocioSinModulo)
            ->where('id_modulo', $idModulo)
            ->first();

        $this->assertNotNull($fila);
        $this->assertSame(1, (int) $fila->activo);
        $this->assertSame('cortesia', $fila->origen);
        $this->assertSame('Incluido al crear el modulo', $fila->nota);
    }
}
