<?php

namespace App\Http\Controllers;

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
            session([
                'id_usuario' => $usuario['id_usuario'],
                'usuario' => $usuario['usuario'],
                'nombre_usuario' => $usuario['nombre'],
                'email' => $usuario['email'],
                'tenant_id' => $usuario['tenant_id'],
                'id_rol' => $usuario['id_rol'],
                'app_sesion' => 'xLXAiX0fFTjLKEiJam7X57',
            ]);

            $this->respSinError();
        } else {
            $this->agregarError('Usuario o clave equivocadas');
        }

        return $this->sendResponse();
    }

    public function logout()
    {
        session()->flush();

        return redirect(url('/'));
    }
}
