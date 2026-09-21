<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Service\SvcCliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Clientes: reactivación desde el modal de edición y el filtro de inactivos,
 * mismo patrón ya construido y probado en Productos.
 */
class ClienteTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    private SvcCliente $svcCliente;

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
        $this->svcCliente = new SvcCliente;
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

    private function crearCliente(int $tenantId, array $sobrescribe = []): int
    {
        return DB::table('clientes')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'nombre' => 'Cliente de prueba',
            'telefono' => '3000000000',
            'email' => null,
            'documento_identidad' => null,
            'fecha_nacimiento' => null,
            'notas' => null,
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

    /* ================= 1) REACTIVAR UN CLIENTE DESDE EL MODAL ================= */

    public function test_editar_puede_desactivar_y_luego_reactivar_un_cliente(): void
    {
        $idCliente = $this->crearCliente($this->negocioA, ['nombre' => 'Cliente Uno']);

        $desactivar = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/cliente/editar', [
                'id_cliente' => $idCliente,
                'nombre' => 'Cliente Uno',
                'telefono' => '3000000000',
                'estado' => 0,
            ]);

        $desactivar->assertJsonPath('error', 0);
        $this->assertSame(0, DB::table('clientes')->where('id_cliente', $idCliente)->value('estado'));

        $reactivar = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/cliente/editar', [
                'id_cliente' => $idCliente,
                'nombre' => 'Cliente Uno',
                'telefono' => '3000000000',
                'estado' => 1,
            ]);

        $reactivar->assertJsonPath('error', 0);
        $this->assertSame(1, DB::table('clientes')->where('id_cliente', $idCliente)->value('estado'));
    }

    public function test_editar_sin_estado_es_rechazado(): void
    {
        $idCliente = $this->crearCliente($this->negocioA, ['nombre' => 'Cliente Uno']);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/cliente/editar', [
                'id_cliente' => $idCliente,
                'nombre' => 'Cliente Uno',
                'telefono' => '3000000000',
            ]);

        $respuesta->assertJsonPath('error', 1);
    }

    /* ================= 2) FILTRO DE INACTIVOS EN listar() ================= */

    public function test_listar_no_muestra_inactivos_por_defecto(): void
    {
        $this->crearCliente($this->negocioA, ['nombre' => 'Activo', 'estado' => 1]);
        $this->crearCliente($this->negocioA, ['nombre' => 'Inactivo', 'estado' => 0]);

        $listado = $this->svcCliente->listar($this->negocioA);

        $nombres = array_column($listado, 'nombre');

        $this->assertContains('Activo', $nombres);
        $this->assertNotContains('Inactivo', $nombres);
        $this->assertCount(1, $listado);
    }

    public function test_listar_incluye_inactivos_cuando_se_pide_explicitamente(): void
    {
        $this->crearCliente($this->negocioA, ['nombre' => 'Activo', 'estado' => 1]);
        $this->crearCliente($this->negocioA, ['nombre' => 'Inactivo', 'estado' => 0]);

        $listado = $this->svcCliente->listar($this->negocioA, true);

        $nombres = array_column($listado, 'nombre');

        $this->assertContains('Activo', $nombres);
        $this->assertContains('Inactivo', $nombres);
        $this->assertCount(2, $listado);
    }

    public function test_el_endpoint_de_listar_respeta_incluir_inactivos(): void
    {
        $this->crearCliente($this->negocioA, ['nombre' => 'Activo', 'estado' => 1]);
        $this->crearCliente($this->negocioA, ['nombre' => 'Inactivo', 'estado' => 0]);

        $normal = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/cliente/listar');

        $normal->assertJsonPath('error', 0);
        $this->assertCount(1, $normal->json('data.clientes'));

        $conInactivos = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/cliente/listar?incluir_inactivos=1');

        $conInactivos->assertJsonPath('error', 0);
        $this->assertCount(2, $conInactivos->json('data.clientes'));
    }

    /* ================= 3) AISLAMIENTO DE TENANT AL CAMBIAR ESTADO ================= */

    public function test_no_se_puede_reactivar_un_cliente_de_otro_negocio(): void
    {
        $idDelB = $this->crearCliente($this->negocioB, ['nombre' => 'Del B', 'estado' => 0]);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/cliente/editar', [
                'id_cliente' => $idDelB,
                'nombre' => 'Secuestrado',
                'telefono' => '3009999999',
                'estado' => 1,
            ]);

        $respuesta->assertJsonPath('error', 1);
        // Sigue inactivo y con su nombre original: el negocio A no lo tocó.
        $this->assertSame(0, DB::table('clientes')->where('id_cliente', $idDelB)->value('estado'));
        $this->assertSame('Del B', DB::table('clientes')->where('id_cliente', $idDelB)->value('nombre'));
    }

    public function test_no_se_puede_desactivar_un_cliente_de_otro_negocio(): void
    {
        $idDelB = $this->crearCliente($this->negocioB, ['nombre' => 'Del B', 'estado' => 1]);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/cliente/editar', [
                'id_cliente' => $idDelB,
                'nombre' => 'Secuestrado',
                'telefono' => '3009999999',
                'estado' => 0,
            ]);

        $respuesta->assertJsonPath('error', 1);
        $this->assertSame(1, DB::table('clientes')->where('id_cliente', $idDelB)->value('estado'));
    }

    /**
     * El aislamiento de listar() con el nuevo parámetro: el negocio B no debe
     * asomarse en el conteo del A ni siquiera pidiendo los inactivos.
     */
    public function test_listar_con_inactivos_no_mezcla_negocios(): void
    {
        $this->crearCliente($this->negocioA, ['nombre' => 'Inactivo A', 'estado' => 0]);
        $this->crearCliente($this->negocioB, ['nombre' => 'Inactivo B', 'estado' => 0]);

        $listadoA = $this->svcCliente->listar($this->negocioA, true);

        $this->assertCount(1, $listadoA);
        $this->assertSame('Inactivo A', $listadoA[0]['nombre']);
    }
}
