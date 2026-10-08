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

        // "incluir_inactivas=1" es lo que activa el filtro "Mostrar inactivas"
        // de la pestaña. Sin él, una tarifa dada de baja no aparece: es la
        // única forma de que darla de baja se sienta reversible.
        $incluirInactivas = (bool) $this->request->query('incluir_inactivas');

        $this->respSinError();
        $this->setDataResponse(
            $this->svcComision->listarTarifasEspecificas(
                $tenantId,
                $this->request->query('id_empleado') ?: null,
                $incluirInactivas
            ),
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
            // Solo lo manda el modal en modo edición, que es donde existe el
            // interruptor. Un alta no lo trae y nace activa.
            'estado' => 'sometimes|in:0,1',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $info = [
            'id_empleado' => $datos['id_empleado'],
            'id_recurso' => $datos['id_recurso'],
            'porcentaje_comision' => $datos['porcentaje_comision'],
            'usuario_registra' => session('nombre_usuario'),
        ];

        if (array_key_exists('estado', $datos)) {
            $info['estado'] = (int) $datos['estado'];
        }

        $resultado = $this->svcComision->guardarTarifaEspecifica($tenantId, $info);

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

    /**
     * Anula un pago marcado por error. El tenant_id sale SIEMPRE de la sesión;
     * uno que venga en el cuerpo se ignora. Un pago de otro negocio responde
     * igual que uno inexistente, para no revelar que existe.
     */
    public function anularPago(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las comisiones se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_pago' => 'required|integer',
            'motivo' => 'required|string|min:5|max:200',
        ], [
            'motivo.required' => 'Escribe el motivo de la anulación.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max' => 'El motivo no puede pasar de 200 caracteres.',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $resultado = $this->svcComision->anularPago(
            (int) $datos['id_pago'],
            $tenantId,
            session('id_usuario'),
            trim($datos['motivo'])
        );

        if ($resultado === SvcComision::ANULACION_YA_ANULADO) {
            $this->agregarError('Este pago ya estaba anulado. Recarga el historial para ver su estado actual.');

            return $this->sendResponse();
        }

        if ($resultado === SvcComision::ANULACION_NO_EXISTE) {
            $this->agregarErrorNoDisponible('el pago de comisión', 'COM-PAGO-ANULAR');

            return $this->sendResponse();
        }

        if ($resultado !== SvcComision::ANULACION_HECHA) {
            $this->agregarErrorSistema('COM-PAGO-ANULAR-ERR');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }
}
