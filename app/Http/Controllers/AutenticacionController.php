<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Service\SvcUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AutenticacionController extends Controller
{
    protected SvcUsuario $svcUsuario;

    public function __construct()
    {
        parent::__construct();

        $this->svcUsuario = new SvcUsuario;
    }

    public function mostrarLogin()
    {
        $templateView = [];

        return view('login', $templateView);
    }

    public function validarLogin(): JsonResponse
    {
        $this->setRequestValidationRules([
            'email' => 'required|email',
            'clave' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $usuario = $this->svcUsuario->getUsuarioByEmail($datos['email']);

        if (! empty($usuario) && Hash::check($datos['clave'], $usuario['clave'])) {
            // Si la cuenta corresponde a un empleado, se guarda su id en la sesión
            // para poder filtrar "sus" citas. Un admin o super admin queda en null.
            $idEmpleado = Empleado::where('id_usuario', $usuario['id_usuario'])
                ->where('estado', 1)
                ->value('id_empleado');

            session([
                'id_usuario' => $usuario['id_usuario'],
                'usuario' => $usuario['usuario'],
                'nombre_usuario' => $usuario['nombre'],
                'email' => $usuario['email'],
                'tenant_id' => $usuario['tenant_id'],
                'id_rol' => $usuario['id_rol'],
                'id_empleado' => $idEmpleado ?: null,
                'app_sesion' => 'xLXAiX0fFTjLKEiJam7X57',
            ]);

            $this->respSinError();
        } else {
            $this->agregarError('El correo o la contraseña no son correctos. Verifica los datos e inténtalo de nuevo.');
        }

        return $this->sendResponse();
    }

    public function logout()
    {
        session()->flush();

        return redirect(url('/'));
    }
}
