<?php

namespace App\Http\Controllers\Request;

use App\Exports\ReporteServiciosExport;
use App\Exports\ReporteVentasExport;
use App\Http\Controllers\Controller;
use App\Service\SvcReserva;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reportes del negocio: consulta en pantalla y descarga en Excel.
 *
 * El middleware "restringir.empleado" ya bloquea al rol empleado en las rutas;
 * aquí se cubre además el caso del super admin, que no pertenece a ningún
 * negocio y por tanto no tiene reportes que ver.
 */
class ReporteController extends Controller
{
    protected SvcReserva $svcReserva;

    public function __construct()
    {
        parent::__construct();

        $this->svcReserva = new SvcReserva;
    }

    public function ventasPreview(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los reportes se consultan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        if (! $this->validarRangoDeFechas()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $this->respSinError();
        $this->setDataResponse(
            $this->svcReserva->reportePorFecha(
                $tenantId,
                $datos['fecha_inicio'],
                $datos['fecha_fin'],
                $datos['id_empleado'] ?? null,
                $datos['estado_reserva'] ?? null
            ),
            'ventas'
        );

        return $this->sendResponse();
    }

    /**
     * Devuelve el archivo, no el sobre JSON de siempre: el navegador lo recibe
     * como descarga directa.
     */
    public function ventasDescargar()
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los reportes se consultan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        if (! $this->validarRangoDeFechas()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $ventas = $this->svcReserva->reportePorFecha(
            $tenantId,
            $datos['fecha_inicio'],
            $datos['fecha_fin'],
            $datos['id_empleado'] ?? null,
            $datos['estado_reserva'] ?? null
        );

        return Excel::download(
            new ReporteVentasExport($ventas),
            'reporte-ventas-'.date('Y-m-d').'.xlsx'
        );
    }

    public function serviciosPreview(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los reportes se consultan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        if (! $this->validarRangoDeFechas()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $this->respSinError();
        $this->setDataResponse(
            $this->svcReserva->reporteIngresosPorServicio($tenantId, $datos['fecha_inicio'], $datos['fecha_fin']),
            'servicios'
        );

        return $this->sendResponse();
    }

    public function serviciosDescargar()
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los reportes se consultan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        if (! $this->validarRangoDeFechas()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $servicios = $this->svcReserva->reporteIngresosPorServicio(
            $tenantId,
            $datos['fecha_inicio'],
            $datos['fecha_fin']
        );

        return Excel::download(
            new ReporteServiciosExport($servicios),
            'reporte-servicios-'.date('Y-m-d').'.xlsx'
        );
    }

    /** Las cuatro acciones piden el mismo rango de fechas. */
    private function validarRangoDeFechas(): bool
    {
        $this->setRequestValidationRules([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
        ]);

        return $this->validateRequestRules();
    }
}
