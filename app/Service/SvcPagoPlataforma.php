<?php

namespace App\Service;

use App\Models\PagoPlataforma;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Libro de pagos de la plataforma: registro INTERNO del super admin.
 *
 * El id_negocio llega como parámetro explícito (aquí lo elige el super admin,
 * que trabaja entre negocios); nunca se lee de session(). El consecutivo se
 * asigna de forma atómica y NUNCA se reutiliza, ni siquiera el de un anulado.
 */
class SvcPagoPlataforma
{
    /** Formas de pago aceptadas (cobro por fuera). */
    const METODOS = ['transferencia', 'nequi', 'qr_enlace', 'efectivo', 'otro'];

    const MONTO_MINIMO = 1;

    const MONTO_MAXIMO = 100000000;

    const MOTIVO_MINIMO = 5;

    const MOTIVO_MAXIMO = 200;

    /** Resultados de anular, para que el controller distinga el motivo. */
    const ANULAR_OK = 'ok';

    const ANULAR_NO_EXISTE = 'no_existe';

    const ANULAR_YA_ANULADO = 'ya_anulado';

    /**
     * Registra un pago DENTRO de la transacción que ya abrió quien activa o
     * renueva el módulo. No abre su propia transacción: si algo falla aquí,
     * la excepción sube y hace rollback de todo (módulo incluido), y el
     * consecutivo no se gasta.
     *
     * Devuelve el PagoPlataforma creado.
     */
    public function registrarPago(array $info): PagoPlataforma
    {
        $consecutivo = $this->siguienteConsecutivo();

        return PagoPlataforma::create([
            'tenant_id' => $info['tenant_id'],
            'id_modulo' => $info['id_modulo'] ?? null,
            'concepto' => $info['concepto'],
            'periodo_desde' => $info['periodo_desde'],
            'periodo_hasta' => $info['periodo_hasta'] ?? null,
            'monto' => $info['monto'],
            'metodo' => $info['metodo'],
            'referencia' => $info['referencia'] ?? null,
            'consecutivo' => $consecutivo,
            'id_usuario_registra' => $info['id_usuario_registra'],
            'fecha_registro' => now(),
        ]);
    }

    /**
     * Siguiente consecutivo: MAX + 1 sobre TODA la tabla (incluidos los
     * anulados), bajo bloqueo, dentro de la transacción del que llama. El
     * índice único de la base es la garantía final de que no se repite.
     */
    public function siguienteConsecutivo(): int
    {
        $max = (int) PagoPlataforma::lockForUpdate()->max('consecutivo');

        return $max + 1;
    }

    /** CP-0001: el consecutivo con 4 dígitos. */
    public static function formatoConsecutivo(int $consecutivo): string
    {
        return 'CP-'.str_pad((string) $consecutivo, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Listado del libro con filtros. Lista blanca de campos: nunca select('*').
     *
     * @param  string|null  $mes  AAAA-MM; null = todos.
     * @param  int|null  $idNegocio  null = todos.
     */
    public function listar(?string $mes = null, ?int $idNegocio = null): array
    {
        try {
            $query = PagoPlataforma::from('pagos_plataforma as pp')
                ->join('negocios as n', 'n.id_negocio', '=', 'pp.tenant_id')
                ->select(
                    'pp.id_pago',
                    'pp.consecutivo',
                    'pp.fecha_registro',
                    'pp.tenant_id',
                    'n.nombre_negocio',
                    'pp.concepto',
                    'pp.periodo_desde',
                    'pp.periodo_hasta',
                    'pp.monto',
                    'pp.metodo',
                    'pp.referencia',
                    'pp.comprobante_enviado_en',
                    'pp.anulado_en'
                )
                ->orderByDesc('pp.consecutivo');

            if ($idNegocio !== null) {
                $query->where('pp.tenant_id', $idNegocio);
            }

            if ($mes !== null) {
                $query->whereYear('pp.fecha_registro', (int) substr($mes, 0, 4))
                    ->whereMonth('pp.fecha_registro', (int) substr($mes, 5, 2));
            }

            return $query->get()
                ->map(function ($p) {
                    return [
                        'id_pago' => $p->id_pago,
                        'consecutivo' => self::formatoConsecutivo((int) $p->consecutivo),
                        'fecha' => Carbon::parse($p->fecha_registro)->format('Y-m-d'),
                        'nombre_negocio' => $p->nombre_negocio,
                        'concepto' => $p->concepto,
                        'periodo' => $this->textoPeriodo($p->periodo_desde, $p->periodo_hasta),
                        'monto' => (int) $p->monto,
                        'monto_formato' => self::formatoPesos((int) $p->monto),
                        'metodo' => $p->metodo,
                        'referencia' => $p->referencia,
                        'anulado' => $p->anulado_en !== null,
                        'comprobante_enviado' => $p->comprobante_enviado_en !== null,
                    ];
                })
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /** Total (suma de VIGENTES) y cantidad del mes/negocio filtrado. */
    public function totales(?string $mes = null, ?int $idNegocio = null): array
    {
        try {
            $query = PagoPlataforma::whereNull('anulado_en');

            if ($idNegocio !== null) {
                $query->where('tenant_id', $idNegocio);
            }

            if ($mes !== null) {
                $query->whereYear('fecha_registro', (int) substr($mes, 0, 4))
                    ->whereMonth('fecha_registro', (int) substr($mes, 5, 2));
            }

            return [
                'total' => (int) $query->sum('monto'),
                'cantidad' => (int) $query->count(),
            ];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return ['total' => 0, 'cantidad' => 0];
        }
    }

    /**
     * Anula un registro vigente. Conserva el consecutivo, lo excluye de los
     * totales y NO toca la vigencia del módulo. No se anula dos veces.
     */
    public function anular(int $idPago, int $idUsuario, string $motivo): string
    {
        try {
            return DB::transaction(function () use ($idPago, $idUsuario, $motivo) {
                $pago = PagoPlataforma::where('id_pago', $idPago)->lockForUpdate()->first();

                if ($pago === null) {
                    return self::ANULAR_NO_EXISTE;
                }

                if ($pago->anulado_en !== null) {
                    return self::ANULAR_YA_ANULADO;
                }

                PagoPlataforma::where('id_pago', $idPago)->update([
                    'anulado_en' => now(),
                    'anulado_por' => $idUsuario,
                    'motivo_anulacion' => $motivo,
                ]);

                return self::ANULAR_OK;
            });
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return self::ANULAR_NO_EXISTE;
        }
    }

    /** Marca el comprobante como enviado (tras encolar el correo). */
    public function marcarComprobanteEnviado(int $idPago): void
    {
        try {
            PagoPlataforma::where('id_pago', $idPago)->update(['comprobante_enviado_en' => now()]);
        } catch (\Exception $e) {
            Log::channel('database')->info($e);
        }
    }

    /**
     * Un pago vigente por su id, con los datos del negocio, para (re)enviar el
     * comprobante. Null si no existe o está anulado.
     */
    public function paraComprobante(int $idPago): ?array
    {
        try {
            $p = PagoPlataforma::from('pagos_plataforma as pp')
                ->join('negocios as n', 'n.id_negocio', '=', 'pp.tenant_id')
                ->where('pp.id_pago', $idPago)
                ->whereNull('pp.anulado_en')
                ->select(
                    'pp.id_pago', 'pp.tenant_id', 'pp.consecutivo', 'pp.fecha_registro',
                    'pp.concepto', 'pp.periodo_desde', 'pp.periodo_hasta', 'pp.monto',
                    'pp.metodo', 'pp.referencia', 'n.nombre_negocio', 'n.color_acento'
                )
                ->first();

            if ($p === null) {
                return null;
            }

            return [
                'id_pago' => (int) $p->id_pago,
                'tenant_id' => (int) $p->tenant_id,
                'consecutivo' => self::formatoConsecutivo((int) $p->consecutivo),
                'fecha' => Carbon::parse($p->fecha_registro)->format('Y-m-d'),
                'nombre_negocio' => $p->nombre_negocio,
                'color_acento' => $p->color_acento,
                'concepto' => $p->concepto,
                'periodo' => $this->textoPeriodo($p->periodo_desde, $p->periodo_hasta),
                'monto_formato' => self::formatoPesos((int) $p->monto),
                'metodo' => $p->metodo,
                'referencia' => $p->referencia,
            ];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return null;
        }
    }

    /** Filas para exportar a Excel (encabezados legibles, VIGENTES y anulados). */
    public function paraExportar(?string $mes = null, ?int $idNegocio = null): array
    {
        return array_map(function ($p) {
            return [
                $p['consecutivo'],
                $p['fecha'],
                $p['nombre_negocio'],
                $p['concepto'],
                $p['periodo'],
                $p['monto'],
                $p['metodo'],
                $p['referencia'] ?? '',
                $p['anulado'] ? 'Anulado' : 'Vigente',
            ];
        }, $this->listar($mes, $idNegocio));
    }

    public static function formatoPesos(int $monto): string
    {
        return '$'.number_format($monto, 0, ',', '.');
    }

    private function textoPeriodo($desde, $hasta): string
    {
        $d = Carbon::parse($desde)->format('Y-m-d');

        return $hasta === null ? 'Desde '.$d.' (sin vencimiento)' : $d.' a '.Carbon::parse($hasta)->format('Y-m-d');
    }
}
