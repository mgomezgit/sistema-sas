<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Service\SvcEmpleado;
use App\Service\SvcUsuario;
use Illuminate\Http\JsonResponse;

class EmpleadoController extends Controller
{
    protected SvcEmpleado $svcEmpleado;

    protected SvcUsuario $svcUsuario;

    public function __construct()
    {
        parent::__construct();

        $this->svcEmpleado = new SvcEmpleado;
        $this->svcUsuario = new SvcUsuario;
    }

    public function crear(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los empleados se gestionan desde la cuenta de cada negocio');

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
            'cargo' => $datos['cargo'] ?? null,
            'porcentaje_comision' => $datos['porcentaje_comision'] ?? null,
            'id_usuario' => null,
            'usuario_registra' => session('nombre_usuario'),
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ];

        $idEmpleado = $this->svcEmpleado->crear($info);

        if ($idEmpleado === false) {
            $this->agregarError('No fue posible crear el empleado');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($idEmpleado, 'id_empleado');

        return $this->sendResponse();
    }

    public function editar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los empleados se gestionan desde la cuenta de cada negocio');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_empleado' => 'required',
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
            'cargo' => $datos['cargo'] ?? null,
            'porcentaje_comision' => $datos['porcentaje_comision'] ?? null,
        ];

        $resultado = $this->svcEmpleado->editar($datos['id_empleado'], $info, $tenantId);

        if (! $resultado) {
            $this->agregarError('No fue posible editar el empleado');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function eliminar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los empleados se gestionan desde la cuenta de cada negocio');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_empleado' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $resultado = $this->svcEmpleado->eliminar($datos['id_empleado'], $tenantId);

        if (! $resultado) {
            $this->agregarError('No fue posible eliminar el empleado');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function listar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los empleados se gestionan desde la cuenta de cada negocio');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($this->svcEmpleado->listar($tenantId), 'empleados');

        return $this->sendResponse();
    }

    public function crearAcceso(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los empleados se gestionan desde la cuenta de cada negocio');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_empleado' => 'required',
            'usuario' => 'required',
            'email' => 'required|email',
            'clave' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $empleado = $this->svcEmpleado->listarById($datos['id_empleado'], $tenantId);

        if (empty($empleado)) {
            $this->agregarError('Empleado no encontrado');

            return $this->sendResponse();
        }

        $idRol = Rol::where('nombre_rol', 'empleado')->value('id_rol');

        if (! $idRol) {
            $this->agregarError('No existe el rol "empleado" configurado en el sistema');

            return $this->sendResponse();
        }

        $datosUsuario = [
            'usuario' => $datos['usuario'],
            'nombre' => $empleado[0]['nombre'],
            'email' => $datos['email'],
            'clave' => $datos['clave'],
            'tenant_id' => $tenantId,
            'id_rol' => $idRol,
            'usuario_registra' => session('nombre_usuario'),
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ];

        $idUsuarioCreado = $this->svcUsuario->crear($datosUsuario);

        if ($idUsuarioCreado === false) {
            $this->agregarError('No fue posible crear el acceso del empleado');

            return $this->sendResponse();
        }

        $vinculado = $this->svcEmpleado->vincularUsuario($datos['id_empleado'], $idUsuarioCreado, $tenantId);

        if (! $vinculado) {
            $this->agregarError('El usuario se creó pero no fue posible vincularlo al empleado');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($idUsuarioCreado, 'id_usuario');

        return $this->sendResponse();
    }
}
