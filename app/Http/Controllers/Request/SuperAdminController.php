<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Service\SvcChatbot;
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

        // Al activar/renovar se validan vigencia, origen y nota (vacíos
        // permitidos). La misma regla del Service, aquí para dar el mensaje.
        $vigenteHasta = null;
        $origen = null;
        $nota = null;

        if ($activar) {
            $v = $this->svcModulo->validarVigencia(
                $datos['vigente_hasta'] ?? null,
                $datos['origen'] ?? null,
                $datos['nota'] ?? null
            );

            if (! $v['ok']) {
                $this->agregarError($v['mensaje']);

                return $this->sendResponse();
            }

            $vigenteHasta = $v['vigente_hasta'];
            $origen = $v['origen'];
            $nota = $v['nota'];
        }

        $resultado = $activar
            ? $this->svcModulo->activarModulo($idNegocio, $clave, $this->quienActua(), $vigenteHasta, $origen, $nota)
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

    /**
     * Módulos que vencen pronto o vencieron hace poco, de todos los negocios,
     * para la tarjeta del dashboard. Lista blanca (SvcModulo::modulosPorVencer).
     */
    public function modulosPorVencer(): JsonResponse
    {
        $this->respSinError();
        $this->setDataResponse($this->svcModulo->modulosPorVencer(), 'modulos');

        return $this->sendResponse();
    }

    /* ================= CHATBOT: USO Y LÍMITE ================= */

    const MENSAJE_MES_INVALIDO = 'El mes no es válido. Usa el formato AAAA-MM de un mes que ya empezó, por ejemplo 2026-10.';

    const MENSAJE_LIMITE_INVALIDO = 'El límite debe estar vacío (usa el valor por defecto) o ser un número entero entre 0 y 10000. Con 0 el chatbot queda bloqueado para ese negocio.';

    /**
     * Uso del mes (el actual, o uno anterior con ?mes=AAAA-MM) de todos los
     * negocios, o de uno solo con ?id_negocio. Solo números.
     */
    public function chatbotUso(): JsonResponse
    {
        $svcChatbot = new SvcChatbot;
        $datos = $this->getRequestData();

        $mes = $svcChatbot->normalizarMes($datos['mes'] ?? null);

        if ($mes === null) {
            $this->agregarError(self::MENSAJE_MES_INVALIDO);

            return $this->sendResponse();
        }

        $idNegocio = null;

        if (isset($datos['id_negocio'])) {
            $this->setRequestValidationRules($this->reglaNegocio(), $this->mensajesValidacion());

            if (! $this->validateRequestRules()) {
                return $this->sendResponse();
            }

            $idNegocio = (int) $datos['id_negocio'];
        }

        $this->respSinError();
        $this->setDataResponse([
            'mes' => $mes,
            // Sin límite propio (null) rige el valor por defecto: la misma regla de SvcChatbot.
            'limite_por_defecto' => SvcChatbot::limiteEfectivo(null),
            'negocios' => $this->svcSuperAdmin->usoChatbotDeTodosLosNegocios($mes, $idNegocio),
        ]);

        return $this->sendResponse();
    }

    /** Números agregados de la plataforma para la tarjeta del dashboard. */
    public function chatbotResumen(): JsonResponse
    {
        $this->respSinError();
        $this->setDataResponse($this->svcSuperAdmin->resumenChatbot((new SvcChatbot)->mesActual()), 'resumen');

        return $this->sendResponse();
    }

    /**
     * Fija o quita el límite mensual propio del chatbot de un negocio.
     *
     * Validación estricta: el negocio tiene que existir, y "limite" tiene que
     * estar presente y ser vacío/null (usa el valor por defecto) o un entero
     * de 0 a 10000. Nada más: ni negativos, ni decimales, ni texto, ni
     * booleanos ni arreglos. Rechazado = no se toca nada.
     */
    public function chatbotLimite(): JsonResponse
    {
        $this->setRequestValidationRules($this->reglaNegocio(), $this->mensajesValidacion());

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        if (! array_key_exists('limite', $datos)) {
            $this->agregarError(self::MENSAJE_LIMITE_INVALIDO);

            return $this->sendResponse();
        }

        $valor = $datos['limite'];
        $limite = null;

        if ($valor !== null && $valor !== '') {
            $esEntero = is_int($valor) || (is_string($valor) && preg_match('/^\d{1,5}$/', $valor) === 1);

            if (! $esEntero || (int) $valor > SvcChatbot::LIMITE_MAXIMO_PROPIO || (int) $valor < 0) {
                $this->agregarError(self::MENSAJE_LIMITE_INVALIDO);

                return $this->sendResponse();
            }

            $limite = (int) $valor;
        }

        if (! $this->svcSuperAdmin->cambiarLimiteChatbot((int) $datos['id_negocio'], $limite, $this->quienActua())) {
            $this->agregarErrorNoDisponible('el negocio', 'SA-CHATBOT-LIMITE');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    /** Quién hizo el cambio, para el registro de auditoría. */
    private function quienActua(): string
    {
        return (session('usuario') ?? 'desconocido').' (id '.(session('id_usuario') ?? '?').')';
    }
}
