<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Service\SvcCliente;
use Illuminate\Http\JsonResponse;

class ClienteController extends Controller
{
    protected SvcCliente $svcCliente;

    public function __construct()
    {
        parent::__construct();

        $this->svcCliente = new SvcCliente;
    }

    public function crear(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los clientes se gestionan desde la cuenta de cada negocio');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'nombre' => 'required',
            'telefono' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $info = [
            'tenant_id' => $tenantId,
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'email' => $datos['email'] ?? null,
            'documento_identidad' => $datos['documento_identidad'] ?? null,
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
            'notas' => $datos['notas'] ?? null,
            'usuario_registra' => session('nombre_usuario'),
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ];

        $idCliente = $this->svcCliente->crear($info);

        if ($idCliente === false) {
            $this->agregarError('No fue posible crear el cliente');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($idCliente, 'id_cliente');

        return $this->sendResponse();
    }

    public function editar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los clientes se gestionan desde la cuenta de cada negocio');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_cliente' => 'required',
            'nombre' => 'required',
            'telefono' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $info = [
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'email' => $datos['email'] ?? null,
            'documento_identidad' => $datos['documento_identidad'] ?? null,
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
            'notas' => $datos['notas'] ?? null,
        ];

        $resultado = $this->svcCliente->editar($datos['id_cliente'], $info, $tenantId);

        if (! $resultado) {
            $this->agregarError('No fue posible editar el cliente');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function eliminar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los clientes se gestionan desde la cuenta de cada negocio');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_cliente' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $resultado = $this->svcCliente->eliminar($datos['id_cliente'], $tenantId);

        if (! $resultado) {
            $this->agregarError('No fue posible eliminar el cliente');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function listar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los clientes se gestionan desde la cuenta de cada negocio');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($this->svcCliente->listar($tenantId), 'clientes');

        return $this->sendResponse();
    }
}
