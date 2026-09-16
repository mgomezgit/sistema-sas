<?php

namespace App\Service;

use App\Models\ComisionTarifa;
use App\Models\PagoComision;
use App\Models\Reserva;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Comisiones de empleados.
 *
 * El precio de cada cita sale de recursos_reservables.precio, la MISMA fuente
 * que usa el reporte de ingresos por servicio (SvcReserva::reporteIngresosPorServicio).
 * La tabla reservas no guarda una copia del precio, así que no hay una segunda
 * fuente con la que este módulo pudiera desalinearse.
 *
 * El porcentaje se resuelve en cascada: primero la tarifa específica
 * empleado+servicio de comisiones_tarifas; si no hay, el porcentaje general
 * del empleado; si tampoco, cero.
 *
 * Ningún método lee session(): el tenant_id siempre llega como parámetro, para
 * que la regla multi-tenant sea verificable desde las pruebas sin montar una
 * sesión falsa.
 */
class SvcComision
{
    /**
     * Solo estas citas generan comisión: las que realmente se prestaron.
     */
    const ESTADO_RESERVA_COMISIONABLE = 'completada';

    /**
     * Informe de comisiones pendientes de pago del periodo.
     *
     * Excluye lo ya pagado (id_pago_comision no nulo), de modo que un periodo
     * que se solape con otro ya liquidado no vuelve a cobrar las mismas citas.
     */
    public function generarInforme($tenantId, $fechaInicio, $fechaFin, $idEmpleado = null)
    {
        try {
            $query = Reserva::from('reservas as r')
                // INNER JOIN a propósito: una cita sin empleado asignado no
                // le genera comisión a nadie.
                ->join('empleados as e', 'e.id_empleado', '=', 'r.id_empleado')
                ->join('recursos_reservables as rec', 'rec.id_recurso', '=', 'r.id_recurso')
                // La tarifa específica es opcional, por eso LEFT JOIN. Va acotada
                // al mismo negocio para que la tarifa de un tenant no pueda
                // aplicarse sobre las citas de otro.
                ->leftJoin('comisiones_tarifas as ct', function ($join) {
                    $join->on('ct.id_empleado', '=', 'r.id_empleado')
                        ->on('ct.id_recurso', '=', 'r.id_recurso')
                        ->on('ct.tenant_id', '=', 'r.tenant_id')
                        ->where('ct.estado', 1);
                })
                ->select(
                    'e.id_empleado',
                    'e.nombre as nombre_empleado',
                    'rec.id_recurso',
                    'rec.nombre as nombre_servicio',
                    DB::raw('COUNT(r.id_reserva) as cantidad_citas'),
                    DB::raw('SUM(rec.precio) as monto_servicios'),
                    DB::raw('COALESCE(ct.porcentaje_comision, e.porcentaje_comision, 0) as porcentaje_aplicado')
                )
                ->where('r.tenant_id', $tenantId)
                ->where('r.estado', 1)
                ->where('r.estado_reserva', self::ESTADO_RESERVA_COMISIONABLE)
                ->whereNull('r.id_pago_comision')
                ->whereBetween('r.fecha_reserva', [$fechaInicio, $fechaFin]);

            if (! empty($idEmpleado)) {
                $query->where('r.id_empleado', $idEmpleado);
            }

            $filas = $query->groupBy(
                'e.id_empleado',
                'e.nombre',
                'rec.id_recurso',
                'rec.nombre',
                'ct.porcentaje_comision',
                'e.porcentaje_comision'
            )
                ->orderBy('e.nombre')
                ->orderBy('rec.nombre')
                ->get()
                ->toArray() ?? [];

            return $this->armarInformePorEmpleado($filas);
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Convierte las filas planas (una por empleado+servicio) en la estructura
     * anidada que consume la vista: un bloque por empleado con sus servicios
     * y su total.
     */
    private function armarInformePorEmpleado(array $filas): array
    {
        $porEmpleado = [];

        foreach ($filas as $fila) {
            $idEmpleado = $fila['id_empleado'];
            $montoServicios = (float) $fila['monto_servicios'];
            $porcentaje = (float) $fila['porcentaje_aplicado'];
            $montoComision = round($montoServicios * $porcentaje / 100, 2);

            if (! isset($porEmpleado[$idEmpleado])) {
                $porEmpleado[$idEmpleado] = [
                    'id_empleado' => $idEmpleado,
                    'nombre_empleado' => $fila['nombre_empleado'],
                    'servicios' => [],
                    'total_comision' => 0.0,
                ];
            }

            $porEmpleado[$idEmpleado]['servicios'][] = [
                'id_recurso' => $fila['id_recurso'],
                'nombre_servicio' => $fila['nombre_servicio'],
                'cantidad_citas' => (int) $fila['cantidad_citas'],
                'porcentaje_aplicado' => $porcentaje,
                'monto_servicios' => $montoServicios,
                'monto_comision' => $montoComision,
            ];

            $porEmpleado[$idEmpleado]['total_comision'] = round(
                $porEmpleado[$idEmpleado]['total_comision'] + $montoComision,
                2
            );
        }

        return array_values($porEmpleado);
    }

    /**
     * Liquida el periodo de un empleado: guarda el pago y marca sus citas.
     *
     * El monto NUNCA llega por parámetro: se recalcula aquí con las mismas
     * reglas del informe, así un total manipulado en el formulario no tiene
     * ningún efecto.
     *
     * Todo ocurre dentro de DB::transaction, que revierte automáticamente si
     * se lanza cualquier excepción: o queda el pago con sus citas marcadas, o
     * no queda nada.
     *
     * @return int|false El id del pago creado, o false si no había nada que pagar.
     */
    public function marcarPeriodoPagado($tenantId, $idEmpleado, $fechaInicio, $fechaFin, $usuarioRegistra)
    {
        try {
            return DB::transaction(function () use ($tenantId, $idEmpleado, $fechaInicio, $fechaFin, $usuarioRegistra) {
                $informe = $this->generarInforme($tenantId, $fechaInicio, $fechaFin, $idEmpleado);

                // Sin comisiones pendientes no se crea un pago en cero: sería un
                // registro vacío en el historial.
                if (empty($informe)) {
                    throw new \RuntimeException('COM-PAGO-SIN-PENDIENTES');
                }

                $pago = PagoComision::create([
                    'tenant_id' => $tenantId,
                    'id_empleado' => $idEmpleado,
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin,
                    'monto_total' => $informe[0]['total_comision'],
                    'fecha_pago' => date('Y-m-d H:i:s'),
                    'usuario_registra' => $usuarioRegistra,
                    'fecha_registro' => date('Y-m-d H:i:s'),
                    'estado' => 1,
                ]);

                // Mismos filtros que el informe: se marcan exactamente las citas
                // que se acaban de contar, ni una más.
                Reserva::where('tenant_id', $tenantId)
                    ->where('id_empleado', $idEmpleado)
                    ->where('estado', 1)
                    ->where('estado_reserva', self::ESTADO_RESERVA_COMISIONABLE)
                    ->whereNull('id_pago_comision')
                    ->whereBetween('fecha_reserva', [$fechaInicio, $fechaFin])
                    ->update(['id_pago_comision' => $pago->id_pago_comision]);

                return $pago->id_pago_comision;
            });
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function listarTarifasEspecificas($tenantId, $idEmpleado = null)
    {
        try {
            $query = ComisionTarifa::from('comisiones_tarifas as ct')
                ->join('empleados as e', 'e.id_empleado', '=', 'ct.id_empleado')
                ->join('recursos_reservables as rec', 'rec.id_recurso', '=', 'ct.id_recurso')
                ->select(
                    'ct.id_comision_tarifa',
                    'ct.id_empleado',
                    'e.nombre as nombre_empleado',
                    'ct.id_recurso',
                    'rec.nombre as nombre_servicio',
                    'ct.porcentaje_comision',
                    'ct.estado'
                )
                ->where('ct.tenant_id', $tenantId)
                ->where('ct.estado', 1);

            if (! empty($idEmpleado)) {
                $query->where('ct.id_empleado', $idEmpleado);
            }

            return $query->orderBy('e.nombre')
                ->orderBy('rec.nombre')
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Crea la tarifa del par empleado+servicio, o actualiza la que ya exista.
     *
     * Se apoya en el índice único (tenant_id, id_empleado, id_recurso) para
     * que no puedan convivir dos tarifas de la misma combinación.
     */
    public function guardarTarifaEspecifica($tenantId, $info): bool
    {
        try {
            ComisionTarifa::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'id_empleado' => $info['id_empleado'],
                    'id_recurso' => $info['id_recurso'],
                ],
                [
                    'porcentaje_comision' => $info['porcentaje_comision'],
                    'usuario_registra' => $info['usuario_registra'] ?? null,
                    'fecha_registro' => date('Y-m-d H:i:s'),
                    // Guardar sobre una tarifa dada de baja la reactiva.
                    'estado' => 1,
                ]
            );

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function eliminarTarifaEspecifica($id, $tenantId): bool
    {
        try {
            $query = ComisionTarifa::where('id_comision_tarifa', $id)->where('tenant_id', $tenantId);

            if (! $query->exists()) {
                return false;
            }

            $query->update(['estado' => 0]);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function listarHistorialPagos($tenantId, $idEmpleado = null)
    {
        try {
            $query = PagoComision::from('pagos_comisiones as p')
                ->join('empleados as e', 'e.id_empleado', '=', 'p.id_empleado')
                ->select(
                    'p.id_pago_comision',
                    'p.id_empleado',
                    'e.nombre as nombre_empleado',
                    'p.fecha_inicio',
                    'p.fecha_fin',
                    'p.monto_total',
                    'p.fecha_pago',
                    'p.estado'
                )
                ->where('p.tenant_id', $tenantId)
                ->where('p.estado', 1);

            if (! empty($idEmpleado)) {
                $query->where('p.id_empleado', $idEmpleado);
            }

            return $query->orderByDesc('p.fecha_pago')
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }
}
