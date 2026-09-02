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
     * Verifica que quien llama sea el administrador de un negocio.
     * Retorna el mensaje de error si no lo es, o null si puede continuar.
     */
    private function validarAccesoAdmin(string $accion): ?string
    {
        if (session('tenant_id') === null) {
            return 'La '.$accion.' se gestiona desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.';
        }

        if (Rol::esRolEmpleado(session('id_rol'))) {
            return 'No tienes permiso para '.($accion === 'configuración' ? 'ver la configuración' : 'personalizar el tema').'. Pídeselo al administrador de tu negocio.';
        }

        return null;
    }

    public function obtenerConfiguracion(): JsonResponse
    {
        $error = $this->validarAccesoAdmin('configuración');

        if ($error !== null) {
            $this->agregarError($error);

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($this->svcNegocio->obtenerConfiguracion(session('tenant_id')), 'negocio');

        return $this->sendResponse();
    }

    public function actualizarConfiguracion(): JsonResponse
    {
        $error = $this->validarAccesoAdmin('configuración');

        if ($error !== null) {
            $this->agregarError($error);

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'nombre_negocio' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        // Los días llegan como arreglo desde el formulario y se guardan como
        // lista separada por comas.
        $diasAtencion = $datos['dias_atencion'] ?? null;

        if (is_array($diasAtencion)) {
            $diasAtencion = ! empty($diasAtencion) ? implode(',', $diasAtencion) : null;
        }

        $horaApertura = $datos['hora_apertura'] ?? null;
        $horaCierre = $datos['hora_cierre'] ?? null;

        if (! empty($horaApertura) && ! empty($horaCierre) && $horaCierre <= $horaApertura) {
            $this->agregarError('La hora de cierre debe ser posterior a la de apertura');

            return $this->sendResponse();
        }

        $info = [
            'nombre_negocio' => $datos['nombre_negocio'],
            'telefono_contacto' => $datos['telefono_contacto'] ?? null,
            'dias_atencion' => $diasAtencion,
            'hora_apertura' => $horaApertura ?: null,
            'hora_cierre' => $horaCierre ?: null,
        ];

        $resultado = $this->svcNegocio->actualizarConfiguracion(session('tenant_id'), $info);

        if (! $resultado) {
            $this->agregarErrorNoDisponible('el negocio', 'NEG-CONFIG');

            return $this->sendResponse();
        }

        // El nombre se muestra en el sidebar desde la sesión: hay que refrescarlo
        // o el usuario seguiría viendo el anterior hasta volver a entrar.
        session(['nombre_negocio_sesion' => $datos['nombre_negocio']]);

        $this->respSinError();

        return $this->sendResponse();
    }

    /**
     * Horario del negocio. Sin restricción de rol: el empleado también lo
     * necesita para ver las franjas del calendario.
     */
    public function obtenerHorario(): JsonResponse
    {
        $tenantId = session('tenant_id');

        $this->respSinError();
        $this->setDataResponse(
            $tenantId === null ? [] : $this->svcNegocio->obtenerHorario($tenantId),
            'horario'
        );

        return $this->sendResponse();
    }

    /**
     * Progreso del onboarding. A empleados y super admin se les responde
     * "ya terminado" para que el widget simplemente no aparezca, sin que el
     * frontend tenga que tratar un caso de error.
     */
    public function obtenerProgresoOnboarding(): JsonResponse
    {
        $tenantId = session('tenant_id');

        $this->respSinError();

        if ($tenantId === null || Rol::esRolEmpleado(session('id_rol'))) {
            // Misma forma que la respuesta normal, para que el frontend no tenga
            // que distinguir entre casos.
            $this->setDataResponse(
                ['tour_completado' => true, 'bienvenida_vista' => true, 'pasos' => []],
                'onboarding'
            );

            return $this->sendResponse();
        }

        $this->setDataResponse($this->svcNegocio->obtenerProgresoOnboarding($tenantId), 'onboarding');

        return $this->sendResponse();
    }

    /**
     * Marca que el negocio ya vio la pantalla de bienvenida (se muestra una
     * sola vez en la vida de la cuenta).
     */
    public function marcarBienvenidaVista(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('La bienvenida se gestiona desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        if (Rol::esRolEmpleado(session('id_rol'))) {
            $this->agregarError('No tienes permiso para completar esta acción. Pídeselo al administrador de tu negocio.');

            return $this->sendResponse();
        }

        if (! $this->svcNegocio->marcarBienvenidaVista($tenantId)) {
            $this->agregarErrorNoDisponible('el negocio', 'NEG-BIENVENIDA');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function completarOnboarding(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los primeros pasos se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        if (Rol::esRolEmpleado(session('id_rol'))) {
            $this->agregarError('No tienes permiso para completar los primeros pasos. Pídeselo al administrador de tu negocio.');

            return $this->sendResponse();
        }

        if (! $this->svcNegocio->completarOnboarding($tenantId)) {
            $this->agregarErrorNoDisponible('el negocio', 'NEG-ONBOARDING');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
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
