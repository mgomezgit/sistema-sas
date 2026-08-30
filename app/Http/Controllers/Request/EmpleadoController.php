<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\Usuario;
use App\Service\SvcEmpleado;
use App\Service\SvcUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            $this->agregarError('Los empleados se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

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
            $this->agregarErrorSistema('EMP-CREAR');

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
            $this->agregarError('Los empleados se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

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
            $this->agregarErrorNoDisponible('el empleado', 'EMP-EDIT');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function eliminar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los empleados se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

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
            $this->agregarErrorNoDisponible('el empleado', 'EMP-ELIM');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function listar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los empleados se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

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
            $this->agregarError('Los empleados se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

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
            $this->agregarError('No se encontró el empleado. Es posible que haya sido eliminado. Recarga la página e inténtalo de nuevo.');

            return $this->sendResponse();
        }

        $idRol = Rol::where('nombre_rol', 'empleado')->value('id_rol');

        if (! $idRol) {
            $this->agregarErrorSistema('EMP-SIN-ROL');

            return $this->sendResponse();
        }

        if (! empty($empleado[0]['id_usuario'])) {
            $this->agregarError('Este empleado ya tiene un acceso creado. Si necesitas cambiarle la contraseña, hazlo desde el módulo de Usuarios.');

            return $this->sendResponse();
        }

        // El usuario y el email son únicos en toda la tabla, así que se avisa cuál de
        // los dos está ocupado antes de intentar el insert y caer en un error genérico.
        if (Usuario::where('usuario', $datos['usuario'])->exists()) {
            $this->agregarError('El usuario "'.$datos['usuario'].'" ya está en uso. Elige otro nombre de usuario, por ejemplo agregándole un número o un apellido.');

            return $this->sendResponse();
        }

        if (Usuario::where('email', $datos['email'])->exists()) {
            $this->agregarError('El correo "'.$datos['email'].'" ya está registrado en otra cuenta. Usa un correo diferente para este empleado.');

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

        // Crear el usuario y vincularlo van juntos: si la vinculación falla, no debe
        // quedar un usuario suelto sin empleado asociado.
        try {
            $idUsuarioCreado = DB::transaction(function () use ($datosUsuario, $datos, $tenantId) {
                $idUsuario = $this->svcUsuario->crear($datosUsuario);

                if ($idUsuario === false) {
                    throw new \RuntimeException('EMP-ACCESO-CREAR');
                }

                $vinculado = $this->svcEmpleado->vincularUsuario($datos['id_empleado'], $idUsuario, $tenantId);

                if (! $vinculado) {
                    throw new \RuntimeException('EMP-ACCESO-VINCULAR');
                }

                return $idUsuario;
            });
        } catch (\Exception $e) {
            Log::channel('database')->info($e);
            $this->agregarErrorSistema($e->getMessage());

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($idUsuarioCreado, 'id_usuario');

        return $this->sendResponse();
    }
}
