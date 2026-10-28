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
 * Roles y cuenta de plataforma: una sola cuenta super_admin, sin negocio, que
 * nadie puede crear, duplicar, mover a un negocio ni dar de baja; un admin de
 * negocio solo asigna admin y empleado en SU negocio; el panel de plataforma
 * exige rol super_admin Y sesión sin negocio; el dashboard es solo de admin.
 *
 * Todo por HTTP real con withSession() (y el login real donde importa).
 */
class RolesPlataformaTest extends TestCase
{
    use RefreshDatabase;

    const ROL_ADMIN = 1;

    const ROL_EMPLEADO = 2;

    const ROL_SUPER_ADMIN = 3;

    const CLAVE = 'Clave-Segura-2026';

    private int $negocioA;

    private int $negocioB;

    private int $idSuperAdmin;

    private int $idAdminA;

    private int $idAdminB;

    /** @var string[] */
    private array $archivosTemporales = [];

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => self::ROL_ADMIN, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => self::ROL_EMPLEADO, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => self::ROL_SUPER_ADMIN, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = $this->crearNegocio('Negocio A', 'negocio-a');
        $this->negocioB = $this->crearNegocio('Negocio B', 'negocio-b');

        $this->idSuperAdmin = $this->crearUsuario(null, self::ROL_SUPER_ADMIN, 'plataforma', 'plataforma@sistema.test');
        $this->idAdminA = $this->crearUsuario($this->negocioA, self::ROL_ADMIN, 'admin.a', 'admin.a@negocio.test');
        $this->idAdminB = $this->crearUsuario($this->negocioB, self::ROL_ADMIN, 'admin.b', 'admin.b@negocio.test');
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

    private function crearNegocio(string $nombre, string $slug): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'slug' => $slug,
            'rubro' => 'spa',
            'dias_atencion' => '1,2,3,4,5,6',
            'hora_apertura' => '08:00:00',
            'hora_cierre' => '18:00:00',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearUsuario(?int $tenantId, int $idRol, string $usuario, string $email, int $estado = 1): int
    {
        return DB::table('usuarios')->insertGetId([
            'tenant_id' => $tenantId,
            'id_rol' => $idRol,
            'usuario' => $usuario,
            'nombre' => 'Usuario '.$usuario,
            'email' => $email,
            'clave' => bcrypt(self::CLAVE),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => $estado,
        ]);
    }

    private function crearEmpleado(int $tenantId, string $nombre, ?int $idUsuario = null): int
    {
        return DB::table('empleados')->insertGetId([
            'tenant_id' => $tenantId,
            'id_usuario' => $idUsuario,
            'nombre' => $nombre,
            'telefono' => '3000000000',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function sesion(?int $tenantId, int $idRol, int $idUsuario, ?int $idEmpleado = null): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => $idUsuario,
            'usuario' => 'sesion.prueba',
            'nombre_usuario' => 'Sesion Prueba',
            'tenant_id' => $tenantId,
            'id_rol' => $idRol,
            'id_empleado' => $idEmpleado,
        ];
    }

    private function sesionAdminA(): array
    {
        return $this->sesion($this->negocioA, self::ROL_ADMIN, $this->idAdminA);
    }

    private function sesionSuperAdmin(): array
    {
        return $this->sesion(null, self::ROL_SUPER_ADMIN, $this->idSuperAdmin);
    }

    private function sesionEmpleadoA(): array
    {
        $idUsuario = $this->crearUsuario($this->negocioA, self::ROL_EMPLEADO, 'empleada.a', 'empleada.a@negocio.test');
        $idEmpleado = $this->crearEmpleado($this->negocioA, 'Empleada A', $idUsuario);

        return $this->sesion($this->negocioA, self::ROL_EMPLEADO, $idUsuario, $idEmpleado);
    }

    private function postComo(?array $sesion, string $url, array $cuerpo = [])
    {
        $this->flushSession();

        return $sesion === null
            ? $this->postJson($url, $cuerpo)
            : $this->withSession($sesion)->postJson($url, $cuerpo);
    }

    private function getComo(?array $sesion, string $url, array $query = [])
    {
        $this->flushSession();
        $url .= $query ? '?'.http_build_query($query) : '';

        return $sesion === null
            ? $this->getJson($url)
            : $this->withSession($sesion)->getJson($url);
    }

    private function datosNuevoUsuario(array $cambios = []): array
    {
        return $cambios + [
            'usuario' => 'nuevo.usuario',
            'nombre' => 'Nuevo Usuario',
            'email' => 'nuevo.usuario@negocio.test',
            'clave' => self::CLAVE,
            'tenant_id' => $this->negocioA,
            'id_rol' => self::ROL_ADMIN,
        ];
    }

    private function fila(int $idUsuario): object
    {
        return DB::table('usuarios')->where('id_usuario', $idUsuario)->first();
    }

    /** Lo que importa de la tabla usuarios, sin claves ni hashes. */
    private function fotoUsuarios(): array
    {
        return DB::table('usuarios')
            ->select('id_usuario', 'tenant_id', 'id_rol', 'usuario', 'email', 'estado')
            ->orderBy('id_usuario')
            ->get()
            ->map(fn ($u) => (array) $u)
            ->all();
    }

    private function cuentasSuperAdmin(): array
    {
        return DB::table('usuarios')
            ->select('id_usuario', 'tenant_id', 'estado', 'usuario')
            ->where('id_rol', self::ROL_SUPER_ADMIN)
            ->orderBy('id_usuario')
            ->get()
            ->map(fn ($u) => (array) $u)
            ->all();
    }

    private function crearExcel(array $filas): UploadedFile
    {
        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray($filas, null, 'A1');

        $ruta = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('roles-plataforma-').'.xlsx';
        (new Xlsx($libro))->save($ruta);
        $this->archivosTemporales[] = $ruta;

        return new UploadedFile($ruta, 'usuarios.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function moduloActivo(int $tenantId, string $clave): bool
    {
        return DB::table('negocio_modulos as nm')
            ->join('modulos_plataforma as mp', 'mp.id_modulo', '=', 'nm.id_modulo')
            ->where('nm.tenant_id', $tenantId)
            ->where('mp.clave', $clave)
            ->where('nm.activo', 1)
            ->exists();
    }

    /* ================= PASO 1: REPRODUCCIÓN DE LAS HIPÓTESIS =================
     *
     * Estas cinco pruebas se escribieron ANTES de la corrección y afirman el
     * comportamiento seguro. Sobre el código anterior, (a), (b), (c) y (d)
     * fallaron (el hueco era real); (e) pasó (la carga masiva ya filtraba).
     * Los resultados reales están en el reporte de la tarea.
     */

    /** (a) Un admin de negocio crea un usuario con el id_rol de super_admin. */
    public function test_paso1_a_admin_de_negocio_no_puede_crear_un_super_admin(): void
    {
        $respuesta = $this->postComo($this->sesionAdminA(), 'request/usuario/crear', $this->datosNuevoUsuario([
            'id_rol' => self::ROL_SUPER_ADMIN,
        ]));

        $this->assertSame(1, $respuesta->json('error'), 'El servidor respondió: '.json_encode($respuesta->json()));
        $this->assertSame(
            [['id_usuario' => $this->idSuperAdmin, 'tenant_id' => null, 'estado' => 1, 'usuario' => 'plataforma']],
            $this->cuentasSuperAdmin(),
            'En la base quedaron estas cuentas super_admin: '.json_encode($this->cuentasSuperAdmin())
        );
    }

    /** (b) Un admin de negocio pone super_admin a otro usuario suyo o a sí mismo. */
    public function test_paso1_b_admin_de_negocio_no_puede_poner_super_admin_a_nadie_ni_a_si_mismo(): void
    {
        $idOtro = $this->crearUsuario($this->negocioA, self::ROL_EMPLEADO, 'otra.a', 'otra.a@negocio.test');

        foreach (['otro usuario' => $idOtro, 'a sí mismo' => $this->idAdminA] as $caso => $idObjetivo) {
            $fila = $this->fila($idObjetivo);

            $respuesta = $this->postComo($this->sesionAdminA(), 'request/usuario/editar', [
                'id_usuario' => $idObjetivo,
                'usuario' => $fila->usuario,
                'nombre' => $fila->nombre,
                'email' => $fila->email,
                'tenant_id' => $this->negocioA,
                'id_rol' => self::ROL_SUPER_ADMIN,
                'estado' => 1,
            ]);

            $this->assertSame(1, $respuesta->json('error'), "Editar $caso a super_admin. El servidor respondió: ".json_encode($respuesta->json()));
            $this->assertSame((int) $fila->id_rol, (int) $this->fila($idObjetivo)->id_rol, "El rol de $caso quedó en ".$this->fila($idObjetivo)->id_rol);
        }
    }

    /**
     * (c) Con la cuenta resultante, por el LOGIN REAL, se llama al panel de
     * plataforma y a la activación de un módulo para el propio negocio.
     */
    public function test_paso1_c_la_cuenta_creada_por_un_admin_no_abre_el_panel_de_plataforma(): void
    {
        $alta = $this->postComo($this->sesionAdminA(), 'request/usuario/crear', $this->datosNuevoUsuario([
            'usuario' => 'escalada',
            'email' => 'escalada@negocio.test',
            'id_rol' => self::ROL_SUPER_ADMIN,
        ]));

        $this->flushSession();
        $login = $this->postJson('request/autenticacion/login', ['email' => 'escalada@negocio.test', 'clave' => self::CLAVE]);

        // Sin flushSession: se sigue con la sesión que haya dejado el login real.
        $negocios = $this->getJson('request/superadmin/negocios');
        $activar = $this->postJson('request/superadmin/activar-modulo', ['id_negocio' => $this->negocioA, 'clave_modulo' => 'ia']);

        $detalle = 'alta='.json_encode($alta->json()).' login='.json_encode($login->json())
            .' negocios(error)='.json_encode($negocios->json('error')).' negocios(cuantos)='.count((array) $negocios->json('data.negocios'))
            .' activar='.json_encode($activar->json());

        $this->assertSame(1, $negocios->json('error'), 'El panel de plataforma respondió. '.$detalle);
        $this->assertSame(1, $activar->json('error'), 'La activación de módulo respondió. '.$detalle);
        $this->assertFalse($this->moduloActivo($this->negocioA, 'ia'), 'El negocio A quedó con el módulo ia activo. '.$detalle);
    }

    /** (d) Un empleado abre backoffice/dashboard escribiendo la URL. */
    public function test_paso1_d_empleado_no_ve_el_dashboard_del_negocio(): void
    {
        $this->flushSession();
        $respuesta = $this->withSession($this->sesionEmpleadoA())->get('backoffice/dashboard');

        $this->assertNotSame(200, $respuesta->status(), 'El empleado recibió el dashboard (HTTP 200) con las métricas del negocio.');
        $respuesta->assertRedirect(url('backoffice/mis-citas'));
    }

    /** (e) Carga masiva de usuarios con una fila de rol super_admin. */
    public function test_paso1_e_carga_masiva_no_crea_un_super_admin(): void
    {
        $archivo = $this->crearExcel([
            ['usuario', 'nombre', 'email', 'clave', 'rol'],
            ['masivo.super', 'Masivo Super', 'masivo.super@negocio.test', self::CLAVE, 'super_admin'],
        ]);

        $this->flushSession();
        $respuesta = $this->withSession($this->sesionAdminA())->post('request/carga-masiva/importar/usuarios', [
            'archivo' => $archivo,
        ], ['Accept' => 'application/json']);

        $resultados = $respuesta->json('data.resultados');

        $this->assertFalse($resultados[0]['exito'] ?? true, 'La fila super_admin se importó: '.json_encode($respuesta->json()));
        // El mensaje es el del filtro de roles de la carga, no el genérico de
        // "no se pudo guardar": así, si el filtro desaparece, esta prueba lo nota.
        $this->assertStringContainsString('admin', $resultados[0]['mensaje'] ?? '');
        $this->assertStringContainsString('empleado', $resultados[0]['mensaje'] ?? '');
        $this->assertFalse(DB::table('usuarios')->where('email', 'masivo.super@negocio.test')->exists());
        $this->assertCount(1, $this->cuentasSuperAdmin());
    }

    /* ================= BACKSTOP DIRECTO EN EL SERVICE =================
     *
     * La regla vive en el Service, no solo en el controller. Estas pruebas
     * llaman a SvcUsuario directo, sin pasar por el controller, para que romper
     * el backstop del Service haga fallar algo aunque el controller lo tapara.
     */

    public function test_el_servicio_rechaza_crear_con_un_rol_que_no_es_de_negocio(): void
    {
        $svc = new \App\Service\SvcUsuario;

        $idSuper = $svc->crear([
            'usuario' => 'svc.super',
            'nombre' => 'Svc Super',
            'email' => 'svc.super@sistema.test',
            'clave' => self::CLAVE,
            'tenant_id' => $this->negocioA,
            'id_rol' => self::ROL_SUPER_ADMIN,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $this->assertFalse($idSuper);
        $this->assertFalse(DB::table('usuarios')->where('email', 'svc.super@sistema.test')->exists());
    }

    public function test_el_servicio_rechaza_crear_sin_negocio(): void
    {
        $svc = new \App\Service\SvcUsuario;

        $id = $svc->crear([
            'usuario' => 'svc.sinnegocio',
            'nombre' => 'Svc Sin Negocio',
            'email' => 'svc.sinnegocio@sistema.test',
            'clave' => self::CLAVE,
            'tenant_id' => null,
            'id_rol' => self::ROL_ADMIN,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $this->assertFalse($id);
    }

    public function test_el_servicio_rechaza_editar_a_un_rol_que_no_es_de_negocio(): void
    {
        // La cuenta objetivo nace INACTIVA a propósito: así la base no bloquea
        // por sus reglas de super_admin activo (A y B), y lo único que puede
        // impedir el cambio de rol es el backstop del Service. De lo contrario
        // la restricción de la base enmascararía si el guard del Service se cae.
        $idInactivo = $this->crearUsuario($this->negocioA, self::ROL_ADMIN, 'inactivo.a', 'inactivo.a@negocio.test', 0);

        $resultado = $svc = (new \App\Service\SvcUsuario)->editar($idInactivo, [
            'usuario' => 'inactivo.a',
            'nombre' => 'Usuario inactivo.a',
            'email' => 'inactivo.a@negocio.test',
            'id_rol' => self::ROL_SUPER_ADMIN,
            'estado' => 0,
        ], $this->negocioA);

        $this->assertFalse($resultado);
        $this->assertSame(self::ROL_ADMIN, (int) $this->fila($idInactivo)->id_rol);
    }

    /* ================= MIDDLEWARE SoloSuperAdmin EN AISLAMIENTO =================
     *
     * VerificarSesion ya corta una sesión super_admin con negocio antes de que
     * llegue al panel, así que por HTTP ese caso nunca alcanza a SoloSuperAdmin.
     * Para probar que SoloSuperAdmin TAMBIÉN exige tenant nulo (defensa en
     * profundidad), se ejerce el middleware directo.
     */
    public function test_el_middleware_solo_super_admin_exige_ausencia_de_negocio(): void
    {
        $middleware = new \App\Http\Middleware\SoloSuperAdmin;

        $peticion = \Illuminate\Http\Request::create('request/superadmin/resumen', 'GET');
        $peticion->setLaravelSession($this->app['session']->driver('array'));
        $peticion->session()->put('app_sesion', VerificarSesion::CLAVE_SESION);
        $peticion->session()->put('id_rol', self::ROL_SUPER_ADMIN);
        $peticion->session()->put('tenant_id', $this->negocioA); // anómalo: con negocio

        $llamado = false;
        $respuesta = $middleware->handle($peticion, function () use (&$llamado) {
            $llamado = true;

            return response('ok');
        });

        $this->assertFalse($llamado, 'SoloSuperAdmin dejó pasar una sesión super_admin CON negocio.');
        $this->assertSame(1, $respuesta->getData(true)['error'] ?? null);
    }

    public function test_el_middleware_solo_super_admin_deja_pasar_al_super_admin_sin_negocio(): void
    {
        $middleware = new \App\Http\Middleware\SoloSuperAdmin;

        $peticion = \Illuminate\Http\Request::create('request/superadmin/resumen', 'GET');
        $peticion->setLaravelSession($this->app['session']->driver('array'));
        $peticion->session()->put('app_sesion', VerificarSesion::CLAVE_SESION);
        $peticion->session()->put('id_rol', self::ROL_SUPER_ADMIN);
        $peticion->session()->put('tenant_id', null);

        $llamado = false;
        $middleware->handle($peticion, function () use (&$llamado) {
            $llamado = true;

            return response('ok');
        });

        $this->assertTrue($llamado, 'SoloSuperAdmin bloqueó al super admin legítimo (sin negocio).');
    }

    /* ================= CREAR: ROL Y NEGOCIO ================= */

    public function test_crear_con_rol_inexistente_es_rechazado_para_admin_y_para_super_admin(): void
    {
        foreach (['admin de negocio' => $this->sesionAdminA(), 'super admin' => $this->sesionSuperAdmin()] as $quien => $sesion) {
            $respuesta = $this->postComo($sesion, 'request/usuario/crear', $this->datosNuevoUsuario([
                'usuario' => 'rolraro',
                'email' => 'rolraro@negocio.test',
                'id_rol' => 999,
            ]));

            $this->assertSame(1, $respuesta->json('error'), "$quien con rol inexistente debe ser rechazado.");
            $this->assertFalse(DB::table('usuarios')->where('email', 'rolraro@negocio.test')->exists());
        }
    }

    public function test_el_super_admin_tampoco_puede_crear_otro_super_admin(): void
    {
        $respuesta = $this->postComo($this->sesionSuperAdmin(), 'request/usuario/crear', $this->datosNuevoUsuario([
            'usuario' => 'otro.super',
            'email' => 'otro.super@sistema.test',
            'id_rol' => self::ROL_SUPER_ADMIN,
        ]));

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertCount(1, $this->cuentasSuperAdmin());
    }

    public function test_un_tenant_id_del_cuerpo_lo_pisa_el_de_la_sesion_al_crear(): void
    {
        $respuesta = $this->postComo($this->sesionAdminA(), 'request/usuario/crear', $this->datosNuevoUsuario([
            'usuario' => 'intruso',
            'email' => 'intruso@negocio.test',
            'id_rol' => self::ROL_ADMIN,
            'tenant_id' => $this->negocioB,
        ]));

        $this->assertSame(0, $respuesta->json('error'), $respuesta->json('mensaje'));
        $this->assertSame($this->negocioA, (int) DB::table('usuarios')->where('email', 'intruso@negocio.test')->value('tenant_id'));
    }

    public function test_un_admin_de_negocio_crea_admin_y_empleado_de_su_negocio(): void
    {
        foreach ([self::ROL_ADMIN => 'otro.admin', self::ROL_EMPLEADO => 'otro.empleado'] as $idRol => $usuario) {
            $respuesta = $this->postComo($this->sesionAdminA(), 'request/usuario/crear', $this->datosNuevoUsuario([
                'usuario' => $usuario,
                'email' => $usuario.'@negocio.test',
                'id_rol' => $idRol,
            ]));

            $this->assertSame(0, $respuesta->json('error'), "Crear $usuario: ".$respuesta->json('mensaje'));
            $fila = DB::table('usuarios')->where('email', $usuario.'@negocio.test')->first();
            $this->assertSame($this->negocioA, (int) $fila->tenant_id);
            $this->assertSame($idRol, (int) $fila->id_rol);
        }
    }

    /* ================= EDITAR: LA CUENTA DE PLATAFORMA ================= */

    public function test_el_super_admin_edita_su_nombre_correo_y_clave(): void
    {
        $respuesta = $this->postComo($this->sesionSuperAdmin(), 'request/usuario/editar', [
            'id_usuario' => $this->idSuperAdmin,
            'usuario' => 'plataforma',
            'nombre' => 'Plataforma Renombrada',
            'email' => 'plataforma.nueva@sistema.test',
            'tenant_id' => null,
            'id_rol' => self::ROL_SUPER_ADMIN,
            'estado' => 1,
            'clave' => 'Clave-Nueva-2026',
        ]);

        $this->assertSame(0, $respuesta->json('error'), $respuesta->json('mensaje'));
        $fila = $this->fila($this->idSuperAdmin);
        $this->assertSame('Plataforma Renombrada', $fila->nombre);
        $this->assertSame('plataforma.nueva@sistema.test', $fila->email);
        $this->assertNull($fila->tenant_id);
        $this->assertSame(self::ROL_SUPER_ADMIN, (int) $fila->id_rol);
        $this->assertSame(1, (int) $fila->estado);

        // La clave nueva entra por el login real; la vieja ya no.
        $this->flushSession();
        $this->assertSame(0, $this->postJson('request/autenticacion/login', ['email' => 'plataforma.nueva@sistema.test', 'clave' => 'Clave-Nueva-2026'])->json('error'));
    }

    public function test_el_super_admin_no_puede_quitarse_el_rol(): void
    {
        $respuesta = $this->postComo($this->sesionSuperAdmin(), 'request/usuario/editar', [
            'id_usuario' => $this->idSuperAdmin,
            'usuario' => 'plataforma',
            'nombre' => 'Usuario plataforma',
            'email' => 'plataforma@sistema.test',
            'tenant_id' => $this->negocioA,
            'id_rol' => self::ROL_ADMIN,
            'estado' => 1,
        ]);

        $this->assertSame(1, $respuesta->json('error'));
        $fila = $this->fila($this->idSuperAdmin);
        $this->assertSame(self::ROL_SUPER_ADMIN, (int) $fila->id_rol);
        $this->assertNull($fila->tenant_id);
    }

    public function test_nadie_puede_ponerle_negocio_a_la_cuenta_de_plataforma_sin_cambiar_el_rol(): void
    {
        // Aunque el rol que llega siga siendo super_admin, el negocio del cuerpo
        // se descarta: la cuenta de plataforma nunca tiene tenant_id.
        $respuesta = $this->postComo($this->sesionSuperAdmin(), 'request/usuario/editar', [
            'id_usuario' => $this->idSuperAdmin,
            'usuario' => 'plataforma',
            'nombre' => 'Usuario plataforma',
            'email' => 'plataforma@sistema.test',
            'tenant_id' => $this->negocioA,
            'id_rol' => self::ROL_SUPER_ADMIN,
            'estado' => 1,
        ]);

        $this->assertSame(0, $respuesta->json('error'), $respuesta->json('mensaje'));
        $this->assertNull($this->fila($this->idSuperAdmin)->tenant_id);
    }

    public function test_el_super_admin_no_puede_desactivarse(): void
    {
        $respuesta = $this->postComo($this->sesionSuperAdmin(), 'request/usuario/editar', [
            'id_usuario' => $this->idSuperAdmin,
            'usuario' => 'plataforma',
            'nombre' => 'Usuario plataforma',
            'email' => 'plataforma@sistema.test',
            'tenant_id' => null,
            'id_rol' => self::ROL_SUPER_ADMIN,
            'estado' => 0,
        ]);

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertSame(1, (int) $this->fila($this->idSuperAdmin)->estado);
    }

    public function test_un_admin_de_negocio_no_puede_editar_la_cuenta_de_plataforma(): void
    {
        // Ni siquiera la ve: su tenant no coincide. El Service no encuentra la
        // fila bajo su negocio y nada cambia.
        $respuesta = $this->postComo($this->sesionAdminA(), 'request/usuario/editar', [
            'id_usuario' => $this->idSuperAdmin,
            'usuario' => 'plataforma',
            'nombre' => 'Hackeada',
            'email' => 'plataforma@sistema.test',
            'tenant_id' => $this->negocioA,
            'id_rol' => self::ROL_SUPER_ADMIN,
            'estado' => 1,
        ]);

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertSame('Usuario plataforma', $this->fila($this->idSuperAdmin)->nombre);
    }

    public function test_editar_un_usuario_de_negocio_a_super_admin_es_rechazado(): void
    {
        $respuesta = $this->postComo($this->sesionAdminA(), 'request/usuario/editar', [
            'id_usuario' => $this->idAdminA,
            'usuario' => 'admin.a',
            'nombre' => 'Usuario admin.a',
            'email' => 'admin.a@negocio.test',
            'tenant_id' => $this->negocioA,
            'id_rol' => self::ROL_SUPER_ADMIN,
            'estado' => 1,
        ]);

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertSame(self::ROL_ADMIN, (int) $this->fila($this->idAdminA)->id_rol);
    }

    /* ================= BAJA DE LA CUENTA DE PLATAFORMA ================= */

    public function test_la_papelera_no_elimina_la_cuenta_de_plataforma(): void
    {
        $respuesta = $this->postComo($this->sesionSuperAdmin(), 'request/usuario/eliminar', [
            'id_usuario' => $this->idSuperAdmin,
        ]);

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertSame(1, (int) $this->fila($this->idSuperAdmin)->estado);
    }

    public function test_el_servicio_no_desactiva_ni_elimina_la_cuenta_de_plataforma(): void
    {
        $svc = new \App\Service\SvcUsuario;

        $this->assertFalse($svc->eliminar($this->idSuperAdmin, null));
        $this->assertFalse($svc->editar($this->idSuperAdmin, ['estado' => 0], null));
        $this->assertSame(1, (int) $this->fila($this->idSuperAdmin)->estado);
    }

    public function test_la_cascada_de_empleados_no_puede_desactivar_la_cuenta_de_plataforma(): void
    {
        // Un empleado (anómalo) de un negocio apuntando a la cuenta de
        // plataforma: desactivarlo NO debe arrastrar al super admin. La cascada
        // aborta por discrepancia de negocio (el super admin no tiene tenant).
        $idEmpleado = $this->crearEmpleado($this->negocioA, 'Empleado Anomalo', $this->idSuperAdmin);

        $svc = new \App\Service\SvcEmpleado;
        $resultado = $svc->editar($idEmpleado, ['estado' => 0], $this->negocioA);

        $this->assertFalse($resultado, 'La baja debió abortar por la discrepancia de negocio.');
        $this->assertSame(1, (int) $this->fila($this->idSuperAdmin)->estado);
    }

    /* ================= DEFENSA EN PROFUNDIDAD: SESIÓN Y PANEL ================= */

    public function test_una_sesion_super_admin_con_negocio_es_cortada_por_verificar_sesion(): void
    {
        $this->flushSession();
        $sesionAnomala = $this->sesion($this->negocioA, self::ROL_SUPER_ADMIN, $this->idSuperAdmin);

        // En una vista: vuelve al login.
        $this->withSession($sesionAnomala)->get('backoffice/dashboard')->assertRedirect(url('/login'));

        // En un endpoint: el error de siempre, sin datos.
        $this->flushSession();
        $this->withSession($sesionAnomala)->getJson('request/superadmin/negocios')->assertJson(['error' => 1, 'data' => []]);
    }

    public function test_solo_super_admin_exige_rol_y_ausencia_de_negocio(): void
    {
        // Rol super_admin pero con negocio en la sesión: SoloSuperAdmin lo
        // rechaza (y además VerificarSesion ya lo habría cortado).
        $this->flushSession();
        $anomala = $this->sesion($this->negocioA, self::ROL_SUPER_ADMIN, $this->idSuperAdmin);
        $this->assertSame(1, $this->withSession($anomala)->getJson('request/superadmin/resumen')->json('error'));

        // La sesión legítima del super admin (sin negocio) sí entra.
        $this->flushSession();
        $this->assertSame(0, $this->withSession($this->sesionSuperAdmin())->getJson('request/superadmin/resumen')->json('error'));
    }

    /* ================= DASHBOARD ================= */

    public function test_dashboard_vista_rechaza_al_empleado_y_atiende_al_admin(): void
    {
        $this->flushSession();
        $this->withSession($this->sesionEmpleadoA())->get('backoffice/dashboard')->assertRedirect(url('backoffice/mis-citas'));

        $this->flushSession();
        $this->withSession($this->sesionAdminA())->get('backoffice/dashboard')->assertStatus(200);

        $this->flushSession();
        $this->withSession($this->sesionSuperAdmin())->get('backoffice/dashboard')->assertRedirect(url('backoffice/superadmin/dashboard'));
    }

    public function test_el_empleado_conserva_mis_citas(): void
    {
        $this->flushSession();
        $this->withSession($this->sesionEmpleadoA())->get('backoffice/mis-citas')->assertStatus(200);
    }

    /* ================= EL SELECTOR DE ROL NO OFRECE super_admin ================= */

    public function test_la_pantalla_de_usuarios_no_ofrece_el_rol_super_admin(): void
    {
        $this->flushSession();
        $html = $this->withSession($this->sesionAdminA())->get('backoffice/usuarios')->getContent();

        // Las opciones del selector de rol se pintan como >nombre_rol<. El
        // literal "super_admin" aparece en comentarios de CSS del layout, así
        // que se verifica el texto de la OPCIÓN, no la página entera.
        $this->assertStringContainsString('>admin<', $html);
        $this->assertStringContainsString('>empleado<', $html);
        $this->assertStringNotContainsString('>super_admin<', $html);
    }

    public function test_el_super_admin_tampoco_ve_el_rol_super_admin_en_el_selector(): void
    {
        $this->flushSession();
        $html = $this->withSession($this->sesionSuperAdmin())->get('backoffice/usuarios')->getContent();

        $this->assertStringNotContainsString('>super_admin<', $html);
    }

    /* ================= DEFENSA EN LA BASE (restricción real, sin mocks) ================= */

    public function test_la_base_rechaza_un_segundo_super_admin_activo(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('usuarios')->insert([
            'tenant_id' => null,
            'id_rol' => self::ROL_SUPER_ADMIN,
            'usuario' => 'segundo.super',
            'nombre' => 'Segundo Super',
            'email' => 'segundo.super@sistema.test',
            'clave' => bcrypt(self::CLAVE),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    public function test_la_base_rechaza_un_super_admin_con_negocio(): void
    {
        // Se quita antes la única cuenta de plataforma para que el rechazo sea
        // por la regla B (columna NOT NULL: super_admin activo no puede tener
        // negocio), no por el índice único de la regla A (segundo activo).
        DB::table('usuarios')->where('id_usuario', $this->idSuperAdmin)->delete();

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('usuarios')->insert([
            'tenant_id' => $this->negocioA,
            'id_rol' => self::ROL_SUPER_ADMIN,
            'usuario' => 'super.con.negocio',
            'nombre' => 'Super Con Negocio',
            'email' => 'super.con.negocio@sistema.test',
            'clave' => bcrypt(self::CLAVE),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    public function test_la_base_permite_un_segundo_super_admin_si_esta_inactivo(): void
    {
        $id = DB::table('usuarios')->insertGetId([
            'tenant_id' => null,
            'id_rol' => self::ROL_SUPER_ADMIN,
            'usuario' => 'super.historico',
            'nombre' => 'Super Historico',
            'email' => 'super.historico@sistema.test',
            'clave' => bcrypt(self::CLAVE),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 0,
        ]);

        $this->assertTrue(DB::table('usuarios')->where('id_usuario', $id)->exists());
    }
}
