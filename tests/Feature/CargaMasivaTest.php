<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Pruebas del aislamiento multi-tenant en la carga masiva.
 *
 * Es el punto más delicado del módulo: se sube un archivo que el usuario mismo
 * arma, así que hay que garantizar que el negocio de destino sale siempre de la
 * sesión y nunca del contenido del archivo.
 *
 * Las pruebas suben archivos XLSX reales por la ruta real, de modo que pasan por
 * el middleware, el controller, Laravel Excel, el Import y el Service, igual que
 * en producción. Corren sobre la SQLite en memoria de phpunit.xml.
 */
class CargaMasivaTest extends TestCase
{
    use RefreshDatabase;

    /** Negocio A: el que hace las importaciones. */
    private int $negocioA;

    /** Negocio B: el que NUNCA debe recibir nada de las cargas del A. */
    private int $negocioB;

    /** Archivos temporales creados por las pruebas, para borrarlos al final. */
    private array $archivosTemporales = [];

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
    }

    protected function tearDown(): void
    {
        foreach ($this->archivosTemporales as $ruta) {
            if (file_exists($ruta)) {
                unlink($ruta);
            }
        }

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

    /**
     * Genera un XLSX de verdad y lo envuelve como archivo subido, para que la
     * prueba recorra el mismo camino que una carga hecha desde el navegador.
     */
    private function crearExcel(array $filas, string $nombre): UploadedFile
    {
        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray($filas, null, 'A1');

        $ruta = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('carga-masiva-').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        $this->archivosTemporales[] = $ruta;

        return new UploadedFile(
            $ruta,
            $nombre,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            // Modo de prueba: evita que PHP exija que venga de un upload real.
            true
        );
    }

    /** Sesión de un administrador del negocio indicado. */
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

    private function importar(string $tipo, UploadedFile $archivo, array $sesion)
    {
        return $this->withSession($sesion)->post('request/carga-masiva/importar/'.$tipo, [
            'archivo' => $archivo,
        ], ['Accept' => 'application/json']);
    }

    /* ================= 1) EMPLEADOS ================= */

    /**
     * Aunque el archivo traiga una columna extra "tenant_id" apuntando al
     * negocio B, los empleados deben quedar en el negocio A, que es el de la
     * sesión que hizo la carga.
     */
    public function test_importar_empleados_asigna_siempre_el_tenant_de_la_sesion(): void
    {
        $archivo = $this->crearExcel([
            // La sexta columna es el intento de forzar otro negocio.
            ['Nombre', 'Telefono', 'Email', 'Cargo', 'Porcentaje Comision', 'tenant_id'],
            ['Empleado Uno', '3001110001', 'uno@test.local', 'Masajista', 12, $this->negocioB],
            ['Empleado Dos', '3001110002', 'dos@test.local', 'Esteticista', 15, $this->negocioB],
        ], 'empleados.xlsx');

        $respuesta = $this->importar('empleados', $archivo, $this->sesionAdmin($this->negocioA));

        $respuesta->assertJsonPath('error', 0);

        $this->assertSame(2, DB::table('empleados')->count());
        // Todos en el negocio de la sesión...
        $this->assertSame(2, DB::table('empleados')->where('tenant_id', $this->negocioA)->count());
        // ...y ninguno en el que intentaba colarse el archivo.
        $this->assertSame(0, DB::table('empleados')->where('tenant_id', $this->negocioB)->count());

        // Además quedan marcados como carga masiva, para poder rastrearlos.
        $this->assertSame(2, DB::table('empleados')->where('usuario_registra', 'Carga Masiva')->count());
    }

    /* ================= 2) RECURSOS Y USUARIOS ================= */

    public function test_importar_recursos_asigna_siempre_el_tenant_de_la_sesion(): void
    {
        $archivo = $this->crearExcel([
            ['Categoria', 'Nombre', 'Descripcion', 'Duracion Minutos', 'Precio', 'Capacidad', 'tenant_id'],
            ['Masajes', 'Servicio Uno', 'Descripcion', 60, 90000, 1, $this->negocioB],
            ['Faciales', 'Servicio Dos', 'Descripcion', 45, 70000, 1, $this->negocioB],
        ], 'recursos.xlsx');

        $respuesta = $this->importar('recursos', $archivo, $this->sesionAdmin($this->negocioA));

        $respuesta->assertJsonPath('error', 0);

        $this->assertSame(2, DB::table('recursos_reservables')->count());
        $this->assertSame(2, DB::table('recursos_reservables')->where('tenant_id', $this->negocioA)->count());
        $this->assertSame(0, DB::table('recursos_reservables')->where('tenant_id', $this->negocioB)->count());
    }

    public function test_importar_usuarios_asigna_siempre_el_tenant_de_la_sesion(): void
    {
        $archivo = $this->crearExcel([
            ['Usuario', 'Nombre', 'Email', 'Clave Temporal', 'Rol', 'tenant_id'],
            ['usuario.uno', 'Usuario Uno', 'uno@test.local', 'Temporal2026', 'empleado', $this->negocioB],
            ['usuario.dos', 'Usuario Dos', 'dos@test.local', 'Temporal2026', 'admin', $this->negocioB],
        ], 'usuarios.xlsx');

        $respuesta = $this->importar('usuarios', $archivo, $this->sesionAdmin($this->negocioA));

        $respuesta->assertJsonPath('error', 0);

        $this->assertSame(2, DB::table('usuarios')->count());
        $this->assertSame(2, DB::table('usuarios')->where('tenant_id', $this->negocioA)->count());
        $this->assertSame(0, DB::table('usuarios')->where('tenant_id', $this->negocioB)->count());

        // El rol se resuelve por su nombre, no por un id venido del archivo.
        $this->assertSame(2, DB::table('usuarios')->where('usuario', 'usuario.uno')->value('id_rol'));
        $this->assertSame(1, DB::table('usuarios')->where('usuario', 'usuario.dos')->value('id_rol'));

        // La clave nunca queda en texto plano.
        $clave = DB::table('usuarios')->where('usuario', 'usuario.uno')->value('clave');
        $this->assertNotSame('Temporal2026', $clave);
        $this->assertStringStartsWith('$2y$', $clave);
    }

    /* ================= 2.1) PRODUCTOS: EL SKU DECIDE CREAR VS ACTUALIZAR ================= */

    public function test_importar_productos_con_sku_nuevo_o_sin_sku_crea_productos(): void
    {
        $archivo = $this->crearExcel([
            ['SKU', 'Nombre', 'Descripcion', 'Cantidad Actual', 'Cantidad Minima', 'tenant_id'],
            ['SH-500', 'Shampoo', 'Hidratante', 12, 4, $this->negocioB],
            ['', 'Toallas', 'Paquete x100', 30, 10, $this->negocioB],
        ], 'productos.xlsx');

        $respuesta = $this->importar('productos', $archivo, $this->sesionAdmin($this->negocioA));

        $respuesta->assertJsonPath('error', 0);

        $this->assertSame(2, DB::table('productos')->count());
        // Todo al negocio de la sesión, ignorando la columna del archivo.
        $this->assertSame(2, DB::table('productos')->where('tenant_id', $this->negocioA)->count());
        $this->assertSame(0, DB::table('productos')->where('tenant_id', $this->negocioB)->count());

        // El que no traía SKU queda con null, sin inventarle uno.
        $this->assertNull(DB::table('productos')->where('nombre', 'Toallas')->value('sku'));
        $this->assertSame('SH-500', DB::table('productos')->where('nombre', 'Shampoo')->value('sku'));
    }

    public function test_importar_productos_con_sku_existente_actualiza_ese_producto(): void
    {
        $idExistente = DB::table('productos')->insertGetId([
            'tenant_id' => $this->negocioA,
            'nombre' => 'Nombre Viejo',
            'sku' => 'SH-500',
            'descripcion' => 'Descripcion vieja',
            'cantidad_actual' => 7,
            'cantidad_minima' => 2,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $archivo = $this->crearExcel([
            ['SKU', 'Nombre', 'Descripcion', 'Cantidad Actual', 'Cantidad Minima'],
            ['SH-500', 'Nombre Nuevo', 'Descripcion nueva', 999, 8],
        ], 'productos.xlsx');

        $respuesta = $this->importar('productos', $archivo, $this->sesionAdmin($this->negocioA));

        $respuesta->assertJsonPath('error', 0);

        // No se duplicó: sigue habiendo un solo producto.
        $this->assertSame(1, DB::table('productos')->count());

        $actualizado = DB::table('productos')->where('id_producto', $idExistente)->first();

        $this->assertSame('Nombre Nuevo', $actualizado->nombre);
        $this->assertSame('Descripcion nueva', $actualizado->descripcion);
        $this->assertSame(8, $actualizado->cantidad_minima);

        // La cantidad actual NO se toca desde la carga masiva: el stock real solo
        // se mueve con ingresarStock(), para no descuadrarlo en silencio.
        $this->assertSame(7, $actualizado->cantidad_actual);
    }

    /**
     * Dos negocios con el MISMO SKU: la carga del negocio A no puede alcanzar
     * el producto del negocio B.
     */
    public function test_importar_productos_por_sku_no_toca_el_producto_de_otro_negocio(): void
    {
        // El del negocio B se inserta PRIMERO a propósito: si la búsqueda por
        // SKU perdiera el filtro de negocio, un ->first() devolvería este (id
        // más bajo) y la carga del negocio A terminaría pisándolo. Con el orden
        // al revés la prueba pasaría por casualidad aunque el filtro no
        // estuviera, y dejaría de custodiar nada.
        $idDelB = DB::table('productos')->insertGetId([
            'tenant_id' => $this->negocioB, 'nombre' => 'Del B', 'sku' => 'MISMO-SKU',
            'cantidad_actual' => 5, 'cantidad_minima' => 1, 'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'), 'estado' => 1,
        ]);
        $idDelA = DB::table('productos')->insertGetId([
            'tenant_id' => $this->negocioA, 'nombre' => 'Del A', 'sku' => 'MISMO-SKU',
            'cantidad_actual' => 5, 'cantidad_minima' => 1, 'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'), 'estado' => 1,
        ]);

        $archivo = $this->crearExcel([
            ['SKU', 'Nombre', 'Descripcion', 'Cantidad Actual', 'Cantidad Minima'],
            ['MISMO-SKU', 'Renombrado Por El A', '', 5, 3],
        ], 'productos.xlsx');

        $this->importar('productos', $archivo, $this->sesionAdmin($this->negocioA))
            ->assertJsonPath('error', 0);

        // El del negocio A cambió...
        $this->assertSame('Renombrado Por El A', DB::table('productos')->where('id_producto', $idDelA)->value('nombre'));
        // ...y el del negocio B quedó exactamente igual.
        $this->assertSame('Del B', DB::table('productos')->where('id_producto', $idDelB)->value('nombre'));
        $this->assertSame(1, DB::table('productos')->where('id_producto', $idDelB)->value('cantidad_minima'));

        // Y no se creó ningún producto de más.
        $this->assertSame(2, DB::table('productos')->count());
    }

    /* ================= 3) LISTADOS AISLADOS ENTRE NEGOCIOS ================= */

    /**
     * Cada negocio importa sus propios empleados; al listar desde el negocio A
     * no puede asomarse ninguno del negocio B.
     */
    public function test_admin_del_negocio_a_no_puede_ver_empleados_importados_del_negocio_b(): void
    {
        $this->importar('empleados', $this->crearExcel([
            ['Nombre', 'Telefono', 'Email', 'Cargo', 'Porcentaje Comision'],
            ['Empleado Del A', '3001110001', 'a@test.local', 'Masajista', 10],
        ], 'empleados-a.xlsx'), $this->sesionAdmin($this->negocioA));

        $this->importar('empleados', $this->crearExcel([
            ['Nombre', 'Telefono', 'Email', 'Cargo', 'Porcentaje Comision'],
            ['Empleado Del B', '3002220002', 'b@test.local', 'Esteticista', 20],
        ], 'empleados-b.xlsx'), $this->sesionAdmin($this->negocioB));

        // Cada negocio se quedó con el suyo.
        $this->assertSame(1, DB::table('empleados')->where('tenant_id', $this->negocioA)->count());
        $this->assertSame(1, DB::table('empleados')->where('tenant_id', $this->negocioB)->count());

        $listado = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/empleado/listar');

        $listado->assertJsonPath('error', 0);

        $nombres = array_column($listado->json('data.empleados'), 'nombre');

        $this->assertContains('Empleado Del A', $nombres);
        $this->assertNotContains('Empleado Del B', $nombres);
        $this->assertCount(1, $nombres);
    }

    /* ================= 4) CONTROL DE ACCESO ================= */

    /**
     * Ni el rol empleado ni el super admin pueden usar la carga masiva: el
     * primero porque el módulo no es suyo, el segundo porque no pertenece a
     * ningún negocio al cual cargar datos.
     */
    public function test_carga_masiva_rechazada_para_empleado_y_super_admin(): void
    {
        $sesionEmpleado = [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 2,
            'usuario' => 'empleado.test',
            'nombre_usuario' => 'Empleado Test',
            'tenant_id' => $this->negocioA,
            'id_rol' => 2,
        ];

        $sesionSuperAdmin = [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 3,
            'usuario' => 'superadmin.test',
            'nombre_usuario' => 'Super Admin',
            'tenant_id' => null,
            'id_rol' => 3,
        ];

        $filasValidas = [
            ['Nombre', 'Telefono', 'Email', 'Cargo', 'Porcentaje Comision'],
            ['No Deberia Crearse', '3009990000', 'no@test.local', 'Masajista', 10],
        ];

        // --- Rol empleado: lo frena el middleware ---
        $plantillaEmpleado = $this->withSession($sesionEmpleado)
            ->getJson('request/carga-masiva/plantilla/empleados');

        $plantillaEmpleado->assertJsonPath('error', 1);
        $this->assertStringContainsString('No tienes permiso', $plantillaEmpleado->json('mensaje'));

        $importarEmpleado = $this->importar(
            'empleados',
            $this->crearExcel($filasValidas, 'empleados.xlsx'),
            $sesionEmpleado
        );

        $importarEmpleado->assertJsonPath('error', 1);
        $this->assertStringContainsString('No tienes permiso', $importarEmpleado->json('mensaje'));

        // La vista lo devuelve a su propia agenda.
        $this->withSession($sesionEmpleado)
            ->get('backoffice/carga-masiva')
            ->assertRedirect(url('backoffice/mis-citas'));

        // --- Super admin: pasa el middleware, pero no tiene negocio ---
        $plantillaSuper = $this->withSession($sesionSuperAdmin)
            ->getJson('request/carga-masiva/plantilla/empleados');

        $plantillaSuper->assertJsonPath('error', 1);
        $this->assertStringContainsString('cuenta de cada negocio', $plantillaSuper->json('mensaje'));

        $importarSuper = $this->importar(
            'empleados',
            $this->crearExcel($filasValidas, 'empleados.xlsx'),
            $sesionSuperAdmin
        );

        $importarSuper->assertJsonPath('error', 1);
        $this->assertStringContainsString('cuenta de cada negocio', $importarSuper->json('mensaje'));

        $this->withSession($sesionSuperAdmin)
            ->get('backoffice/carga-masiva')
            ->assertRedirect(url('backoffice/dashboard'));

        // Lo importante: ninguno de los cuatro intentos creó nada.
        $this->assertSame(0, DB::table('empleados')->count());
    }
}
