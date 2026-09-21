<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Service\SvcRecursoReservable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Recursos reservables: reactivación desde el modal de edición y el filtro de
 * inactivos, mismo patrón ya construido y probado en Productos y Clientes.
 */
class RecursoReservableTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    private SvcRecursoReservable $svcRecurso;

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
        $this->svcRecurso = new SvcRecursoReservable;
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

    private function crearRecurso(int $tenantId, array $sobrescribe = []): int
    {
        return DB::table('recursos_reservables')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'categoria' => 'Masajes',
            'nombre' => 'Recurso de prueba',
            'descripcion' => null,
            'duracion_minutos' => 60,
            'precio' => 90000,
            'capacidad' => 1,
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

    /* ================= 1) REACTIVAR UN RECURSO DESDE EL MODAL ================= */

    public function test_editar_puede_desactivar_y_luego_reactivar_un_recurso(): void
    {
        $idRecurso = $this->crearRecurso($this->negocioA, ['nombre' => 'Masaje Relajante']);

        $desactivar = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/recurso/editar', [
                'id_recurso' => $idRecurso,
                'nombre' => 'Masaje Relajante',
                'duracion_minutos' => 60,
                'precio' => 90000,
                'estado' => 0,
            ]);

        $desactivar->assertJsonPath('error', 0);
        $this->assertSame(0, DB::table('recursos_reservables')->where('id_recurso', $idRecurso)->value('estado'));

        $reactivar = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/recurso/editar', [
                'id_recurso' => $idRecurso,
                'nombre' => 'Masaje Relajante',
                'duracion_minutos' => 60,
                'precio' => 90000,
                'estado' => 1,
            ]);

        $reactivar->assertJsonPath('error', 0);
        $this->assertSame(1, DB::table('recursos_reservables')->where('id_recurso', $idRecurso)->value('estado'));
    }

    public function test_editar_sin_estado_es_rechazado(): void
    {
        $idRecurso = $this->crearRecurso($this->negocioA, ['nombre' => 'Masaje Relajante']);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/recurso/editar', [
                'id_recurso' => $idRecurso,
                'nombre' => 'Masaje Relajante',
                'duracion_minutos' => 60,
                'precio' => 90000,
            ]);

        $respuesta->assertJsonPath('error', 1);
    }

    /* ================= 2) FILTRO DE INACTIVOS EN listar() ================= */

    public function test_listar_no_muestra_inactivos_por_defecto(): void
    {
        $this->crearRecurso($this->negocioA, ['nombre' => 'Activo', 'estado' => 1]);
        $this->crearRecurso($this->negocioA, ['nombre' => 'Inactivo', 'estado' => 0]);

        $listado = $this->svcRecurso->listar($this->negocioA);

        $nombres = array_column($listado, 'nombre');

        $this->assertContains('Activo', $nombres);
        $this->assertNotContains('Inactivo', $nombres);
        $this->assertCount(1, $listado);
    }

    public function test_listar_incluye_inactivos_cuando_se_pide_explicitamente(): void
    {
        $this->crearRecurso($this->negocioA, ['nombre' => 'Activo', 'estado' => 1]);
        $this->crearRecurso($this->negocioA, ['nombre' => 'Inactivo', 'estado' => 0]);

        $listado = $this->svcRecurso->listar($this->negocioA, true);

        $nombres = array_column($listado, 'nombre');

        $this->assertContains('Activo', $nombres);
        $this->assertContains('Inactivo', $nombres);
        $this->assertCount(2, $listado);
    }

    public function test_el_endpoint_de_listar_respeta_incluir_inactivos(): void
    {
        $this->crearRecurso($this->negocioA, ['nombre' => 'Activo', 'estado' => 1]);
        $this->crearRecurso($this->negocioA, ['nombre' => 'Inactivo', 'estado' => 0]);

        $normal = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/recurso/listar');

        $normal->assertJsonPath('error', 0);
        $this->assertCount(1, $normal->json('data.recursos'));

        $conInactivos = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/recurso/listar?incluir_inactivos=1');

        $conInactivos->assertJsonPath('error', 0);
        $this->assertCount(2, $conInactivos->json('data.recursos'));
    }

    /* ================= 3) AISLAMIENTO DE TENANT AL CAMBIAR ESTADO ================= */

    public function test_no_se_puede_reactivar_un_recurso_de_otro_negocio(): void
    {
        $idDelB = $this->crearRecurso($this->negocioB, ['nombre' => 'Del B', 'estado' => 0]);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/recurso/editar', [
                'id_recurso' => $idDelB,
                'nombre' => 'Secuestrado',
                'duracion_minutos' => 30,
                'precio' => 1000,
                'estado' => 1,
            ]);

        $respuesta->assertJsonPath('error', 1);
        // Sigue inactivo y con su nombre original: el negocio A no lo tocó.
        $this->assertSame(0, DB::table('recursos_reservables')->where('id_recurso', $idDelB)->value('estado'));
        $this->assertSame('Del B', DB::table('recursos_reservables')->where('id_recurso', $idDelB)->value('nombre'));
    }

    public function test_no_se_puede_desactivar_un_recurso_de_otro_negocio(): void
    {
        $idDelB = $this->crearRecurso($this->negocioB, ['nombre' => 'Del B', 'estado' => 1]);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/recurso/editar', [
                'id_recurso' => $idDelB,
                'nombre' => 'Secuestrado',
                'duracion_minutos' => 30,
                'precio' => 1000,
                'estado' => 0,
            ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertSame(1, DB::table('recursos_reservables')->where('id_recurso', $idDelB)->value('estado'));
    }

    /**
     * El aislamiento de listar() con el nuevo parámetro: el negocio B no debe
     * asomarse en el conteo del A ni siquiera pidiendo los inactivos.
     */
    public function test_listar_con_inactivos_no_mezcla_negocios(): void
    {
        $this->crearRecurso($this->negocioA, ['nombre' => 'Inactivo A', 'estado' => 0]);
        $this->crearRecurso($this->negocioB, ['nombre' => 'Inactivo B', 'estado' => 0]);

        $listadoA = $this->svcRecurso->listar($this->negocioA, true);

        $this->assertCount(1, $listadoA);
        $this->assertSame('Inactivo A', $listadoA[0]['nombre']);
    }
}
