<?php

namespace App\Service;

use App\Models\ModuloPlataforma;
use App\Models\Negocio;
use App\Models\NegocioModulo;
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
 */
class SvcModulo
{
    /**
     * ¿Este negocio tiene el módulo activo?
     *
     * Devuelve false también cuando no existe la fila: un negocio del que no
     * se sabe nada no tiene el módulo. El default es negar, nunca conceder.
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
                ->exists();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Activa el módulo para el negocio, creando la fila si todavía no existe.
     */
    public function activarModulo($tenantId, $claveModulo, $usuarioRegistra = null): bool
    {
        try {
            if (empty($tenantId)) {
                return false;
            }

            $idModulo = $this->idModuloPorClave($claveModulo);

            if (empty($idModulo)) {
                return false;
            }

            $ahora = now();

            $fila = NegocioModulo::where('tenant_id', $tenantId)
                ->where('id_modulo', $idModulo)
                ->first();

            if (empty($fila)) {
                NegocioModulo::create([
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

            // El WHERE lleva el tenant_id además del id del registro: sin él,
            // un id de otro negocio bastaría para activarle el módulo.
            $afectadas = NegocioModulo::where('id_negocio_modulo', $fila->id_negocio_modulo)
                ->where('tenant_id', $tenantId)
                ->update([
                    'activo' => true,
                    'fecha_activacion' => $ahora,
                    'fecha_desactivacion' => null,
                ]);

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
                    'nm.fecha_activacion',
                    'nm.fecha_desactivacion'
                )
                ->where('mp.estado', 1)
                ->orderBy('mp.nombre')
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
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
