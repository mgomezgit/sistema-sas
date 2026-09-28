<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Service\SvcModulo;
use App\Service\SvcSuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Endpoints del panel del super admin (request/superadmin/...).
 *
 * Todas sus rutas llevan solo.superadmin: sesión válida Y rol super_admin. A
 * diferencia del resto del sistema, aquí es LEGÍTIMO que el id_negocio llegue
 * en el cuerpo, porque el super admin elige sobre qué negocio actúa. Por eso
 * se valida siempre que exista, en vez de ignorarlo.
 *
 * Los módulos solo se activan o desactivan, nunca se eliminan: sus datos
 * (tarifas, historial de pagos) quedan intactos al desactivarlos.
 */
class SuperAdminController extends Controller
{
    private SvcSuperAdmin $svcSuperAdmin;

    private SvcModulo $svcModulo;

    public function __construct()
    {
        parent::__construct();

        $this->svcSuperAdmin = new SvcSuperAdmin;
        $this->svcModulo = new SvcModulo;
    }

    /** Reglas comunes: el negocio objetivo tiene que existir. */
    private function reglaNegocio(): array
    {
        return ['id_negocio' => 'required|integer|exists:negocios,id_negocio'];
    }

    /** La clave tiene que ser un módulo existente y vigente en el catálogo. */
    private function reglaModulo(): array
    {
        return [
            'clave_modulo' => [
                'required',
                'string',
                Rule::exists('modulos_plataforma', 'clave')->where('estado', 1),
            ],
        ];
    }

    private function mensajesValidacion(): array
    {
        return [
            'id_negocio.required' => 'Indica el negocio.',
            'id_negocio.integer' => 'El negocio indicado no es válido.',
            'id_negocio.exists' => 'Ese negocio no existe.',
            'clave_modulo.required' => 'Indica el módulo.',
            'clave_modulo.exists' => 'Ese módulo no existe en la plataforma.',
            'estado.required' => 'Indica el estado.',
            'estado.in' => 'El estado solo puede ser 0 (suspendido) o 1 (activo).',
        ];
    }

    public function resumen(): JsonResponse
    {
        $this->respSinError();
        $this->setDataResponse($this->svcSuperAdmin->resumenPlataforma(), 'resumen');

        return $this->sendResponse();
    }

    public function negocios(): JsonResponse
    {
        $busqueda = $this->request->query('busqueda');

        $this->respSinError();
        $this->setDataResponse($this->svcSuperAdmin->listarNegocios($busqueda), 'negocios');

        return $this->sendResponse();
    }

    public function cambiarEstadoNegocio(): JsonResponse
    {
        $this->setRequestValidationRules(
            $this->reglaNegocio() + ['estado' => 'required|in:0,1'],
            $this->mensajesValidacion()
        );

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $resultado = $this->svcSuperAdmin->cambiarEstadoNegocio(
            (int) $datos['id_negocio'],
            (int) $datos['estado'],
            $this->quienActua()
        );

        if (! $resultado) {
            $this->agregarErrorNoDisponible('el negocio', 'SA-ESTADO');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function modulosDeNegocio(): JsonResponse
    {
        $this->setRequestValidationRules($this->reglaNegocio(), $this->mensajesValidacion());

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $this->respSinError();
        $this->setDataResponse($this->svcModulo->listarModulosPorNegocio((int) $datos['id_negocio']), 'modulos');

        return $this->sendResponse();
    }

    public function activarModulo(): JsonResponse
    {
        return $this->alternarModulo(true);
    }

    public function desactivarModulo(): JsonResponse
    {
        return $this->alternarModulo(false);
    }

    /**
     * Activar y desactivar comparten validación y registro. La lógica de la
     * activación vive en SvcModulo; aquí no se duplica.
     */
    private function alternarModulo(bool $activar): JsonResponse
    {
        $this->setRequestValidationRules(
            $this->reglaNegocio() + $this->reglaModulo(),
            $this->mensajesValidacion()
        );

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();
        $idNegocio = (int) $datos['id_negocio'];
        $clave = $datos['clave_modulo'];

        $resultado = $activar
            ? $this->svcModulo->activarModulo($idNegocio, $clave, $this->quienActua())
            : $this->svcModulo->desactivarModulo($idNegocio, $clave);

        // Desactivar un módulo que el negocio nunca tuvo no es un error: el
        // estado final pedido (apagado) ya se cumple.
        if (! $resultado && $activar) {
            $this->agregarErrorNoDisponible('el módulo', 'SA-MODULO');

            return $this->sendResponse();
        }

        Log::channel('database')->info(sprintf(
            'SUPERADMIN: modulo "%s" %s en el negocio %d por %s el %s',
            $clave,
            $activar ? 'ACTIVADO' : 'DESACTIVADO',
            $idNegocio,
            $this->quienActua(),
            now()->format('Y-m-d H:i:s')
        ));

        $this->respSinError();

        return $this->sendResponse();
    }

    /** Quién hizo el cambio, para el registro de auditoría. */
    private function quienActua(): string
    {
        return (session('usuario') ?? 'desconocido').' (id '.(session('id_usuario') ?? '?').')';
    }
}
