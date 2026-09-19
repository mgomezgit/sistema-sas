<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Service\SvcProducto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Inventario de productos: aislamiento multi-tenant y reglas de negocio del
 * stock bajo (qué aparece y qué no en las alertas de la campana / el correo).
 */
class ProductoTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    private SvcProducto $svcProducto;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = $this->crearNegocio('Negocio A');
        $this->negocioB = $this->crearNegocio('Negocio B');
        $this->svcProducto = new SvcProducto;
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

    private function crearUsuarioAdmin(int $tenantId, string $usuario, string $email): int
    {
        return DB::table('usuarios')->insertGetId([
            'tenant_id' => $tenantId,
            'id_rol' => 1,
            'usuario' => $usuario,
            'nombre' => 'Admin '.$usuario,
            'email' => $email,
            'clave' => bcrypt('secreta'),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearProducto(int $tenantId, array $sobrescribe = []): int
    {
        return DB::table('productos')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'nombre' => 'Producto de prueba',
            'descripcion' => null,
            'cantidad_actual' => 10,
            'cantidad_minima' => 5,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ], $sobrescribe));
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

    /* ================= 1) AISLAMIENTO MULTI-TENANT EN EL CRUD ================= */

    public function test_crear_producto_queda_asignado_al_tenant_de_la_sesion(): void
    {
        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/producto/crear', [
                'nombre' => 'Shampoo',
                'sku' => 'SHAM-001',
                'cantidad_actual' => 20,
                'cantidad_minima' => 5,
            ]);

        $respuesta->assertJsonPath('error', 0);

        $this->assertSame(1, DB::table('productos')->where('tenant_id', $this->negocioA)->count());
        $this->assertSame('Shampoo', DB::table('productos')->value('nombre'));
    }

    public function test_editar_producto_de_otro_negocio_es_rechazado(): void
    {
        $idProducto = $this->crearProducto($this->negocioA, ['nombre' => 'Original']);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioB))
            ->postJson('request/producto/editar', [
                'id_producto' => $idProducto,
                'nombre' => 'Modificado Desde B',
                'cantidad_actual' => 1,
                'cantidad_minima' => 1,
            ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertSame('Original', DB::table('productos')->where('id_producto', $idProducto)->value('nombre'));
    }

    public function test_eliminar_producto_de_otro_negocio_es_rechazado(): void
    {
        $idProducto = $this->crearProducto($this->negocioA);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioB))
            ->postJson('request/producto/eliminar', ['id_producto' => $idProducto]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertSame(1, DB::table('productos')->where('id_producto', $idProducto)->value('estado'));
    }

    public function test_listar_productos_no_muestra_los_de_otro_negocio(): void
    {
        $this->crearProducto($this->negocioA, ['nombre' => 'Del Negocio A']);
        $this->crearProducto($this->negocioB, ['nombre' => 'Del Negocio B']);

        $listado = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/producto/listar');

        $listado->assertJsonPath('error', 0);

        $nombres = array_column($listado->json('data.productos'), 'nombre');

        $this->assertContains('Del Negocio A', $nombres);
        $this->assertNotContains('Del Negocio B', $nombres);
        $this->assertCount(1, $nombres);
    }

    /* ================= 2-4) REGLAS DE listarStockBajo() ================= */

    public function test_producto_inactivo_nunca_aparece_en_stock_bajo_aunque_tenga_cantidad_baja(): void
    {
        $this->crearProducto($this->negocioA, [
            'nombre' => 'Inactivo Bajo',
            'cantidad_actual' => 1,
            'cantidad_minima' => 10,
            'estado' => 0,
        ]);

        $stockBajo = $this->svcProducto->listarStockBajo($this->negocioA);

        $this->assertCount(0, $stockBajo);
    }

    /**
     * El mínimo es el punto de reposición: estar justo en él ya es motivo de
     * alerta, porque la siguiente venta deja al negocio por debajo.
     */
    public function test_producto_activo_con_cantidad_igual_al_minimo_si_aparece_en_stock_bajo(): void
    {
        $this->crearProducto($this->negocioA, [
            'nombre' => 'Justo En El Minimo',
            'cantidad_actual' => 10,
            'cantidad_minima' => 10,
            'estado' => 1,
        ]);

        $stockBajo = $this->svcProducto->listarStockBajo($this->negocioA);

        $this->assertCount(1, $stockBajo);
        $this->assertSame('Justo En El Minimo', $stockBajo[0]['nombre']);
        $this->assertSame('bajo', $stockBajo[0]['urgencia']);
    }

    public function test_producto_activo_con_cantidad_por_debajo_del_minimo_aparece_en_stock_bajo(): void
    {
        $this->crearProducto($this->negocioA, [
            'nombre' => 'Stock Bajo',
            'cantidad_actual' => 3,
            'cantidad_minima' => 10,
            'estado' => 1,
        ]);

        $stockBajo = $this->svcProducto->listarStockBajo($this->negocioA);

        $this->assertCount(1, $stockBajo);
        $this->assertSame('Stock Bajo', $stockBajo[0]['nombre']);
    }

    /* ================= 5) DESAPARECE AL REPONER ================= */

    public function test_al_ingresar_stock_por_encima_del_minimo_desaparece_de_las_notificaciones(): void
    {
        $idProducto = $this->crearProducto($this->negocioA, [
            'nombre' => 'Por Reponer',
            'cantidad_actual' => 2,
            'cantidad_minima' => 10,
            'estado' => 1,
        ]);

        $this->assertCount(1, $this->svcProducto->listarStockBajo($this->negocioA));

        $resultado = $this->svcProducto->ingresarStock($idProducto, 15, $this->negocioA);

        $this->assertTrue($resultado);
        $this->assertSame(17, DB::table('productos')->where('id_producto', $idProducto)->value('cantidad_actual'));
        $this->assertCount(0, $this->svcProducto->listarStockBajo($this->negocioA));
    }

    /* ================= 6) ingresarStock RECHAZADO PARA EMPLEADO Y SUPER ADMIN ================= */

    public function test_ingresar_stock_rechazado_para_empleado_y_super_admin(): void
    {
        $idProducto = $this->crearProducto($this->negocioA, ['cantidad_actual' => 1, 'cantidad_minima' => 10]);

        $comoEmpleado = $this->withSession($this->sesionEmpleado($this->negocioA))
            ->postJson('request/producto/ingresar-stock', [
                'id_producto' => $idProducto,
                'cantidad_agregar' => 50,
            ]);

        $comoEmpleado->assertJsonPath('error', 1);
        $this->assertStringContainsString('No tienes permiso', $comoEmpleado->json('mensaje'));

        $comoSuperAdmin = $this->withSession($this->sesionSuperAdmin())
            ->postJson('request/producto/ingresar-stock', [
                'id_producto' => $idProducto,
                'cantidad_agregar' => 50,
            ]);

        $comoSuperAdmin->assertJsonPath('error', 1);
        $this->assertStringContainsString('cuenta de cada negocio', $comoSuperAdmin->json('mensaje'));

        // Ninguno de los dos intentos alteró la cantidad real.
        $this->assertSame(1, DB::table('productos')->where('id_producto', $idProducto)->value('cantidad_actual'));

        // Las vistas de gestión tampoco quedan disponibles para ninguno de los dos.
        $this->withSession($this->sesionEmpleado($this->negocioA))
            ->get('backoffice/productos')
            ->assertRedirect(url('backoffice/mis-citas'));

        $this->withSession($this->sesionSuperAdmin())
            ->get('backoffice/productos')
            ->assertRedirect(url('backoffice/dashboard'));
    }

    /* ================= 8) SKU: ÚNICO POR NEGOCIO, NO GLOBAL =================
     *
     * ---------- PRUEBA DE MUTACIÓN DEL TENANT_ID ----------
     *
     * test_dos_negocios_pueden_usar_el_mismo_sku_cada_uno_el_suyo() se verificó
     * rompiendo el código a propósito. Procedimiento ejecutado:
     *
     *   1. En app/Service/SvcProducto.php, dentro de buscarPorSku(), se comentó
     *      la línea del filtro por negocio:
     *          ->where('tenant_id', $tenantId)
     *      Con eso la búsqueda de SKU pasa a ser GLOBAL, y el negocio B ya no
     *      puede registrar un SKU que el negocio A esté usando.
     *   2. Se ejecutó: php artisan test --filter=ProductoTest
     *      Resultado: 17 tests, 15 passed, 2 FAILED:
     *        - test_dos_negocios_pueden_usar_el_mismo_sku_cada_uno_el_suyo:
     *          "El negocio B debe poder usar un SKU que ya usa el negocio A
     *           Failed asserting that 1 is identical to 0."
     *          (el endpoint respondió error=1: rechazó el SKU del vecino).
     *        - test_buscar_por_sku_no_alcanza_el_producto_de_otro_negocio:
     *          buscarPorSku() devolvió el producto del negocio B al preguntarle
     *          por el negocio A.
     *   3. Se restauró la línea tal cual estaba.
     *   4. Se volvió a ejecutar: php artisan test --filter=ProductoTest
     *      Resultado: 17 passed.
     *
     * De paso, la mutación destapó que la prueba equivalente de CargaMasivaTest
     * (test_importar_productos_por_sku_no_toca_el_producto_de_otro_negocio)
     * pasaba por casualidad: con el filtro roto, el ->first() seguía devolviendo
     * el producto correcto solo por el orden de inserción. Se corrigió allá
     * invirtiendo ese orden, y se verificó que con la mutación puesta ahora sí
     * falla.
     *
     * Es decir: la prueba falla exactamente cuando el aislamiento por negocio
     * desaparece, que es lo que debe custodiar.
     */

    public function test_no_permite_dos_productos_con_el_mismo_sku_en_el_mismo_negocio(): void
    {
        $this->crearProducto($this->negocioA, ['nombre' => 'Original', 'sku' => 'SH-500']);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/producto/crear', [
                'nombre' => 'Repetido',
                'sku' => 'SH-500',
                'cantidad_actual' => 5,
                'cantidad_minima' => 2,
            ]);

        // Rechazo limpio y con mensaje entendible, no una excepción de MySQL.
        $respuesta->assertJsonPath('error', 1);
        $this->assertSame('Ya existe un producto con ese SKU', $respuesta->json('mensaje'));

        // Y no se creó nada.
        $this->assertSame(1, DB::table('productos')->count());
    }

    public function test_dos_negocios_pueden_usar_el_mismo_sku_cada_uno_el_suyo(): void
    {
        $this->crearProducto($this->negocioA, ['nombre' => 'Shampoo del A', 'sku' => 'SH-500']);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioB))
            ->postJson('request/producto/crear', [
                'nombre' => 'Shampoo del B',
                'sku' => 'SH-500',
                'cantidad_actual' => 7,
                'cantidad_minima' => 3,
            ]);

        $respuesta->assertJsonPath(
            'error',
            0,
            'El negocio B debe poder usar un SKU que ya usa el negocio A'
        );

        // Cada negocio con su producto, mismo SKU, sin estorbarse.
        $this->assertSame(1, DB::table('productos')->where('tenant_id', $this->negocioA)->where('sku', 'SH-500')->count());
        $this->assertSame(1, DB::table('productos')->where('tenant_id', $this->negocioB)->where('sku', 'SH-500')->count());
    }

    public function test_editar_permite_conservar_el_propio_sku_pero_no_tomar_el_de_otro(): void
    {
        $idPropio = $this->crearProducto($this->negocioA, ['nombre' => 'Propio', 'sku' => 'AAA-1']);
        $this->crearProducto($this->negocioA, ['nombre' => 'Vecino', 'sku' => 'BBB-2']);

        // Guardar el mismo registro con su propio SKU no debe chocar consigo mismo.
        $conSuPropio = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/producto/editar', [
                'id_producto' => $idPropio,
                'nombre' => 'Propio Renombrado',
                'sku' => 'AAA-1',
                'cantidad_actual' => 9,
                'cantidad_minima' => 2,
            ]);

        $conSuPropio->assertJsonPath('error', 0);
        $this->assertSame('Propio Renombrado', DB::table('productos')->where('id_producto', $idPropio)->value('nombre'));

        // Pero tomar el SKU de otro producto del mismo negocio sí se rechaza.
        $conElAjeno = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/producto/editar', [
                'id_producto' => $idPropio,
                'nombre' => 'Propio',
                'sku' => 'BBB-2',
                'cantidad_actual' => 9,
                'cantidad_minima' => 2,
            ]);

        $conElAjeno->assertJsonPath('error', 1);
        $this->assertSame('AAA-1', DB::table('productos')->where('id_producto', $idPropio)->value('sku'));
    }

    /**
     * El SKU pasó a ser obligatorio, así que ya no se pueden dar de alta
     * productos sin código. Pero los registrados antes siguen ahí con su SKU en
     * NULL, y el índice único por negocio admite varios NULL a propósito: si no
     * lo hiciera, el segundo producto heredado rompería la tabla.
     *
     * Se insertan directo en la base porque el endpoint ya no permite crearlos:
     * lo que se comprueba aquí es que el dato viejo sigue conviviendo.
     */
    public function test_los_productos_heredados_sin_sku_conviven_sin_chocar(): void
    {
        $this->crearProducto($this->negocioA, ['nombre' => 'Heredado Uno', 'sku' => null]);
        $this->crearProducto($this->negocioA, ['nombre' => 'Heredado Dos', 'sku' => null]);

        $this->assertSame(2, DB::table('productos')->whereNull('sku')->count());

        // Y el listado los sigue mostrando con normalidad.
        $listado = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/producto/listar');

        $listado->assertJsonPath('error', 0);
        $this->assertCount(2, $listado->json('data.productos'));
    }

    public function test_buscar_por_sku_no_alcanza_el_producto_de_otro_negocio(): void
    {
        $this->crearProducto($this->negocioB, ['nombre' => 'Del B', 'sku' => 'XYZ-9']);

        $this->assertNull($this->svcProducto->buscarPorSku('XYZ-9', $this->negocioA));
        $this->assertNotNull($this->svcProducto->buscarPorSku('XYZ-9', $this->negocioB));
    }

    /* ================= 9) URGENCIA: AGOTADO VS BAJO ================= */

    public function test_stock_bajo_distingue_agotado_de_bajo(): void
    {
        $this->crearProducto($this->negocioA, [
            'nombre' => 'Agotado',
            'cantidad_actual' => 0,
            'cantidad_minima' => 5,
        ]);
        $this->crearProducto($this->negocioA, [
            'nombre' => 'Bajo',
            'cantidad_actual' => 2,
            'cantidad_minima' => 5,
        ]);

        $stockBajo = collect($this->svcProducto->listarStockBajo($this->negocioA))->keyBy('nombre');

        $this->assertCount(2, $stockBajo);
        $this->assertSame('agotado', $stockBajo['Agotado']['urgencia']);
        $this->assertSame('bajo', $stockBajo['Bajo']['urgencia']);
    }

    /**
     * La frontera completa en una sola prueba, porque es la regla que decide
     * qué ve el negocio en la campana y qué recibe por correo:
     *
     *   cantidad = minimo      -> aparece, urgencia "bajo"
     *   cantidad = minimo + 1  -> no aparece
     *   cantidad = 0           -> aparece, urgencia "agotado"
     */
    public function test_frontera_exacta_del_stock_bajo(): void
    {
        $this->crearProducto($this->negocioA, [
            'nombre' => 'Igual Al Minimo',
            'cantidad_actual' => 5,
            'cantidad_minima' => 5,
        ]);
        $this->crearProducto($this->negocioA, [
            'nombre' => 'Una Unidad Arriba',
            'cantidad_actual' => 6,
            'cantidad_minima' => 5,
        ]);
        $this->crearProducto($this->negocioA, [
            'nombre' => 'Sin Existencias',
            'cantidad_actual' => 0,
            'cantidad_minima' => 5,
        ]);

        $stockBajo = collect($this->svcProducto->listarStockBajo($this->negocioA))->keyBy('nombre');

        $this->assertCount(2, $stockBajo);
        $this->assertSame('bajo', $stockBajo['Igual Al Minimo']['urgencia']);
        $this->assertSame('agotado', $stockBajo['Sin Existencias']['urgencia']);
        $this->assertArrayNotHasKey('Una Unidad Arriba', $stockBajo);
    }

    /**
     * La consulta del scheduler tiene su propio whereColumn, así que la frontera
     * se comprueba también ahí: si alguien ajusta una de las dos y olvida la
     * otra, la campana y el correo dejarían de contar lo mismo.
     */
    public function test_la_frontera_del_scheduler_coincide_con_la_de_la_campana(): void
    {
        $this->crearUsuarioAdmin($this->negocioA, 'admin.a', 'admin.a@test.local');

        $this->crearProducto($this->negocioA, [
            'nombre' => 'Igual Al Minimo',
            'cantidad_actual' => 5,
            'cantidad_minima' => 5,
        ]);
        $this->crearProducto($this->negocioA, [
            'nombre' => 'Una Unidad Arriba',
            'cantidad_actual' => 6,
            'cantidad_minima' => 5,
        ]);

        $delScheduler = collect($this->svcProducto->listarStockBajoTodosLosNegocios())->keyBy('nombre');
        $deLaCampana = collect($this->svcProducto->listarStockBajo($this->negocioA))->keyBy('nombre');

        $this->assertCount(1, $delScheduler);
        $this->assertSame('bajo', $delScheduler['Igual Al Minimo']['urgencia']);
        $this->assertArrayNotHasKey('Una Unidad Arriba', $delScheduler);

        // Las dos consultas deben seleccionar exactamente los mismos productos.
        $this->assertSame($deLaCampana->keys()->all(), $delScheduler->keys()->all());
    }

    public function test_el_resumen_de_todos_los_negocios_tambien_trae_la_urgencia(): void
    {
        $this->crearUsuarioAdmin($this->negocioA, 'admin.a', 'admin.a@test.local');

        $this->crearProducto($this->negocioA, [
            'nombre' => 'Agotado',
            'cantidad_actual' => 0,
            'cantidad_minima' => 4,
        ]);

        $todos = $this->svcProducto->listarStockBajoTodosLosNegocios();

        $this->assertCount(1, $todos);
        $this->assertSame('agotado', $todos[0]['urgencia']);
    }

    /* ================= 7) listarStockBajoTodosLosNegocios() PARA EL SCHEDULER ================= */

    public function test_listar_stock_bajo_todos_los_negocios_segmenta_correctamente_entre_negocios(): void
    {
        $this->crearUsuarioAdmin($this->negocioA, 'admin.a', 'admin.a@test.local');
        $this->crearUsuarioAdmin($this->negocioB, 'admin.b', 'admin.b@test.local');

        $this->crearProducto($this->negocioA, [
            'nombre' => 'Producto A Bajo',
            'cantidad_actual' => 1,
            'cantidad_minima' => 10,
        ]);
        $this->crearProducto($this->negocioB, [
            'nombre' => 'Producto B Bajo',
            'cantidad_actual' => 2,
            'cantidad_minima' => 20,
        ]);
        // Producto con stock suficiente: no debe aparecer para ningún negocio.
        $this->crearProducto($this->negocioA, [
            'nombre' => 'Producto A Con Stock',
            'cantidad_actual' => 50,
            'cantidad_minima' => 10,
        ]);
        // Producto inactivo con stock bajo: tampoco debe aparecer.
        $this->crearProducto($this->negocioB, [
            'nombre' => 'Producto B Inactivo',
            'cantidad_actual' => 1,
            'cantidad_minima' => 10,
            'estado' => 0,
        ]);

        $todos = $this->svcProducto->listarStockBajoTodosLosNegocios();

        $this->assertCount(2, $todos);

        $porNegocio = [];
        foreach ($todos as $fila) {
            $porNegocio[$fila['tenant_id']] = $fila;
        }

        $this->assertSame('Producto A Bajo', $porNegocio[$this->negocioA]['nombre']);
        $this->assertSame('Negocio A', $porNegocio[$this->negocioA]['nombre_negocio']);
        $this->assertSame('admin.a@test.local', $porNegocio[$this->negocioA]['email_admin']);

        $this->assertSame('Producto B Bajo', $porNegocio[$this->negocioB]['nombre']);
        $this->assertSame('Negocio B', $porNegocio[$this->negocioB]['nombre_negocio']);
        $this->assertSame('admin.b@test.local', $porNegocio[$this->negocioB]['email_admin']);

        // El email de un negocio nunca se cuela en el registro del otro.
        $this->assertNotSame($porNegocio[$this->negocioA]['email_admin'], $porNegocio[$this->negocioB]['email_admin']);
    }

    /* ================= 10) EL SKU ES OBLIGATORIO ================= */

    public function test_crear_producto_sin_sku_es_rechazado(): void
    {
        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/producto/crear', [
                'nombre' => 'Sin Codigo',
                'sku' => '',
                'cantidad_actual' => 5,
                'cantidad_minima' => 1,
            ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertSame(0, DB::table('productos')->count());
    }

    /** Ni siquiera omitiéndolo del todo. */
    public function test_crear_producto_omitiendo_el_sku_es_rechazado(): void
    {
        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/producto/crear', [
                'nombre' => 'Sin Codigo',
                'cantidad_actual' => 5,
                'cantidad_minima' => 1,
            ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertSame(0, DB::table('productos')->count());
    }

    public function test_editar_producto_sin_sku_es_rechazado(): void
    {
        $idProducto = $this->crearProducto($this->negocioA, ['nombre' => 'Original', 'sku' => 'ORIG-001']);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/producto/editar', [
                'id_producto' => $idProducto,
                'nombre' => 'Editado',
                'sku' => '',
                'cantidad_actual' => 5,
                'cantidad_minima' => 1,
            ]);

        $respuesta->assertJsonPath('error', 1);
        // No se guardó nada: el nombre sigue como estaba.
        $this->assertSame('Original', DB::table('productos')->where('id_producto', $idProducto)->value('nombre'));
    }

    /* ================= 11) SKU SUGERIDO ================= */

    public function test_el_sku_sugerido_usa_las_primeras_letras_del_nombre(): void
    {
        $sugerido = $this->svcProducto->generarSkuSugerido($this->negocioA, 'Depilación de piernas');

        $this->assertSame('DEPI-001', $sugerido);
    }

    /** Las tildes y los espacios no llegan al código. */
    public function test_el_sku_sugerido_ignora_tildes_espacios_y_simbolos(): void
    {
        $this->assertSame('MASC-001', $this->svcProducto->generarSkuSugerido($this->negocioA, 'Máscara facial'));
        $this->assertSame('CREM-001', $this->svcProducto->generarSkuSugerido($this->negocioA, '  Crema   Hidratante  '));
        $this->assertSame('ACEI-001', $this->svcProducto->generarSkuSugerido($this->negocioA, 'Aceite 30% (nuevo)'));
    }

    public function test_el_sku_sugerido_salta_al_siguiente_numero_si_ya_existe(): void
    {
        $this->crearProducto($this->negocioA, ['nombre' => 'Depilacion', 'sku' => 'DEPI-001']);

        $this->assertSame('DEPI-002', $this->svcProducto->generarSkuSugerido($this->negocioA, 'Depilación de piernas'));

        // Con el 002 también tomado, sigue avanzando.
        $this->crearProducto($this->negocioA, ['nombre' => 'Depilacion Dos', 'sku' => 'DEPI-002']);

        $this->assertSame('DEPI-003', $this->svcProducto->generarSkuSugerido($this->negocioA, 'Depilación de piernas'));
    }

    /** Un producto inactivo sigue ocupando su código: no se reutiliza. */
    public function test_el_sku_sugerido_respeta_el_codigo_de_un_producto_inactivo(): void
    {
        $this->crearProducto($this->negocioA, ['nombre' => 'Depilacion', 'sku' => 'DEPI-001', 'estado' => 0]);

        $this->assertSame('DEPI-002', $this->svcProducto->generarSkuSugerido($this->negocioA, 'Depilacion'));
    }

    public function test_el_sku_sugerido_usa_el_prefijo_generico_si_el_nombre_no_sirve(): void
    {
        $this->assertSame('PROD-001', $this->svcProducto->generarSkuSugerido($this->negocioA, ''));
        $this->assertSame('PROD-001', $this->svcProducto->generarSkuSugerido($this->negocioA, '   '));
        // Demasiado corto para reconocer nada.
        $this->assertSame('PROD-001', $this->svcProducto->generarSkuSugerido($this->negocioA, 'Té'));
        // Sin una sola letra aprovechable.
        $this->assertSame('PROD-001', $this->svcProducto->generarSkuSugerido($this->negocioA, '123 %'));
    }

    /**
     * El generador mira solo el inventario de su propio negocio: dos negocios
     * pueden recibir el mismo sugerido, cada uno el suyo.
     */
    public function test_el_sku_sugerido_no_se_ve_afectado_por_los_codigos_de_otro_negocio(): void
    {
        // El negocio B llena DEPI-001 y DEPI-002; al A no debe afectarle.
        $this->crearProducto($this->negocioB, ['nombre' => 'Depilacion B1', 'sku' => 'DEPI-001']);
        $this->crearProducto($this->negocioB, ['nombre' => 'Depilacion B2', 'sku' => 'DEPI-002']);

        $this->assertSame(
            'DEPI-001',
            $this->svcProducto->generarSkuSugerido($this->negocioA, 'Depilación de piernas'),
            'El negocio A debe recibir DEPI-001 aunque el negocio B ya lo use'
        );

        $this->assertSame('DEPI-003', $this->svcProducto->generarSkuSugerido($this->negocioB, 'Depilación de piernas'));
    }

    /* ================= 12) ENDPOINT DE SUGERENCIA ================= */

    public function test_el_endpoint_de_sku_devuelve_una_sugerencia_para_el_negocio_de_la_sesion(): void
    {
        $this->crearProducto($this->negocioB, ['nombre' => 'Del B', 'sku' => 'SHAM-001']);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/producto/generar-sku?nombre='.urlencode('Shampoo profesional'));

        $respuesta->assertJsonPath('error', 0);
        // El SHAM-001 del otro negocio no estorba.
        $respuesta->assertJsonPath('data.sku', 'SHAM-001');
    }

    public function test_el_endpoint_de_sku_sin_nombre_devuelve_el_prefijo_generico(): void
    {
        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/producto/generar-sku');

        $respuesta->assertJsonPath('error', 0);
        $respuesta->assertJsonPath('data.sku', 'PROD-001');
    }

    /* ================= 13) COMANDO QUE REPARTE LOS SKU QUE FALTAN ================= */

    public function test_el_comando_asigna_sku_a_los_productos_que_no_tienen(): void
    {
        $idUno = $this->crearProducto($this->negocioA, ['nombre' => 'Shampoo Profesional', 'sku' => null]);
        $idDos = $this->crearProducto($this->negocioA, ['nombre' => 'Aceite de Masaje', 'sku' => null]);

        $this->artisan('productos:asignar-sku-faltantes')->assertExitCode(0);

        $this->assertSame('SHAM-001', DB::table('productos')->where('id_producto', $idUno)->value('sku'));
        $this->assertSame('ACEI-001', DB::table('productos')->where('id_producto', $idDos)->value('sku'));
        $this->assertSame(0, DB::table('productos')->whereNull('sku')->count());
    }

    /** Un producto que ya tiene código no se toca. */
    public function test_el_comando_no_modifica_los_productos_que_ya_tienen_sku(): void
    {
        $idConSku = $this->crearProducto($this->negocioA, ['nombre' => 'Shampoo Profesional', 'sku' => 'MIO-999']);
        $idSinSku = $this->crearProducto($this->negocioA, ['nombre' => 'Toallas', 'sku' => null]);

        $this->artisan('productos:asignar-sku-faltantes')->assertExitCode(0);

        $this->assertSame('MIO-999', DB::table('productos')->where('id_producto', $idConSku)->value('sku'));
        $this->assertSame('TOAL-001', DB::table('productos')->where('id_producto', $idSinSku)->value('sku'));
    }

    /** Dos productos del mismo negocio con el mismo nombre no pueden chocar. */
    public function test_el_comando_no_repite_sku_dentro_del_mismo_negocio(): void
    {
        $idUno = $this->crearProducto($this->negocioA, ['nombre' => 'Shampoo', 'sku' => null]);
        $idDos = $this->crearProducto($this->negocioA, ['nombre' => 'Shampoo', 'sku' => null]);
        $idTres = $this->crearProducto($this->negocioA, ['nombre' => 'Shampoo', 'sku' => null]);

        $this->artisan('productos:asignar-sku-faltantes')->assertExitCode(0);

        $skus = [
            DB::table('productos')->where('id_producto', $idUno)->value('sku'),
            DB::table('productos')->where('id_producto', $idDos)->value('sku'),
            DB::table('productos')->where('id_producto', $idTres)->value('sku'),
        ];

        $this->assertSame(['SHAM-001', 'SHAM-002', 'SHAM-003'], $skus);
        $this->assertCount(3, array_unique($skus));
    }

    /**
     * Cada negocio se numera por su cuenta: los dos pueden quedarse con el
     * mismo código sin estorbarse, que es lo que permite el índice único por
     * negocio.
     */
    public function test_el_comando_numera_cada_negocio_por_separado(): void
    {
        $idDelA = $this->crearProducto($this->negocioA, ['nombre' => 'Shampoo Profesional', 'sku' => null]);
        $idDelB = $this->crearProducto($this->negocioB, ['nombre' => 'Shampoo Profesional', 'sku' => null]);

        $this->artisan('productos:asignar-sku-faltantes')->assertExitCode(0);

        $skuA = DB::table('productos')->where('id_producto', $idDelA)->value('sku');
        $skuB = DB::table('productos')->where('id_producto', $idDelB)->value('sku');

        $this->assertSame('SHAM-001', $skuA);
        $this->assertSame('SHAM-001', $skuB, 'Cada negocio numera desde 001 sin mirar al otro');

        // Y quedaron en su propio negocio, no mezclados.
        $this->assertSame($this->negocioA, DB::table('productos')->where('id_producto', $idDelA)->value('tenant_id'));
        $this->assertSame($this->negocioB, DB::table('productos')->where('id_producto', $idDelB)->value('tenant_id'));
    }

    /** El código ya ocupado en ese negocio no se repite: se sigue numerando. */
    public function test_el_comando_respeta_los_sku_ya_usados_en_ese_negocio(): void
    {
        $this->crearProducto($this->negocioA, ['nombre' => 'Shampoo Viejo', 'sku' => 'SHAM-001']);
        $idNuevo = $this->crearProducto($this->negocioA, ['nombre' => 'Shampoo Profesional', 'sku' => null]);

        $this->artisan('productos:asignar-sku-faltantes')->assertExitCode(0);

        $this->assertSame('SHAM-002', DB::table('productos')->where('id_producto', $idNuevo)->value('sku'));
    }

    /** Sin nada que hacer, el comando termina bien y no toca nada. */
    public function test_el_comando_no_hace_nada_si_no_faltan_sku(): void
    {
        $idProducto = $this->crearProducto($this->negocioA, ['nombre' => 'Completo', 'sku' => 'COMP-001']);

        $this->artisan('productos:asignar-sku-faltantes')
            ->expectsOutputToContain('No hay productos sin SKU')
            ->assertExitCode(0);

        $this->assertSame('COMP-001', DB::table('productos')->where('id_producto', $idProducto)->value('sku'));
    }

    /** También alcanza a los inactivos, que igual ocupan su lugar. */
    public function test_el_comando_tambien_asigna_sku_a_productos_inactivos(): void
    {
        $idInactivo = $this->crearProducto($this->negocioA, ['nombre' => 'Descontinuado', 'sku' => null, 'estado' => 0]);

        $this->artisan('productos:asignar-sku-faltantes')->assertExitCode(0);

        $this->assertSame('DESC-001', DB::table('productos')->where('id_producto', $idInactivo)->value('sku'));
    }

    public function test_el_endpoint_de_sku_rechazado_para_empleado_y_super_admin(): void
    {
        $comoEmpleado = $this->withSession($this->sesionEmpleado($this->negocioA))
            ->getJson('request/producto/generar-sku?nombre=Shampoo');

        $comoEmpleado->assertJsonPath('error', 1);
        $this->assertStringContainsString('No tienes permiso', $comoEmpleado->json('mensaje'));

        $comoSuperAdmin = $this->withSession($this->sesionSuperAdmin())
            ->getJson('request/producto/generar-sku?nombre=Shampoo');

        $comoSuperAdmin->assertJsonPath('error', 1);
        $this->assertStringContainsString('cuenta de cada negocio', $comoSuperAdmin->json('mensaje'));
    }
}
