<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Panel del super admin: la única zona que trabaja ENTRE negocios.
 *
 * Todo por HTTP real con withSession(). Se prueba sobre todo lo que NO debe
 * pasar: quién no entra, qué datos no salen, qué negocio no se toca.
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * M1 — La ruta 'resumen' se sacó del grupo protegido y quedó solo con
 *      sesion.activa: 13 tests, 11 passed, 2 FAILED — el guardián (que la
 *      nombró) y la matriz ("empleado NO debe poder usar GET
 *      request/superadmin/resumen"). Restaurado: 13 passed.
 *
 * M2 — select('*') en SvcSuperAdmin::listarNegocios(): 13 tests, 12 passed,
 *      1 FAILED — test_listar_negocios_devuelve_solo_metadatos_de_cuenta. Se
 *      colaron 29 columnas de negocios y usuarios, entre ellas 'clave' (el
 *      hash de la contraseña del admin). Restaurado: 13 passed.
 *
 * M4 — SoloSuperAdmin identificando por tenant_id null en vez de por rol:
 *      13 tests, 12 passed, 1 FAILED — la matriz, por el caso "app_sesion +
 *      tenant null + rol admin". Restaurado: 13 passed.
 */
class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    /** Las claves exactas que puede devolver cada endpoint de listado. */
    const CLAVES_RESUMEN = [
        'modulos', 'negocios_activos', 'negocios_inactivos',
        'negocios_nuevos_30_dias', 'total_negocios',
    ];

    const CLAVES_MODULO_RESUMEN = ['clave', 'negocios_con_modulo', 'nombre'];

    const CLAVES_NEGOCIO = [
        'email_admin', 'estado', 'fecha_registro', 'id_negocio', 'modulos_activos',
        'nombre_admin', 'nombre_negocio', 'rubro', 'slug',
    ];

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

        // Datos operativos que JAMÁS deben aparecer en el panel.
        DB::table('clientes')->insert([
            'tenant_id' => $this->negocioA,
            'nombre' => 'Clienta Confidencial',
            'telefono' => '3009998877',
            'email' => 'clienta.confidencial@correo.test',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre, string $slug): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'slug' => $slug,
            'rubro' => 'spa',
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

    private function sesion(?int $tenantId, int $idRol, bool $conClave = true): array
    {
        $sesion = [
            'id_usuario' => 99,
            'usuario' => 'prueba',
            'nombre_usuario' => 'Prueba',
            'tenant_id' => $tenantId,
            'id_rol' => $idRol,
        ];

        if ($conClave) {
            $sesion['app_sesion'] = VerificarSesion::CLAVE_SESION;
        }

        return $sesion;
    }

    private function sesionSuperAdmin(): array
    {
        return $this->sesion(null, 3);
    }

    /**
     * Cada endpoint del panel con un cuerpo VÁLIDO: si alguien sin permiso
     * lograra pasar, la petición tendría efecto. Así el rechazo prueba algo.
     */
    private function endpoints(): array
    {
        return [
            ['GET', 'request/superadmin/resumen', []],
            ['GET', 'request/superadmin/negocios', []],
            ['POST', 'request/superadmin/cambiar-estado-negocio', ['id_negocio' => $this->negocioA, 'estado' => 0]],
            ['GET', 'request/superadmin/modulos-de-negocio', ['id_negocio' => $this->negocioA]],
            ['POST', 'request/superadmin/activar-modulo', ['id_negocio' => $this->negocioA, 'clave_modulo' => 'comisiones']],
            ['POST', 'request/superadmin/desactivar-modulo', ['id_negocio' => $this->negocioA, 'clave_modulo' => 'comisiones']],
        ];
    }

    private function llamar(string $metodo, string $url, array $cuerpo, ?array $sesion)
    {
        // withSession() MEZCLA con la sesión de la llamada anterior de la misma
        // prueba. Sin vaciarla, una sesión "sin app_sesion" heredaría la clave
        // del caso previo y la prueba no probaría lo que dice.
        $this->flushSession();

        $prueba = $sesion === null ? $this : $this->withSession($sesion);

        return $metodo === 'GET'
            ? $prueba->getJson($url.($cuerpo ? '?'.http_build_query($cuerpo) : ''))
            : $prueba->postJson($url, $cuerpo);
    }

    /** Estado de todo lo que un endpoint del panel podría modificar. */
    private function fotoDeLoModificable(): array
    {
        return [
            'negocios' => DB::table('negocios')->orderBy('id_negocio')->pluck('estado', 'id_negocio')->all(),
            'negocio_modulos' => DB::table('negocio_modulos')->orderBy('id_negocio_modulo')->get()->map(fn ($f) => (array) $f)->all(),
        ];
    }

    /* ================= 1) MATRIZ DE PERMISOS ================= */

    /**
     * Para CADA ruta del panel: anónimo, empleado, admin de negocio, y los dos
     * casos anómalos son rechazados, y nada cambia en la base.
     *
     * MUTACIÓN (solo.superadmin identificando por tenant_id null en vez de por
     * rol): esta prueba cae por el caso "app_sesion + tenant null + rol admin".
     */
    public function test_nadie_salvo_el_super_admin_usa_ningun_endpoint_del_panel(): void
    {
        $sesionesRechazadas = [
            'anonimo' => null,
            'empleado' => $this->sesion($this->negocioA, 2),
            'admin de negocio' => $this->sesion($this->negocioA, 1),
            // tenant_id null pero sin sesión válida, aunque diga ser super admin.
            'tenant null sin app_sesion' => $this->sesion(null, 3, false),
            // Caso anómalo: sesión válida y sin negocio, pero rol admin. Manda el rol.
            'app_sesion + tenant null + rol admin' => $this->sesion(null, 1),
        ];

        foreach ($this->endpoints() as [$metodo, $url, $cuerpo]) {
            foreach ($sesionesRechazadas as $quien => $sesion) {
                $antes = $this->fotoDeLoModificable();

                $respuesta = $this->llamar($metodo, $url, $cuerpo, $sesion);

                $this->assertSame(1, $respuesta->json('error'), "$quien NO debe poder usar $metodo $url");
                $this->assertSame([], (array) $respuesta->json('data'), "$quien no debe recibir datos de $url");
                $this->assertEquals($antes, $this->fotoDeLoModificable(), "$quien no debe haber modificado nada con $url");
            }
        }
    }

    public function test_el_super_admin_usa_todos_los_endpoints_del_panel(): void
    {
        foreach ($this->endpoints() as [$metodo, $url, $cuerpo]) {
            $respuesta = $this->llamar($metodo, $url, $cuerpo, $this->sesionSuperAdmin());

            $this->assertSame(0, $respuesta->json('error'), "El super admin debe poder usar $metodo $url: ".$respuesta->json('mensaje'));
        }
    }

    /* ================= 2) GUARDIÁN DE RUTAS ================= */

    /**
     * Toda ruta cuyo URI empiece por request/superadmin o backoffice/superadmin
     * lleva solo.superadmin. Una ruta nueva sin protección rompe la suite.
     *
     * MUTACIÓN: se sacó 'resumen' del grupo protegido; esta prueba falló
     * nombrando esa ruta.
     */
    public function test_toda_ruta_del_panel_lleva_solo_superadmin(): void
    {
        $delPanel = 0;
        $sinProteccion = [];

        foreach (Route::getRoutes() as $ruta) {
            $uri = $ruta->uri();

            if (! str_starts_with($uri, 'request/superadmin') && ! str_starts_with($uri, 'backoffice/superadmin')) {
                continue;
            }

            $delPanel++;

            if (! in_array('solo.superadmin', $ruta->gatherMiddleware(), true)) {
                $sinProteccion[] = implode('|', $ruta->methods()).' '.$uri;
            }
        }

        // Si el guardián no encontrara ninguna ruta, pasaría sin verificar nada.
        $this->assertGreaterThanOrEqual(6, $delPanel, 'El guardián no encontró las rutas del panel');
        $this->assertSame([], $sinProteccion, "Rutas del panel sin solo.superadmin:\n".implode("\n", $sinProteccion));
    }

    /* ================= 3) LISTAS BLANCAS ================= */

    public function test_el_resumen_devuelve_solo_agregados(): void
    {
        $resumen = $this->withSession($this->sesionSuperAdmin())
            ->getJson('request/superadmin/resumen')
            ->json('data.resumen');

        $claves = array_keys($resumen);
        sort($claves);
        $this->assertSame(self::CLAVES_RESUMEN, $claves);

        foreach ($resumen['modulos'] as $modulo) {
            $clavesModulo = array_keys($modulo);
            sort($clavesModulo);
            $this->assertSame(self::CLAVES_MODULO_RESUMEN, $clavesModulo);
        }

        $this->assertSame(2, $resumen['total_negocios']);
        $this->assertSame(2, $resumen['negocios_activos']);
        $this->assertSame(0, $resumen['negocios_inactivos']);
        $this->assertSame(2, $resumen['negocios_nuevos_30_dias']);
    }

    public function test_el_resumen_cuenta_inactivos_nuevos_y_modulos(): void
    {
        DB::table('negocios')->where('id_negocio', $this->negocioB)->update([
            'estado' => 0,
            'fecha_registro' => now()->subDays(90)->format('Y-m-d H:i:s'),
        ]);

        $this->withSession($this->sesionSuperAdmin())
            ->postJson('request/superadmin/activar-modulo', ['id_negocio' => $this->negocioA, 'clave_modulo' => 'comisiones']);

        $resumen = $this->withSession($this->sesionSuperAdmin())->getJson('request/superadmin/resumen')->json('data.resumen');

        $this->assertSame(1, $resumen['negocios_activos']);
        $this->assertSame(1, $resumen['negocios_inactivos']);
        $this->assertSame(1, $resumen['negocios_nuevos_30_dias']);

        $comisiones = collect($resumen['modulos'])->firstWhere('clave', 'comisiones');
        $this->assertSame(1, $comisiones['negocios_con_modulo']);
    }

    /**
     * MUTACIÓN: select('*') en listarNegocios(); esta prueba cayó porque se
     * colaron columnas de negocios y de usuarios (incluida la clave cifrada).
     */
    public function test_listar_negocios_devuelve_solo_metadatos_de_cuenta(): void
    {
        $this->withSession($this->sesionSuperAdmin())
            ->postJson('request/superadmin/activar-modulo', ['id_negocio' => $this->negocioA, 'clave_modulo' => 'comisiones']);

        $respuesta = $this->withSession($this->sesionSuperAdmin())->getJson('request/superadmin/negocios');
        $negocios = $respuesta->json('data.negocios');

        $this->assertCount(2, $negocios);

        foreach ($negocios as $negocio) {
            $claves = array_keys($negocio);
            sort($claves);
            $this->assertSame(self::CLAVES_NEGOCIO, $claves, 'listarNegocios no puede exponer columnas fuera de la lista blanca');
        }

        $negocioA = collect($negocios)->firstWhere('id_negocio', $this->negocioA);
        $this->assertSame('admin.a@negocio.test', $negocioA['email_admin']);
        $this->assertSame(['comisiones'], $negocioA['modulos_activos']);

        $negocioB = collect($negocios)->firstWhere('id_negocio', $this->negocioB);
        $this->assertSame([], $negocioB['modulos_activos']);

        // Ningún dato operativo ni secreto en la respuesta, en ninguna forma.
        $cuerpo = $respuesta->getContent();
        $this->assertStringNotContainsString('Clienta Confidencial', $cuerpo);
        $this->assertStringNotContainsString('clienta.confidencial@correo.test', $cuerpo);
        $this->assertStringNotContainsString('3009998877', $cuerpo);
        $this->assertStringNotContainsString('$2y$', $cuerpo, 'Jamás una clave cifrada');
    }

    public function test_la_busqueda_filtra_por_nombre_o_slug(): void
    {
        $porNombre = $this->withSession($this->sesionSuperAdmin())
            ->getJson('request/superadmin/negocios?busqueda=Negocio%20B')
            ->json('data.negocios');
        $this->assertSame([$this->negocioB], array_column($porNombre, 'id_negocio'));

        $porSlug = $this->withSession($this->sesionSuperAdmin())
            ->getJson('request/superadmin/negocios?busqueda=negocio-a')
            ->json('data.negocios');
        $this->assertSame([$this->negocioA], array_column($porSlug, 'id_negocio'));
    }

    /* ================= 4) VALIDACIÓN ================= */

    public function test_rechaza_un_negocio_inexistente(): void
    {
        $respuesta = $this->withSession($this->sesionSuperAdmin())
            ->postJson('request/superadmin/cambiar-estado-negocio', ['id_negocio' => 999999, 'estado' => 0]);

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertStringContainsString('no existe', json_encode($respuesta->json('mensaje'), JSON_UNESCAPED_UNICODE));
    }

    public function test_rechaza_un_estado_distinto_de_0_o_1(): void
    {
        $respuesta = $this->withSession($this->sesionSuperAdmin())
            ->postJson('request/superadmin/cambiar-estado-negocio', ['id_negocio' => $this->negocioA, 'estado' => 2]);

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertSame(1, (int) DB::table('negocios')->where('id_negocio', $this->negocioA)->value('estado'));
    }

    public function test_rechaza_una_clave_de_modulo_inexistente_o_retirada(): void
    {
        DB::table('modulos_plataforma')->insert([
            'clave' => 'modulo-retirado',
            'nombre' => 'Módulo retirado',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 0,
        ]);

        foreach (['no-existe', 'modulo-retirado'] as $clave) {
            $respuesta = $this->withSession($this->sesionSuperAdmin())
                ->postJson('request/superadmin/activar-modulo', ['id_negocio' => $this->negocioA, 'clave_modulo' => $clave]);

            $this->assertSame(1, $respuesta->json('error'), "La clave '$clave' debe rechazarse");
        }

        $this->assertSame(0, DB::table('negocio_modulos')->count(), 'No debe haberse creado ninguna activación');
    }

    /* ================= 5) MÓDULOS ================= */

    public function test_activar_en_un_negocio_no_cambia_el_otro(): void
    {
        $antesB = DB::table('negocio_modulos')->where('tenant_id', $this->negocioB)->get()->toArray();

        $this->withSession($this->sesionSuperAdmin())
            ->postJson('request/superadmin/activar-modulo', ['id_negocio' => $this->negocioA, 'clave_modulo' => 'comisiones'])
            ->assertJsonPath('error', 0);

        $modulosA = $this->withSession($this->sesionSuperAdmin())
            ->getJson('request/superadmin/modulos-de-negocio?id_negocio='.$this->negocioA)
            ->json('data.modulos');
        $modulosB = $this->withSession($this->sesionSuperAdmin())
            ->getJson('request/superadmin/modulos-de-negocio?id_negocio='.$this->negocioB)
            ->json('data.modulos');

        $this->assertSame(1, (int) collect($modulosA)->firstWhere('clave', 'comisiones')['activo']);
        $this->assertSame(0, (int) collect($modulosB)->firstWhere('clave', 'comisiones')['activo']);
        $this->assertEquals($antesB, DB::table('negocio_modulos')->where('tenant_id', $this->negocioB)->get()->toArray(), 'El negocio B debe quedar intacto');
    }

    /**
     * El ciclo completo, visto desde el admin del negocio: sin módulo no entra,
     * activado entra, desactivado vuelve a quedar fuera, y sus datos de
     * comisiones siguen ahí.
     */
    public function test_activar_y_desactivar_cambian_el_acceso_del_admin_sin_tocar_sus_datos(): void
    {
        $sesionAdminA = $this->sesion($this->negocioA, 1);

        $idEmpleado = DB::table('empleados')->insertGetId([
            'tenant_id' => $this->negocioA,
            'nombre' => 'Empleada',
            'telefono' => '3000000000',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
        $idRecurso = DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $this->negocioA,
            'nombre' => 'Masaje',
            'duracion_minutos' => 60,
            'precio' => 100000,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
        $idTarifa = DB::table('comisiones_tarifas')->insertGetId([
            'tenant_id' => $this->negocioA,
            'id_empleado' => $idEmpleado,
            'id_recurso' => $idRecurso,
            'porcentaje_comision' => 20,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
        $tarifaAntes = (array) DB::table('comisiones_tarifas')->where('id_comision_tarifa', $idTarifa)->first();

        // Sin módulo: bloqueado.
        $this->withSession($sesionAdminA)->get('backoffice/comisiones')->assertStatus(302);

        $this->withSession($this->sesionSuperAdmin())
            ->postJson('request/superadmin/activar-modulo', ['id_negocio' => $this->negocioA, 'clave_modulo' => 'comisiones'])
            ->assertJsonPath('error', 0);

        $this->withSession($sesionAdminA)->get('backoffice/comisiones')->assertStatus(200);
        $this->assertSame(0, $this->withSession($sesionAdminA)->getJson('request/comisiones/tarifas')->json('error'));

        $this->withSession($this->sesionSuperAdmin())
            ->postJson('request/superadmin/desactivar-modulo', ['id_negocio' => $this->negocioA, 'clave_modulo' => 'comisiones'])
            ->assertJsonPath('error', 0);

        $this->withSession($sesionAdminA)->get('backoffice/comisiones')->assertStatus(302);
        $this->assertSame(1, $this->withSession($sesionAdminA)->getJson('request/comisiones/tarifas')->json('error'));

        $this->assertEquals(
            $tarifaAntes,
            (array) DB::table('comisiones_tarifas')->where('id_comision_tarifa', $idTarifa)->first(),
            'Desactivar el módulo no puede tocar las tarifas'
        );
    }

    public function test_el_admin_de_un_negocio_no_puede_activarse_modulos_a_si_mismo(): void
    {
        $respuesta = $this->withSession($this->sesion($this->negocioA, 1))
            ->postJson('request/superadmin/activar-modulo', ['id_negocio' => $this->negocioA, 'clave_modulo' => 'comisiones']);

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertSame(0, DB::table('negocio_modulos')->where('tenant_id', $this->negocioA)->count());
    }
}
