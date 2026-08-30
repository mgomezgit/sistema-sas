<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Service\SvcUsuario;
use Illuminate\Http\JsonResponse;

class UsuarioController extends Controller
{
    protected SvcUsuario $svcUsuario;

    public function __construct()
    {
        parent::__construct();

        $this->svcUsuario = new SvcUsuario;
    }

    public function crear(): JsonResponse
    {
        $this->setRequestValidationRules([
            'usuario' => 'required',
            'nombre' => 'required',
            'email' => 'required|email',
            'clave' => 'required',
            'tenant_id' => 'required',
            'id_rol' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $info = [
            'usuario' => $datos['usuario'],
            'nombre' => $datos['nombre'],
            'email' => $datos['email'],
            'clave' => $datos['clave'],
            'tenant_id' => $datos['tenant_id'],
            'id_rol' => $datos['id_rol'],
            'usuario_registra' => session('nombre_usuario'),
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ];

        if (session('tenant_id') !== null) {
            $info['tenant_id'] = session('tenant_id');
        }

        $idUsuario = $this->svcUsuario->crear($info);

        if ($idUsuario === false) {
            $this->agregarError('No fue posible crear el usuario');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($idUsuario, 'id_usuario');

        return $this->sendResponse();
    }

    public function editar(): JsonResponse
    {
        $this->setRequestValidationRules([
            'id_usuario' => 'required',
            'usuario' => 'required',
            'nombre' => 'required',
            'email' => 'required|email',
            'tenant_id' => 'required',
            'id_rol' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $info = [
            'usuario' => $datos['usuario'],
            'nombre' => $datos['nombre'],
            'email' => $datos['email'],
            'tenant_id' => $datos['tenant_id'],
            'id_rol' => $datos['id_rol'],
        ];

        if (array_key_exists('clave', $datos)) {
            $info['clave'] = $datos['clave'];
        }

        $tenantId = session('tenant_id');

        $resultado = $this->svcUsuario->editar($datos['id_usuario'], $info, $tenantId);

        if (! $resultado) {
            $this->agregarError('No fue posible editar el usuario');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function eliminar(): JsonResponse
    {
        $this->setRequestValidationRules([
            'id_usuario' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $tenantId = session('tenant_id');

        $resultado = $this->svcUsuario->eliminar($datos['id_usuario'], $tenantId);

        if (! $resultado) {
            $this->agregarError('No fue posible eliminar el usuario');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function listar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        $this->respSinError();
        $this->setDataResponse($this->svcUsuario->listar($tenantId), 'usuarios');

        return $this->sendResponse();
    }
}
