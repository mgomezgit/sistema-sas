<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Service\SvcRecursoReservable;
use Illuminate\Http\JsonResponse;

class RecursoReservableController extends Controller
{
    protected SvcRecursoReservable $svcRecursoReservable;

    public function __construct()
    {
        parent::__construct();

        $this->svcRecursoReservable = new SvcRecursoReservable;
    }

    public function crear(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los servicios se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'nombre' => 'required',
            'duracion_minutos' => 'required',
            'precio' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $info = [
            'tenant_id' => $tenantId,
            'categoria' => $datos['categoria'] ?? null,
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'duracion_minutos' => $datos['duracion_minutos'],
            'precio' => $datos['precio'],
            'capacidad' => $datos['capacidad'] ?? null,
            'usuario_registra' => session('nombre_usuario'),
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ];

        $idRecurso = $this->svcRecursoReservable->crear($info);

        if ($idRecurso === false) {
            $this->agregarErrorSistema('REC-CREAR');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($idRecurso, 'id_recurso');

        return $this->sendResponse();
    }

    public function editar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los servicios se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_recurso' => 'required',
            'nombre' => 'required',
            'duracion_minutos' => 'required',
            'precio' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $info = [
            'categoria' => $datos['categoria'] ?? null,
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'duracion_minutos' => $datos['duracion_minutos'],
            'precio' => $datos['precio'],
            'capacidad' => $datos['capacidad'] ?? null,
        ];

        $resultado = $this->svcRecursoReservable->editar($datos['id_recurso'], $info, $tenantId);

        if (! $resultado) {
            $this->agregarErrorNoDisponible('el servicio', 'REC-EDIT');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function eliminar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los servicios se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_recurso' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $resultado = $this->svcRecursoReservable->eliminar($datos['id_recurso'], $tenantId);

        if (! $resultado) {
            $this->agregarErrorNoDisponible('el servicio', 'REC-ELIM');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function listar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los servicios se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($this->svcRecursoReservable->listar($tenantId), 'recursos');

        return $this->sendResponse();
    }
}
