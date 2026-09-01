<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Service\SvcNegocio;
use Illuminate\Http\JsonResponse;

class NegocioController extends Controller
{
    protected SvcNegocio $svcNegocio;

    public function __construct()
    {
        parent::__construct();

        $this->svcNegocio = new SvcNegocio;
    }

    /**
     * Guarda el tema (modo + acento) del negocio. Aplica a todo su equipo,
     * así que solo el administrador del negocio puede cambiarlo.
     */
    public function actualizarTema(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('La personalización se gestiona desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        if (Rol::esRolEmpleado(session('id_rol'))) {
            $this->agregarError('No tienes permiso para personalizar el tema. Pídeselo al administrador de tu negocio.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'modo_tema' => 'required',
            'color_acento' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $resultado = $this->svcNegocio->actualizarTema($tenantId, $datos['modo_tema'], $datos['color_acento']);

        if (! $resultado) {
            $this->agregarError('No fue posible guardar la personalización, verifica los valores');

            return $this->sendResponse();
        }

        // La sesión se llena al hacer login, así que hay que refrescarla aquí:
        // sin esto el usuario recarga y sigue viendo el tema anterior. El resto
        // del equipo lo verá en su próximo inicio de sesión.
        session([
            'modo_tema' => $datos['modo_tema'],
            'color_acento' => $datos['color_acento'],
        ]);

        $this->respSinError();

        return $this->sendResponse();
    }
}
