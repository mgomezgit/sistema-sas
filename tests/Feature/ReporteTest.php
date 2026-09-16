<?php

namespace Tests\Feature;

use App\Exports\ReporteServiciosExport;
use App\Exports\ReporteVentasExport;
use App\Http\Middleware\VerificarSesion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Reportes de ventas por fecha e ingresos por servicio: filtros, aislamiento
 * multi-tenant y generación del XLSX.
 *
 * Las descargas se verifican con Excel::fake(), que deja pasar la petición
 * real por el controller y el Service (consulta real a la base SQLite en
 * memoria) y solo intercepta la escritura del archivo, así que un fallo en el
 * aislamiento por tenant o en los filtros se detecta igual que si se
 * verificara el archivo generado.
 */
class ReporteTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    private int $idClienteA;

    private int $idRecursoMasajeA;

    private int $idRecursoFacialA;

    private int $idEmpleadoAuraA;

    private int $idEmpleadoLorenaA;

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

        $this->idClienteA = $this->crearCliente($this->negocioA, 'Cliente A');
        $this->idRecursoMasajeA = $this->crearRecurso($this->negocioA, 'Masaje', 60, 90000);
        $this->idRecursoFacialA = $this->crearRecurso($this->negocioA, 'Facial', 45, 70000);
        $this->idEmpleadoAuraA = $this->crearEmpleado($this->negocioA, 'Aura');
        $this->idEmpleadoLorenaA = $this->crearEmpleado($this->negocioA, 'Lorena');
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

    private function crearCliente(int $tenantId, string $nombre): int
    {
        return DB::table('clientes')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'telefono' => '3000000000',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearRecurso(int $tenantId, string $nombre, int $duracion, float $precio): int
    {
        return DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'duracion_minutos' => $duracion,
            'precio' => $precio,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearEmpleado(int $tenantId, string $nombre): int
    {
        return DB::table('empleados')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'telefono' => '3000000000',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearReserva(int $tenantId, int $idCliente, int $idRecurso, ?int $idEmpleado, array $sobreescribir = []): int
    {
        return DB::table('reservas')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'id_cliente' => $idCliente,
            'id_recurso' => $idRecurso,
            'id_empleado' => $idEmpleado,
            'fecha_reserva' => '2026-01-10',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado_reserva' => 'confirmada',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ], $sobreescribir));
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

    /* ================= 1) VENTAS: FILTROS EN PANTALLA ================= */

    public function test_ventas_preview_respeta_el_rango_de_fechas(): void
    {
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idRecursoMasajeA, $this->idEmpleadoAuraA, [
            'fecha_reserva' => '2026-01-10',
        ]);
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idRecursoMasajeA, $this->idEmpleadoAuraA, [
            'fecha_reserva' => '2026-02-15',
        ]);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/reporte/ventas-preview?fecha_inicio=2026-01-01&fecha_fin=2026-01-31');

        $respuesta->assertJsonPath('error', 0);
        $this->assertCount(1, $respuesta->json('data.ventas'));
        $this->assertSame('2026-01-10', $respuesta->json('data.ventas.0.fecha_reserva'));
    }

    public function test_ventas_preview_filtra_por_empleado(): void
    {
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idRecursoMasajeA, $this->idEmpleadoAuraA);
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idRecursoFacialA, $this->idEmpleadoLorenaA);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/reporte/ventas-preview?fecha_inicio=2026-01-01&fecha_fin=2026-01-31&id_empleado='.$this->idEmpleadoAuraA);

        $respuesta->assertJsonPath('error', 0);
        $ventas = $respuesta->json('data.ventas');

        $this->assertCount(1, $ventas);
        $this->assertSame('Masaje', $ventas[0]['nombre_recurso']);
    }

    public function test_ventas_preview_filtra_por_estado(): void
    {
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idRecursoMasajeA, $this->idEmpleadoAuraA, [
            'estado_reserva' => 'confirmada',
        ]);
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idRecursoFacialA, $this->idEmpleadoLorenaA, [
            'estado_reserva' => 'cancelada',
        ]);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/reporte/ventas-preview?fecha_inicio=2026-01-01&fecha_fin=2026-01-31&estado_reserva=cancelada');

        $respuesta->assertJsonPath('error', 0);
        $ventas = $respuesta->json('data.ventas');

        $this->assertCount(1, $ventas);
        $this->assertSame('cancelada', $ventas[0]['estado_reserva']);
    }

    public function test_ventas_preview_no_muestra_reservas_de_otro_negocio(): void
    {
        $idClienteB = $this->crearCliente($this->negocioB, 'Cliente B');
        $idRecursoB = $this->crearRecurso($this->negocioB, 'Servicio B', 30, 40000);

        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idRecursoMasajeA, $this->idEmpleadoAuraA);
        $this->crearReserva($this->negocioB, $idClienteB, $idRecursoB, null);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/reporte/ventas-preview?fecha_inicio=2026-01-01&fecha_fin=2026-01-31');

        $respuesta->assertJsonPath('error', 0);
        $nombres = array_column($respuesta->json('data.ventas'), 'nombre_recurso');

        $this->assertContains('Masaje', $nombres);
        $this->assertNotContains('Servicio B', $nombres);
        $this->assertCount(1, $nombres);
    }

    /* ================= 2) VENTAS: DESCARGA XLSX ================= */

    public function test_ventas_descargar_genera_el_excel_correcto_respetando_filtros_y_aislamiento(): void
    {
        Excel::fake();

        $idClienteB = $this->crearCliente($this->negocioB, 'Cliente B');
        $idRecursoB = $this->crearRecurso($this->negocioB, 'Servicio B', 30, 40000);

        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idRecursoMasajeA, $this->idEmpleadoAuraA, [
            'fecha_reserva' => '2026-01-10',
            'estado_reserva' => 'confirmada',
        ]);
        // Fuera del rango: no debe aparecer en el archivo.
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idRecursoFacialA, $this->idEmpleadoLorenaA, [
            'fecha_reserva' => '2026-03-01',
        ]);
        // De otro negocio: tampoco debe aparecer, aunque esté en el rango.
        $this->crearReserva($this->negocioB, $idClienteB, $idRecursoB, null, [
            'fecha_reserva' => '2026-01-12',
        ]);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->get('request/reporte/ventas-descargar?fecha_inicio=2026-01-01&fecha_fin=2026-01-31');

        $respuesta->assertOk();

        $nombreArchivo = 'reporte-ventas-'.date('Y-m-d').'.xlsx';

        Excel::assertDownloaded($nombreArchivo, function (ReporteVentasExport $export) {
            $filas = $export->array();

            $this->assertCount(1, $filas);
            $this->assertSame('Masaje', $filas[0][4]);
            $this->assertSame('Aura', $filas[0][5]);
            $this->assertSame(90000.0, $filas[0][7]);

            return true;
        });
    }

    /* ================= 3) SERVICIOS: SOLO CUENTA CONFIRMADA/COMPLETADA ================= */

    public function test_servicios_preview_solo_cuenta_reservas_confirmadas_y_completadas(): void
    {
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idRecursoMasajeA, $this->idEmpleadoAuraA, [
            'estado_reserva' => 'confirmada',
        ]);
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idRecursoMasajeA, $this->idEmpleadoAuraA, [
            'estado_reserva' => 'completada',
        ]);
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idRecursoMasajeA, $this->idEmpleadoAuraA, [
            'estado_reserva' => 'pendiente',
        ]);
        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idRecursoMasajeA, $this->idEmpleadoAuraA, [
            'estado_reserva' => 'cancelada',
        ]);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/reporte/servicios-preview?fecha_inicio=2026-01-01&fecha_fin=2026-01-31');

        $respuesta->assertJsonPath('error', 0);
        $servicios = $respuesta->json('data.servicios');

        $this->assertCount(1, $servicios);
        $this->assertSame('Masaje', $servicios[0]['nombre_recurso']);
        $this->assertSame(2, $servicios[0]['cantidad_reservas']);
        $this->assertEquals(180000, $servicios[0]['ingresos_totales']);
    }

    /* ================= 4) SERVICIOS: DESCARGA XLSX AISLADA ================= */

    public function test_servicios_descargar_genera_el_excel_correcto_y_aislado_por_tenant(): void
    {
        Excel::fake();

        $idClienteB = $this->crearCliente($this->negocioB, 'Cliente B');
        $idRecursoB = $this->crearRecurso($this->negocioB, 'Servicio B', 30, 999999);

        $this->crearReserva($this->negocioA, $this->idClienteA, $this->idRecursoMasajeA, $this->idEmpleadoAuraA, [
            'estado_reserva' => 'completada',
        ]);
        $this->crearReserva($this->negocioB, $idClienteB, $idRecursoB, null, [
            'estado_reserva' => 'completada',
        ]);

        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->get('request/reporte/servicios-descargar?fecha_inicio=2026-01-01&fecha_fin=2026-01-31');

        $respuesta->assertOk();

        $nombreArchivo = 'reporte-servicios-'.date('Y-m-d').'.xlsx';

        Excel::assertDownloaded($nombreArchivo, function (ReporteServiciosExport $export) {
            $filas = $export->array();

            $this->assertCount(1, $filas);
            $this->assertSame('Masaje', $filas[0][0]);

            $nombres = array_column($filas, 0);
            $this->assertNotContains('Servicio B', $nombres);

            return true;
        });
    }

    /* ================= 5) VALIDACIÓN DEL RANGO DE FECHAS ================= */

    public function test_ventas_preview_requiere_fecha_inicio_y_fecha_fin(): void
    {
        $respuesta = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/reporte/ventas-preview');

        $respuesta->assertJsonPath('error', 1);
    }

    /* ================= 6) CONTROL DE ACCESO ================= */

    public function test_reportes_rechazados_para_empleado_y_super_admin(): void
    {
        $sesionEmpleado = $this->sesionEmpleado($this->negocioA);
        $sesionSuper = $this->sesionSuperAdmin();

        $endpoints = [
            'request/reporte/ventas-preview?fecha_inicio=2026-01-01&fecha_fin=2026-01-31',
            'request/reporte/ventas-descargar?fecha_inicio=2026-01-01&fecha_fin=2026-01-31',
            'request/reporte/servicios-preview?fecha_inicio=2026-01-01&fecha_fin=2026-01-31',
            'request/reporte/servicios-descargar?fecha_inicio=2026-01-01&fecha_fin=2026-01-31',
        ];

        foreach ($endpoints as $endpoint) {
            $comoEmpleado = $this->withSession($sesionEmpleado)->getJson($endpoint);
            $comoEmpleado->assertJsonPath('error', 1);
            $this->assertStringContainsString('No tienes permiso', $comoEmpleado->json('mensaje'));

            $comoSuperAdmin = $this->withSession($sesionSuper)->getJson($endpoint);
            $comoSuperAdmin->assertJsonPath('error', 1);
            $this->assertStringContainsString('cuenta de cada negocio', $comoSuperAdmin->json('mensaje'));
        }

        $this->withSession($sesionEmpleado)
            ->get('backoffice/reportes/ventas')
            ->assertRedirect(url('backoffice/mis-citas'));
    }
}
