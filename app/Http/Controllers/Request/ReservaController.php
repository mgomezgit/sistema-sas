<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Service\SvcRecursoReservable;
use App\Service\SvcReserva;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ReservaController extends Controller
{
    protected SvcReserva $svcReserva;

    protected SvcRecursoReservable $svcRecursoReservable;

    const ESTADOS_VALIDOS = ['pendiente', 'confirmada', 'completada', 'cancelada'];

    public function __construct()
    {
        parent::__construct();

        $this->svcReserva = new SvcReserva;
        $this->svcRecursoReservable = new SvcRecursoReservable;
    }

    public function crear(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las reservas se gestionan desde la cuenta de cada negocio');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_cliente' => 'required',
            'id_recurso' => 'required',
            'fecha_reserva' => 'required',
            'hora_inicio' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $recurso = $this->svcRecursoReservable->listarById($datos['id_recurso'], $tenantId);

        if (empty($recurso)) {
            $this->agregarError('El recurso seleccionado no existe');

            return $this->sendResponse();
        }

        $horaFin = Carbon::parse($datos['hora_inicio'])->addMinutes($recurso[0]['duracion_minutos'])->format('H:i:s');

        $idEmpleado = $datos['id_empleado'] ?? null;

        if (! empty($idEmpleado)) {
            $disponible = $this->svcReserva->verificarDisponibilidad(
                $idEmpleado,
                $datos['fecha_reserva'],
                $datos['hora_inicio'],
                $horaFin,
                $tenantId
            );

            if (! $disponible) {
                $this->agregarError('El empleado ya tiene una reserva en ese horario');

                return $this->sendResponse();
            }
        }

        $info = [
            'tenant_id' => $tenantId,
            'id_cliente' => $datos['id_cliente'],
            'id_recurso' => $datos['id_recurso'],
            'id_empleado' => $idEmpleado ?: null,
            'fecha_reserva' => $datos['fecha_reserva'],
            'hora_inicio' => $datos['hora_inicio'],
            'hora_fin' => $horaFin,
            'estado_reserva' => 'pendiente',
            'notas' => $datos['notas'] ?? null,
            'usuario_registra' => session('nombre_usuario'),
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ];

        $idReserva = $this->svcReserva->crear($info);

        if ($idReserva === false) {
            $this->agregarError('No fue posible crear la reserva');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($idReserva, 'id_reserva');

        return $this->sendResponse();
    }

    public function editar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las reservas se gestionan desde la cuenta de cada negocio');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_reserva' => 'required',
            'id_cliente' => 'required',
            'id_recurso' => 'required',
            'fecha_reserva' => 'required',
            'hora_inicio' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $recurso = $this->svcRecursoReservable->listarById($datos['id_recurso'], $tenantId);

        if (empty($recurso)) {
            $this->agregarError('El recurso seleccionado no existe');

            return $this->sendResponse();
        }

        $horaFin = Carbon::parse($datos['hora_inicio'])->addMinutes($recurso[0]['duracion_minutos'])->format('H:i:s');

        $idEmpleado = $datos['id_empleado'] ?? null;

        if (! empty($idEmpleado)) {
            $disponible = $this->svcReserva->verificarDisponibilidad(
                $idEmpleado,
                $datos['fecha_reserva'],
                $datos['hora_inicio'],
                $horaFin,
                $tenantId,
                $datos['id_reserva']
            );

            if (! $disponible) {
                $this->agregarError('El empleado ya tiene una reserva en ese horario');

                return $this->sendResponse();
            }
        }

        $info = [
            'id_cliente' => $datos['id_cliente'],
            'id_recurso' => $datos['id_recurso'],
            'id_empleado' => $idEmpleado ?: null,
            'fecha_reserva' => $datos['fecha_reserva'],
            'hora_inicio' => $datos['hora_inicio'],
            'hora_fin' => $horaFin,
            'notas' => $datos['notas'] ?? null,
        ];

        $resultado = $this->svcReserva->editar($datos['id_reserva'], $info, $tenantId);

        if (! $resultado) {
            $this->agregarError('No fue posible editar la reserva');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function cambiarEstado(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las reservas se gestionan desde la cuenta de cada negocio');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_reserva' => 'required',
            'estado_reserva' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        if (! in_array($datos['estado_reserva'], self::ESTADOS_VALIDOS, true)) {
            $this->agregarError('Estado de reserva inválido');

            return $this->sendResponse();
        }

        $resultado = $this->svcReserva->cambiarEstado($datos['id_reserva'], $datos['estado_reserva'], $tenantId);

        if (! $resultado) {
            $this->agregarError('No fue posible cambiar el estado de la reserva');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function eliminar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las reservas se gestionan desde la cuenta de cada negocio');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_reserva' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $resultado = $this->svcReserva->eliminar($datos['id_reserva'], $tenantId);

        if (! $resultado) {
            $this->agregarError('No fue posible eliminar la reserva');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function listar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las reservas se gestionan desde la cuenta de cada negocio');

            return $this->sendResponse();
        }

        $fechaInicio = $this->request->query('fecha_inicio');
        $fechaFin = $this->request->query('fecha_fin');

        $this->respSinError();
        $this->setDataResponse($this->svcReserva->listar($tenantId, $fechaInicio, $fechaFin), 'reservas');

        return $this->sendResponse();
    }
}
