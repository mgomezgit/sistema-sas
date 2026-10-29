<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Exports\LibroPagosExport;
use App\Mail\ComprobantePagoPlataforma;
use App\Service\SvcPagoPlataforma;
use App\Service\SvcProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Libro de pagos de la plataforma: SOLO el super admin (rutas con
 * solo.superadmin). Es un registro interno; ningún admin de negocio lo ve.
 *
 * El id_negocio del cuerpo/query es legítimo (el super admin elige), pero
 * siempre se valida. El registro de un pago NO vive aquí: ocurre dentro de la
 * transacción de activar/renovar (SuperAdminController). Aquí se consulta,
 * anula, reenvía y exporta.
 */
class PagoPlataformaController extends Controller
{
    private SvcPagoPlataforma $svcPago;

    public function __construct()
    {
        parent::__construct();

        $this->svcPago = new SvcPagoPlataforma;
    }

    /** Valida el filtro de mes (AAAA-MM exacto) y negocio (si viene, existe). */
    private function filtros(): array|false
    {
        $datos = $this->getRequestData();

        $mes = $datos['mes'] ?? null;

        if ($mes !== null && $mes !== '') {
            if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $mes) !== 1) {
                $this->agregarError('El mes no es válido. Usa el formato AAAA-MM, por ejemplo 2026-11.');

                return false;
            }
        } else {
            $mes = null;
        }

        $idNegocio = null;

        if (isset($datos['id_negocio']) && $datos['id_negocio'] !== '') {
            $this->setRequestValidationRules(['id_negocio' => 'integer|exists:negocios,id_negocio']);

            if (! $this->validateRequestRules()) {
                return false;
            }

            $idNegocio = (int) $datos['id_negocio'];
        }

        return ['mes' => $mes, 'id_negocio' => $idNegocio];
    }

    public function listar(): JsonResponse
    {
        $filtros = $this->filtros();

        if ($filtros === false) {
            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($this->svcPago->listar($filtros['mes'], $filtros['id_negocio']), 'pagos');
        $this->setDataResponse($this->svcPago->totales($filtros['mes'], $filtros['id_negocio']), 'totales');

        return $this->sendResponse();
    }

    /** Total y cantidad de pagos VIGENTES del mes actual, para el dashboard. */
    public function resumenMes(): JsonResponse
    {
        $mes = now()->format('Y-m');

        $this->respSinError();
        $this->setDataResponse(['mes' => $mes] + $this->svcPago->totales($mes, null), 'resumen');

        return $this->sendResponse();
    }

    public function anular(): JsonResponse
    {
        $this->setRequestValidationRules([
            'id_pago' => 'required|integer',
            'motivo' => 'required|string|min:'.SvcPagoPlataforma::MOTIVO_MINIMO.'|max:'.SvcPagoPlataforma::MOTIVO_MAXIMO,
        ], [
            'motivo.required' => 'Indica el motivo de la anulación.',
            'motivo.min' => 'El motivo debe tener al menos '.SvcPagoPlataforma::MOTIVO_MINIMO.' caracteres.',
            'motivo.max' => 'El motivo no puede pasar de '.SvcPagoPlataforma::MOTIVO_MAXIMO.' caracteres.',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();
        $resultado = $this->svcPago->anular((int) $datos['id_pago'], (int) session('id_usuario'), $datos['motivo']);

        if ($resultado === SvcPagoPlataforma::ANULAR_NO_EXISTE) {
            $this->agregarError('No se encontró el pago que quieres anular.');

            return $this->sendResponse();
        }

        if ($resultado === SvcPagoPlataforma::ANULAR_YA_ANULADO) {
            $this->agregarError('Ese pago ya estaba anulado.');

            return $this->sendResponse();
        }

        Log::channel('database')->info(sprintf(
            'SUPERADMIN: pago de plataforma id %d ANULADO por %s. Motivo: %s',
            (int) $datos['id_pago'],
            (session('usuario') ?? '?').' (id '.(session('id_usuario') ?? '?').')',
            $datos['motivo']
        ));

        // Anular NO cambia la vigencia del módulo: para corregirla hay que
        // ajustarla en Módulos.
        $this->respSinError();
        $this->setDataResponse('Pago anulado. Si necesitas corregir la vigencia del módulo, ajústala en Módulos.', 'mensaje');

        return $this->sendResponse();
    }

    public function reenviar(): JsonResponse
    {
        $this->setRequestValidationRules(['id_pago' => 'required|integer']);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        // Solo para registros vigentes (paraComprobante excluye los anulados).
        $pago = $this->svcPago->paraComprobante((int) $datos['id_pago']);

        if ($pago === null) {
            $this->agregarError('Solo se puede reenviar el comprobante de un pago vigente.');

            return $this->sendResponse();
        }

        $emails = (new SvcProducto)->listarEmailsAdminsActivosPorNegocio([$pago['tenant_id']])[$pago['tenant_id']] ?? [];

        if (empty($emails)) {
            $this->agregarError('Ese negocio no tiene administradores activos con correo, así que no hay a quién enviarle el comprobante.');

            return $this->sendResponse();
        }

        Mail::to($emails)->queue(new ComprobantePagoPlataforma($pago));
        $this->svcPago->marcarComprobanteEnviado((int) $datos['id_pago']);

        Log::channel('database')->info(sprintf(
            'SUPERADMIN: comprobante del pago id %d REENVIADO por %s',
            (int) $datos['id_pago'],
            (session('usuario') ?? '?').' (id '.(session('id_usuario') ?? '?').')'
        ));

        $this->respSinError();
        $this->setDataResponse('Comprobante reenviado.', 'mensaje');

        return $this->sendResponse();
    }

    public function exportar()
    {
        $filtros = $this->filtros();

        if ($filtros === false) {
            return $this->sendResponse();
        }

        return Excel::download(
            new LibroPagosExport($this->svcPago->paraExportar($filtros['mes'], $filtros['id_negocio'])),
            'libro-pagos-'.date('Y-m-d').'.xlsx'
        );
    }
}
