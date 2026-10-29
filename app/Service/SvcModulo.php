<?php

namespace App\Service;

use App\Models\ModuloPlataforma;
use App\Models\Negocio;
use App\Models\NegocioModulo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Módulos de pago por negocio.
 *
 * Quién puede activar o desactivar es decisión del super admin y se controla
 * en las rutas/controladores; aquí no se lee session() en ningún método: el
 * tenant_id siempre llega como parámetro, para que la regla multi-tenant sea
 * verificable desde las pruebas sin montar una sesión falsa.
 *
 * Desactivar un módulo NO borra sus datos. Las tarifas y el historial de pagos
 * de comisiones siguen intactos, de modo que reactivarlo devuelve al negocio
 * exactamente lo que tenía.
 *
 * VIGENCIA: un módulo activo puede tener vigente_hasta (último día en que
 * funciona, inclusive) o no (sin vencimiento). estaActivo() aplica la vigencia
 * EN TIEMPO REAL, así que el acceso se corta al día siguiente de la fecha
 * aunque el proceso diario no haya corrido. origen y nota son privados del
 * super admin.
 */
class SvcModulo
{
    /** Orígenes seleccionables hoy en el panel. */
    const ORIGENES_SELECCIONABLES = ['cortesia', 'pagado_fuera'];

    /** Orígenes RESERVADOS para cuando existan planes y cobro automático (no seleccionables hoy). */
    const ORIGENES_RESERVADOS = ['plan', 'pasarela'];

    /** Tope de la fecha de vigencia hacia adelante. */
    const MAX_ANIOS_VIGENCIA = 3;

    /** Largo máximo de la nota. */
    const NOTA_MAXIMO = 200;

    /**
     * ¿Este negocio tiene el módulo activo HOY?
     *
     * Activo = activo = 1 Y (sin vencimiento O vigente_hasta >= hoy). Devuelve
     * false también cuando no existe la fila: un negocio del que no se sabe
     * nada no tiene el módulo. El default es negar, nunca conceder.
     */
    public function estaActivo($tenantId, $claveModulo): bool
    {
        try {
            if (empty($tenantId) || empty($claveModulo)) {
                return false;
            }

            return NegocioModulo::from('negocio_modulos as nm')
                ->join('modulos_plataforma as mp', 'mp.id_modulo', '=', 'nm.id_modulo')
                ->where('nm.tenant_id', $tenantId)
                ->where('mp.clave', $claveModulo)
                ->where('nm.activo', 1)
                ->where(function ($q) {
                    $q->whereNull('nm.vigente_hasta')
                        ->orWhereDate('nm.vigente_hasta', '>=', Carbon::today()->toDateString());
                })
                ->exists();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Valida y normaliza vigente_hasta, origen y nota.
     *
     * @return array{ok:bool, mensaje:string, vigente_hasta:?string, origen:?string, nota:?string}
     */
    public function validarVigencia($vigenteHasta, $origen, $nota): array
    {
        $fallo = fn (string $mensaje) => ['ok' => false, 'mensaje' => $mensaje, 'vigente_hasta' => null, 'origen' => null, 'nota' => null];

        // Fecha: vacía permitida (sin vencimiento); si viene, hoy o futura y no
        // más de 3 años adelante.
        $fecha = null;

        if ($vigenteHasta !== null && $vigenteHasta !== '') {
            try {
                $fecha = Carbon::createFromFormat('Y-m-d', (string) $vigenteHasta);
            } catch (\Exception $e) {
                $fecha = false;
            }

            if ($fecha === false || $fecha->format('Y-m-d') !== (string) $vigenteHasta) {
                return $fallo('La fecha de vigencia no es válida. Usa el formato AAAA-MM-DD.');
            }

            $hoy = Carbon::today();

            if ($fecha->lt($hoy)) {
                return $fallo('La fecha de vigencia no puede ser anterior a hoy.');
            }

            if ($fecha->gt($hoy->copy()->addYears(self::MAX_ANIOS_VIGENCIA))) {
                return $fallo('La fecha de vigencia no puede pasar de '.self::MAX_ANIOS_VIGENCIA.' años hacia adelante.');
            }
        }

        // Origen: vacío permitido; si viene, solo seleccionables. Los reservados
        // (plan, pasarela) se rechazan igual que cualquier valor desconocido.
        $origenLimpio = null;

        if ($origen !== null && $origen !== '') {
            if (! in_array($origen, self::ORIGENES_SELECCIONABLES, true)) {
                return $fallo('El origen no es válido. Elige "cortesia" o "pagado_fuera".');
            }

            $origenLimpio = $origen;
        }

        // Nota: vacía permitida; máximo 200 caracteres. Se guarda tal cual.
        $notaLimpia = null;

        if ($nota !== null && $nota !== '') {
            if (mb_strlen((string) $nota) > self::NOTA_MAXIMO) {
                return $fallo('La nota no puede pasar de '.self::NOTA_MAXIMO.' caracteres.');
            }

            $notaLimpia = (string) $nota;
        }

        return [
            'ok' => true,
            'mensaje' => '',
            'vigente_hasta' => $fecha ? $fecha->format('Y-m-d') : null,
            'origen' => $origenLimpio,
            'nota' => $notaLimpia,
        ];
    }

    /**
     * Activa (o renueva) el módulo para el negocio.
     *
     * Retrocompatible: con solo 3 argumentos funciona como antes (activar sin
     * vigencia). Si llegan vigente_hasta, origen o nota, se validan.
     *
     * - Fila inexistente o inactiva => ACTIVAR: activo = 1, fecha_activacion =
     *   ahora, fecha_desactivacion = null, banderas de aviso limpias.
     * - Fila ya activa => RENOVAR: cambia vigente_hasta/origen/nota, reinicia
     *   las banderas de aviso y NO toca fecha_activacion.
     */
    public function activarModulo($tenantId, $claveModulo, $usuarioRegistra = null, $vigenteHasta = null, $origen = null, $nota = null): bool
    {
        try {
            if (empty($tenantId)) {
                return false;
            }

            $idModulo = $this->idModuloPorClave($claveModulo);

            if (empty($idModulo)) {
                return false;
            }

            $v = $this->validarVigencia($vigenteHasta, $origen, $nota);

            if (! $v['ok']) {
                return false;
            }

            $ahora = now();

            // Banderas de aviso a cero: una vigencia nueva vuelve a poder avisar.
            $camposVigencia = [
                'vigente_hasta' => $v['vigente_hasta'],
                'origen' => $v['origen'],
                'nota' => $v['nota'],
                'aviso_7_enviado_en' => null,
                'aviso_1_enviado_en' => null,
                'vencido_avisado_en' => null,
            ];

            $fila = NegocioModulo::where('tenant_id', $tenantId)
                ->where('id_modulo', $idModulo)
                ->first();

            if (empty($fila)) {
                NegocioModulo::create($camposVigencia + [
                    'tenant_id' => $tenantId,
                    'id_modulo' => $idModulo,
                    'activo' => true,
                    'fecha_activacion' => $ahora,
                    'fecha_desactivacion' => null,
                    'usuario_registra' => $usuarioRegistra,
                    'fecha_registro' => $ahora,
                ]);

                return true;
            }

            // Renovar (ya activo) conserva fecha_activacion; activar uno inactivo
            // la reinicia y limpia la fecha de desactivación.
            $esRenovacion = (int) $fila->activo === 1;

            $campos = $camposVigencia + ['activo' => true];

            if (! $esRenovacion) {
                $campos['fecha_activacion'] = $ahora;
                $campos['fecha_desactivacion'] = null;
            }

            // El WHERE lleva el tenant_id además del id del registro: sin él,
            // un id de otro negocio bastaría para activarle el módulo.
            $afectadas = NegocioModulo::where('id_negocio_modulo', $fila->id_negocio_modulo)
                ->where('tenant_id', $tenantId)
                ->update($campos);

            return $afectadas > 0;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Desactiva el módulo para el negocio.
     *
     * No toca ni borra los datos del módulo: solo cierra la puerta.
     */
    public function desactivarModulo($tenantId, $claveModulo): bool
    {
        try {
            if (empty($tenantId)) {
                return false;
            }

            $idModulo = $this->idModuloPorClave($claveModulo);

            if (empty($idModulo)) {
                return false;
            }

            $afectadas = NegocioModulo::where('tenant_id', $tenantId)
                ->where('id_modulo', $idModulo)
                ->update([
                    'activo' => false,
                    'fecha_desactivacion' => now(),
                ]);

            return $afectadas > 0;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Catálogo completo con el estado que tiene para ESE negocio.
     *
     * LEFT JOIN a propósito: un módulo que el negocio nunca tuvo debe aparecer
     * como inactivo, no desaparecer de la lista.
     */
    public function listarModulosPorNegocio($tenantId)
    {
        try {
            $hoy = Carbon::today();

            return ModuloPlataforma::from('modulos_plataforma as mp')
                ->leftJoin('negocio_modulos as nm', function ($join) use ($tenantId) {
                    $join->on('nm.id_modulo', '=', 'mp.id_modulo')
                        ->where('nm.tenant_id', '=', $tenantId);
                })
                ->select(
                    'mp.id_modulo',
                    'mp.clave',
                    'mp.nombre',
                    'mp.descripcion',
                    DB::raw('COALESCE(nm.activo, 0) as activo'),
                    'nm.vigente_hasta',
                    'nm.origen',
                    'nm.nota',
                    'nm.fecha_activacion',
                    'nm.fecha_desactivacion'
                )
                ->where('mp.estado', 1)
                ->orderBy('mp.nombre')
                ->get()
                ->map(function ($modulo) use ($hoy) {
                    $activo = (int) $modulo->activo === 1;
                    $vigenteHasta = $modulo->vigente_hasta ? Carbon::parse($modulo->vigente_hasta) : null;

                    // Cálculos en el SERVIDOR: el frontend solo compone el texto.
                    $vencido = $activo && $vigenteHasta !== null && $vigenteHasta->lt($hoy);
                    $diasRestantes = $vigenteHasta !== null ? $hoy->diffInDays($vigenteHasta, false) : null;

                    return [
                        'id_modulo' => $modulo->id_modulo,
                        'clave' => $modulo->clave,
                        'nombre' => $modulo->nombre,
                        'descripcion' => $modulo->descripcion,
                        'activo' => $activo ? 1 : 0,
                        // Activo contando la vigencia en tiempo real (lo que ve el negocio).
                        'activo_vigente' => $activo && ! $vencido ? 1 : 0,
                        'vencido' => $vencido ? 1 : 0,
                        'vigente_hasta' => $vigenteHasta?->format('Y-m-d'),
                        'dias_restantes' => $diasRestantes,
                        // origen y nota SON PRIVADOS del super admin: este método es
                        // solo para su panel (modulos-de-negocio), nunca para un admin.
                        'origen' => $modulo->origen,
                        'nota' => $modulo->nota,
                    ];
                })
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Módulos que vencen pronto (en los próximos 7 días) o que vencieron hace
     * poco (en los últimos 7 días), de TODOS los negocios. Para la tarjeta del
     * dashboard y el endpoint del super admin. Lista blanca de campos; sin
     * origen ni nota (no hacen falta ahí). Máximo 20, los más próximos primero.
     *
     * SOLO para el super admin: cruza negocios, no filtra por tenant.
     */
    public function modulosPorVencer(int $limite = 20): array
    {
        try {
            $hoy = Carbon::today();
            $desde = $hoy->copy()->subDays(7)->toDateString();
            $hasta = $hoy->copy()->addDays(7)->toDateString();

            return NegocioModulo::from('negocio_modulos as nm')
                ->join('modulos_plataforma as mp', 'mp.id_modulo', '=', 'nm.id_modulo')
                ->join('negocios as n', 'n.id_negocio', '=', 'nm.tenant_id')
                ->whereNotNull('nm.vigente_hasta')
                ->where('nm.activo', 1)
                ->whereDate('nm.vigente_hasta', '>=', $desde)
                ->whereDate('nm.vigente_hasta', '<=', $hasta)
                ->orderBy('nm.vigente_hasta')
                ->limit($limite)
                ->get([
                    'n.nombre_negocio',
                    'mp.nombre as nombre_modulo',
                    'nm.vigente_hasta',
                    'nm.origen',
                ])
                ->map(function ($fila) use ($hoy) {
                    return [
                        'nombre_negocio' => $fila->nombre_negocio,
                        'nombre_modulo' => $fila->nombre_modulo,
                        'vigente_hasta' => Carbon::parse($fila->vigente_hasta)->format('Y-m-d'),
                        'dias' => $hoy->diffInDays(Carbon::parse($fila->vigente_hasta), false),
                        'origen' => $fila->origen,
                    ];
                })
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * ¿Está vencido el módulo para este negocio? (activo = 1 pero vigente_hasta
     * ya pasó.) Lo usa VerificarModuloActivo para mostrar "venció el ..." en vez
     * del mensaje genérico. Devuelve la fecha (Y-m-d) o null si no está vencido.
     */
    public function fechaDeVencimiento($tenantId, $claveModulo): ?string
    {
        try {
            if (empty($tenantId) || empty($claveModulo)) {
                return null;
            }

            $fila = NegocioModulo::from('negocio_modulos as nm')
                ->join('modulos_plataforma as mp', 'mp.id_modulo', '=', 'nm.id_modulo')
                ->where('nm.tenant_id', $tenantId)
                ->where('mp.clave', $claveModulo)
                ->where('nm.activo', 1)
                ->whereNotNull('nm.vigente_hasta')
                ->whereDate('nm.vigente_hasta', '<', Carbon::today()->toDateString())
                ->value('nm.vigente_hasta');

            return $fila ? Carbon::parse($fila)->format('Y-m-d') : null;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return null;
        }
    }

    /**
     * Resumen de todos los negocios con el estado de cada módulo.
     *
     * SOLO para el super admin: cruza todos los negocios, así que no filtra por
     * tenant a propósito. No debe exponerse en ninguna ruta de negocio.
     */
    public function listarNegociosConModulos()
    {
        try {
            return Negocio::from('negocios as n')
                ->crossJoin('modulos_plataforma as mp')
                ->leftJoin('negocio_modulos as nm', function ($join) {
                    $join->on('nm.tenant_id', '=', 'n.id_negocio')
                        ->on('nm.id_modulo', '=', 'mp.id_modulo');
                })
                ->select(
                    'n.id_negocio',
                    'n.nombre_negocio',
                    'mp.id_modulo',
                    'mp.clave',
                    'mp.nombre as nombre_modulo',
                    DB::raw('COALESCE(nm.activo, 0) as activo'),
                    'nm.fecha_activacion'
                )
                ->where('mp.estado', 1)
                ->orderBy('n.nombre_negocio')
                ->orderBy('mp.nombre')
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Base común para el proceso diario: módulos activos con vencimiento, con
     * los datos del negocio y del módulo que necesitan el apagado y los correos.
     */
    private function queryProcesoVencimientos()
    {
        return NegocioModulo::from('negocio_modulos as nm')
            ->join('modulos_plataforma as mp', 'mp.id_modulo', '=', 'nm.id_modulo')
            ->join('negocios as n', 'n.id_negocio', '=', 'nm.tenant_id')
            ->where('nm.activo', 1)
            ->whereNotNull('nm.vigente_hasta')
            ->select(
                'nm.id_negocio_modulo',
                'nm.tenant_id',
                'nm.vigente_hasta',
                'mp.clave as clave_modulo',
                'mp.nombre as nombre_modulo',
                'n.nombre_negocio',
                'n.estado as negocio_estado',
                'n.color_acento'
            );
    }

    /**
     * Filas activas cuya vigencia YA pasó (vigente_hasta < hoy): hay que
     * apagarlas. SOLO proceso de sistema (sin sesión).
     */
    public function modulosParaApagar(): array
    {
        try {
            return $this->queryProcesoVencimientos()
                ->whereDate('nm.vigente_hasta', '<', Carbon::today()->toDateString())
                ->get()
                ->map(fn ($f) => (array) $f->getAttributes())
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Filas activas que vencen EXACTAMENTE dentro de $dias días y a las que
     * todavía no se les envió ese aviso (bandera nula). SOLO proceso de sistema.
     *
     * @param  int  $dias  7 o 1.
     */
    public function modulosEnAviso(int $dias): array
    {
        try {
            $bandera = $dias === 7 ? 'nm.aviso_7_enviado_en' : 'nm.aviso_1_enviado_en';
            $fecha = Carbon::today()->copy()->addDays($dias)->toDateString();

            return $this->queryProcesoVencimientos()
                ->whereDate('nm.vigente_hasta', '=', $fecha)
                ->whereNull($bandera)
                ->get()
                ->map(fn ($f) => (array) $f->getAttributes())
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Traduce la clave técnica del módulo a su id del catálogo.
     */
    private function idModuloPorClave($claveModulo)
    {
        if (empty($claveModulo)) {
            return null;
        }

        return ModuloPlataforma::where('clave', $claveModulo)
            ->where('estado', 1)
            ->value('id_modulo');
    }
}
