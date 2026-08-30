<?php

namespace App\Service;

use App\Models\Reserva;
use Illuminate\Support\Facades\Log;

class SvcReserva
{
    public function crear($info)
    {
        try {
            $reserva = Reserva::create($info);

            return $reserva->id_reserva;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function editar($id, $info, $tenantId): bool
    {
        try {
            return (bool) Reserva::where('id_reserva', $id)
                ->where('tenant_id', $tenantId)
                ->update($info);
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function cambiarEstado($id, $estadoReserva, $tenantId): bool
    {
        try {
            return (bool) Reserva::where('id_reserva', $id)
                ->where('tenant_id', $tenantId)
                ->update(['estado_reserva' => $estadoReserva]);
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function eliminar($id, $tenantId): bool
    {
        try {
            return (bool) Reserva::where('id_reserva', $id)
                ->where('tenant_id', $tenantId)
                ->update(['estado' => 0]);
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function listar($tenantId, $fechaInicio = null, $fechaFin = null)
    {
        try {
            $query = Reserva::from('reservas as r')
                ->join('clientes as c', 'c.id_cliente', '=', 'r.id_cliente')
                ->join('recursos_reservables as rec', 'rec.id_recurso', '=', 'r.id_recurso')
                ->leftJoin('empleados as e', 'e.id_empleado', '=', 'r.id_empleado')
                ->select(
                    'r.id_reserva',
                    'r.id_cliente',
                    'r.id_recurso',
                    'r.id_empleado',
                    'r.fecha_reserva',
                    'r.hora_inicio',
                    'r.hora_fin',
                    'r.estado_reserva',
                    'r.notas',
                    'c.nombre as nombre_cliente',
                    'c.telefono as telefono_cliente',
                    'rec.nombre as nombre_recurso',
                    'rec.duracion_minutos',
                    'e.nombre as nombre_empleado'
                )
                ->where('r.tenant_id', $tenantId)
                ->where('r.estado', 1);

            if ($fechaInicio !== null && $fechaFin !== null) {
                $query->whereBetween('r.fecha_reserva', [$fechaInicio, $fechaFin]);
            }

            return $query->get()->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function listarById($id, $tenantId)
    {
        try {
            return Reserva::from('reservas as r')
                ->join('clientes as c', 'c.id_cliente', '=', 'r.id_cliente')
                ->join('recursos_reservables as rec', 'rec.id_recurso', '=', 'r.id_recurso')
                ->leftJoin('empleados as e', 'e.id_empleado', '=', 'r.id_empleado')
                ->select(
                    'r.id_reserva',
                    'r.id_cliente',
                    'r.id_recurso',
                    'r.id_empleado',
                    'r.fecha_reserva',
                    'r.hora_inicio',
                    'r.hora_fin',
                    'r.estado_reserva',
                    'r.notas',
                    'c.nombre as nombre_cliente',
                    'c.telefono as telefono_cliente',
                    'rec.nombre as nombre_recurso',
                    'rec.duracion_minutos',
                    'e.nombre as nombre_empleado'
                )
                ->where('r.id_reserva', $id)
                ->where('r.tenant_id', $tenantId)
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function verificarDisponibilidad($idEmpleado, $fecha, $horaInicio, $horaFin, $tenantId, $idReservaExcluir = null): bool
    {
        try {
            $query = Reserva::where('tenant_id', $tenantId)
                ->where('id_empleado', $idEmpleado)
                ->where('fecha_reserva', $fecha)
                ->where('estado_reserva', '!=', 'cancelada')
                ->where('estado', 1)
                ->whereNot(function ($query) use ($horaInicio, $horaFin) {
                    $query->where('hora_fin', '<=', $horaInicio)
                        ->orWhere('hora_inicio', '>=', $horaFin);
                });

            if ($idReservaExcluir !== null) {
                $query->where('id_reserva', '!=', $idReservaExcluir);
            }

            return ! $query->exists();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }
}
