<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Mail\ComprobantePagoPlataforma;
use App\Models\ModuloPlataforma;
use App\Service\SvcChatbot;
use App\Service\SvcModulo;
use App\Service\SvcPagoPlataforma;
use App\Service\SvcProducto;
use App\Service\SvcSuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    private SvcPagoPlataforma $svcPago;

    public function __construct()
    {
        parent::__construct();

        $this->svcSuperAdmin = new SvcSuperAdmin;
        $this->svcModulo = new SvcModulo;
        $this->svcPago = new SvcPagoPlataforma;
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

        // ¿Se registra el pago en el libro? Solo al activar/renovar y solo con
        // origen 'pagado_fuera'. Devuelve false si la validación del pago falla
        // (ya dejó el error en la respuesta); null si no hay pago que registrar.
        // Solo se registra pago con origen 'pagado_fuera' y la casilla marcada.
        // Con 'cortesia' no hay pago aunque la casilla llegue marcada (se ignora).
        $datosPago = null;

        if ($activar && $origen === 'pagado_fuera' && $this->seRegistraPago($datos)) {
            $datosPago = $this->validarPago($datos, $origen);

            if ($datosPago === false) {
                return $this->sendResponse();
            }
        }

        // Si NO hay pago, el camino simple de siempre.
        if ($datosPago === null) {
            $resultado = $activar
                ? $this->svcModulo->activarModulo($idNegocio, $clave, $this->quienActua(), $vigenteHasta, $origen, $nota)
                : $this->svcModulo->desactivarModulo($idNegocio, $clave);

            if (! $resultado && $activar) {
                $this->agregarErrorNoDisponible('el módulo', 'SA-MODULO');

                return $this->sendResponse();
            }

            $this->registrarEnLog($clave, $activar, $idNegocio);
            $this->respSinError();

            return $this->sendResponse();
        }

        // CON pago: activar/renovar y crear el registro van en UNA transacción.
        // Si algo falla, no se activa, no se registra y no se gasta consecutivo.
        $concepto = (string) ModuloPlataforma::where('clave', $clave)->value('nombre');

        try {
            $idPago = DB::transaction(function () use ($idNegocio, $clave, $vigenteHasta, $origen, $nota, $datosPago, $concepto) {
                if (! $this->svcModulo->activarModulo($idNegocio, $clave, $this->quienActua(), $vigenteHasta, $origen, $nota)) {
                    throw new \RuntimeException('SA-MODULO-ACTIVAR');
                }

                $pago = $this->svcPago->registrarPago([
                    'tenant_id' => $idNegocio,
                    'id_modulo' => ModuloPlataforma::where('clave', $clave)->value('id_modulo'),
                    'concepto' => $concepto,
                    'periodo_desde' => Carbon::today()->toDateString(),
                    'periodo_hasta' => $vigenteHasta,
                    'monto' => $datosPago['monto'],
                    'metodo' => $datosPago['metodo'],
                    'referencia' => $datosPago['referencia'],
                    'id_usuario_registra' => (int) (session('id_usuario') ?? 0),
                ]);

                return (int) $pago->id_pago;
            });
        } catch (\Exception $e) {
            Log::channel('database')->info($e);
            $this->agregarErrorNoDisponible('el módulo', 'SA-MODULO-PAGO');

            return $this->sendResponse();
        }

        $this->registrarEnLog($clave, true, $idNegocio);

        Log::channel('database')->info(sprintf(
            'SUPERADMIN: pago de plataforma registrado (consecutivo interno id %d) en el negocio %d por %s',
            $idPago,
            $idNegocio,
            $this->quienActua()
        ));

        // El comprobante va DESPUÉS de confirmar la transacción.
        $this->enviarComprobante($idPago, $idNegocio);

        $this->respSinError();

        return $this->sendResponse();
    }

    /** ¿El cuerpo pide registrar el pago? (casilla marcada) */
    private function seRegistraPago(array $datos): bool
    {
        return in_array($datos['registrar_pago'] ?? null, [true, 1, '1', 'true', 'on'], true);
    }

    /**
     * Valida los campos del pago. Devuelve el arreglo limpio, o false tras dejar
     * el error en la respuesta.
     *
     * @return array{monto:int, metodo:string, referencia:?string}|false
     */
    private function validarPago(array $datos, ?string $origen)
    {
        if ($origen !== 'pagado_fuera') {
            $this->agregarError('El pago solo se registra cuando el origen es "Pagado por fuera".');

            return false;
        }

        $monto = $datos['monto'] ?? null;

        if (! (is_int($monto) || (is_string($monto) && preg_match('/^\d+$/', $monto) === 1))
            || (int) $monto < SvcPagoPlataforma::MONTO_MINIMO
            || (int) $monto > SvcPagoPlataforma::MONTO_MAXIMO) {
            $this->agregarError('El monto debe ser un número entero entre '.SvcPagoPlataforma::MONTO_MINIMO.' y '.number_format(SvcPagoPlataforma::MONTO_MAXIMO, 0, ',', '.').'.');

            return false;
        }

        if (! in_array($datos['metodo'] ?? null, SvcPagoPlataforma::METODOS, true)) {
            $this->agregarError('El método de pago no es válido.');

            return false;
        }

        $referencia = isset($datos['referencia']) && $datos['referencia'] !== '' ? (string) $datos['referencia'] : null;

        if ($referencia !== null && mb_strlen($referencia) > 100) {
            $this->agregarError('La referencia no puede pasar de 100 caracteres.');

            return false;
        }

        return ['monto' => (int) $monto, 'metodo' => $datos['metodo'], 'referencia' => $referencia];
    }

    /** Encola el comprobante a los administradores activos del negocio, si los hay. */
    private function enviarComprobante(int $idPago, int $idNegocio): void
    {
        try {
            $pago = $this->svcPago->paraComprobante($idPago);

            if ($pago === null) {
                return;
            }

            $emails = (new SvcProducto)->listarEmailsAdminsActivosPorNegocio([$idNegocio])[$idNegocio] ?? [];

            if (empty($emails)) {
                return; // El libro lo muestra como "Sin enviar".
            }

            Mail::to($emails)->queue(new ComprobantePagoPlataforma($pago));
            $this->svcPago->marcarComprobanteEnviado($idPago);
        } catch (\Exception $e) {
            Log::channel('database')->info($e);
        }
    }

    private function registrarEnLog(string $clave, bool $activar, int $idNegocio): void
    {
        Log::channel('database')->info(sprintf(
            'SUPERADMIN: modulo "%s" %s en el negocio %d por %s el %s',
            $clave,
            $activar ? 'ACTIVADO' : 'DESACTIVADO',
            $idNegocio,
            $this->quienActua(),
            now()->format('Y-m-d H:i:s')
        ));
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
