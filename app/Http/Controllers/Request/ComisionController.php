<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Service\SvcComision;
use Illuminate\Http\JsonResponse;

/**
 * Comisiones de empleados: informe del periodo, liquidación y tarifas.
 *
 * Módulo exclusivo del administrador del negocio. El middleware
 * "restringir.empleado" bloquea al rol empleado en estas rutas (no puede ver
 * comisiones de nadie, ni siquiera las propias); aquí se cubre además el caso
 * del super admin, que no pertenece a ningún negocio y por tanto no tiene
 * empleados a los que liquidar.
 */
class ComisionController extends Controller
{
    private SvcComision $svcComision;

    public function __construct()
    {
        parent::__construct();

        $this->svcComision = new SvcComision;
    }

    public function informe(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las comisiones se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $this->respSinError();
        $this->setDataResponse(
            $this->svcComision->generarInforme(
                $tenantId,
                $datos['fecha_inicio'],
                $datos['fecha_fin'],
                $datos['id_empleado'] ?? null
            ),
            'comisiones'
        );

        return $this->sendResponse();
    }

    /**
     * Liquida el periodo. Nótese que "monto_total" no se lee del request ni
     * aunque venga: el Service lo recalcula contra la base de datos.
     */
    public function marcarPagado(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las comisiones se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_empleado' => 'required',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $idPago = $this->svcComision->marcarPeriodoPagado(
            $tenantId,
            $datos['id_empleado'],
            $datos['fecha_inicio'],
            $datos['fecha_fin'],
            session('nombre_usuario')
        );

        if ($idPago === false) {
            $this->agregarError('No hay comisiones pendientes de pago para ese empleado en el periodo seleccionado.');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($idPago, 'id_pago_comision');

        return $this->sendResponse();
    }

    public function listarTarifas(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las comisiones se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse(
            $this->svcComision->listarTarifasEspecificas($tenantId, $this->request->query('id_empleado') ?: null),
            'tarifas'
        );

        return $this->sendResponse();
    }

    public function guardarTarifa(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las comisiones se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_empleado' => 'required',
            'id_recurso' => 'required',
            'porcentaje_comision' => 'required|numeric|min:0|max:100',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $resultado = $this->svcComision->guardarTarifaEspecifica($tenantId, [
            'id_empleado' => $datos['id_empleado'],
            'id_recurso' => $datos['id_recurso'],
            'porcentaje_comision' => $datos['porcentaje_comision'],
            'usuario_registra' => session('nombre_usuario'),
        ]);

        if (! $resultado) {
            $this->agregarErrorSistema('COM-TARIFA-GUARDAR');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function eliminarTarifa(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las comisiones se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_comision_tarifa' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        if (! $this->svcComision->eliminarTarifaEspecifica($datos['id_comision_tarifa'], $tenantId)) {
            $this->agregarErrorNoDisponible('la tarifa de comisión', 'COM-TARIFA-ELIM');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function historialPagos(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las comisiones se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse(
            $this->svcComision->listarHistorialPagos($tenantId, $this->request->query('id_empleado') ?: null),
            'pagos'
        );

        return $this->sendResponse();
    }
}
