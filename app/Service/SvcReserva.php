<?php

namespace App\Service;

use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SvcReserva
{
    /* ================= MÉTRICAS DEL DASHBOARD ================= */

    /**
     * Reservas de hoy que siguen en pie (las canceladas no cuentan).
     */
    public function contarHoy($tenantId)
    {
        try {
            return Reserva::where('tenant_id', $tenantId)
                ->where('fecha_reserva', date('Y-m-d'))
                ->where('estado', 1)
                ->where('estado_reserva', '!=', 'cancelada')
                ->count();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return 0;
        }
    }

    /**
     * Próximas citas de hoy que todavía no empiezan, para el resumen del panel.
     * Se corta contra la hora actual, así la lista se va vaciando durante el día.
     */
    public function listarProximasHoy($tenantId, $limite = 4)
    {
        try {
            return Reserva::from('reservas as r')
                ->join('clientes as c', 'c.id_cliente', '=', 'r.id_cliente')
                ->join('recursos_reservables as rec', 'rec.id_recurso', '=', 'r.id_recurso')
                ->select(
                    'r.hora_inicio',
                    'c.nombre as nombre_cliente',
                    'rec.nombre as nombre_recurso'
                )
                ->where('r.tenant_id', $tenantId)
                ->where('r.fecha_reserva', date('Y-m-d'))
                ->where('r.estado', 1)
                ->where('r.estado_reserva', '!=', 'cancelada')
                ->where('r.hora_inicio', '>=', Carbon::now()->format('H:i:s'))
                ->orderBy('r.hora_inicio')
                ->limit($limite)
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Reservas de hoy repartidas por hora, para la franja visual del panel.
     *
     * Devuelve una entrada por cada hora de la jornada ("07:00" => 2, ...),
     * incluidas las horas sin reservas, para que el gráfico muestre la forma
     * real del día y no solo las horas ocupadas. Si el negocio no tiene horario
     * configurado, devuelve un array vacío.
     */
    public function distribucionHoyPorHora($tenantId)
    {
        try {
            $horario = (new SvcNegocio)->obtenerHorario($tenantId);

            $apertura = $horario['hora_apertura'] ?? null;
            $cierre = $horario['hora_cierre'] ?? null;

            if (empty($apertura) || empty($cierre)) {
                return [];
            }

            $horaApertura = (int) substr($apertura, 0, 2);
            $horaCierre = (int) substr($cierre, 0, 2);

            if ($horaCierre <= $horaApertura) {
                return [];
            }

            // La jornada arranca en cero y luego se suman las reservas que caen
            // en cada hora.
            $distribucion = [];

            for ($hora = $horaApertura; $hora < $horaCierre; $hora++) {
                $distribucion[sprintf('%02d:00', $hora)] = 0;
            }

            $reservas = Reserva::where('tenant_id', $tenantId)
                ->where('fecha_reserva', date('Y-m-d'))
                ->where('estado', 1)
                ->where('estado_reserva', '!=', 'cancelada')
                ->pluck('hora_inicio');

            foreach ($reservas as $horaInicio) {
                $franja = sprintf('%02d:00', (int) substr($horaInicio, 0, 2));

                if (array_key_exists($franja, $distribucion)) {
                    $distribucion[$franja]++;
                }
            }

            return $distribucion;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Ingresos del mes en curso: suma el precio del servicio de cada reserva
     * confirmada o completada. Las pendientes no se cuentan porque todavía
     * pueden caerse, y las canceladas obviamente tampoco.
     */
    public function calcularIngresosMes($tenantId)
    {
        try {
            return (float) Reserva::from('reservas as r')
                ->join('recursos_reservables as rec', 'rec.id_recurso', '=', 'r.id_recurso')
                ->where('r.tenant_id', $tenantId)
                ->where('r.estado', 1)
                ->whereIn('r.estado_reserva', ['confirmada', 'completada'])
                ->whereBetween('r.fecha_reserva', [date('Y-m-01'), date('Y-m-t')])
                ->sum('rec.precio');
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return 0;
        }
    }

    /**
     * Porcentaje de la jornada de hoy que ya está reservado: minutos agendados
     * sobre los minutos que el negocio atiende hoy. Devuelve 0 si hoy no se
     * atiende o si no hay horario configurado, que es también lo que evita
     * cualquier división por cero.
     */
    public function calcularOcupacionHoy($tenantId)
    {
        try {
            $horario = (new SvcNegocio)->obtenerHorario($tenantId);

            $apertura = $horario['hora_apertura'] ?? null;
            $cierre = $horario['hora_cierre'] ?? null;
            $diasAtencion = $horario['dias_atencion'] ?? null;

            if (empty($apertura) || empty($cierre)) {
                return 0;
            }

            // Si hay días configurados y hoy no es uno de ellos, no hay jornada.
            if (! empty($diasAtencion)) {
                $dias = array_map('intval', explode(',', $diasAtencion));

                if (! in_array((int) date('N'), $dias, true)) {
                    return 0;
                }
            }

            $minutosDisponibles = (strtotime($cierre) - strtotime($apertura)) / 60;

            if ($minutosDisponibles <= 0) {
                return 0;
            }

            $minutosReservados = (float) Reserva::from('reservas as r')
                ->join('recursos_reservables as rec', 'rec.id_recurso', '=', 'r.id_recurso')
                ->where('r.tenant_id', $tenantId)
                ->where('r.fecha_reserva', date('Y-m-d'))
                ->where('r.estado', 1)
                ->where('r.estado_reserva', '!=', 'cancelada')
                ->sum('rec.duracion_minutos');

            return (int) round($minutosReservados / $minutosDisponibles * 100);
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return 0;
        }
    }

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
            $query = Reserva::where('id_reserva', $id)->where('tenant_id', $tenantId);

            // Si el registro no existe (o es de otro negocio) sí es un fallo real. En
            // cambio, guardar sin cambiar ningún valor afecta 0 filas y es un caso válido.
            if (! $query->exists()) {
                return false;
            }

            $query->update($info);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function cambiarEstado($id, $estadoReserva, $tenantId): bool
    {
        try {
            $query = Reserva::where('id_reserva', $id)->where('tenant_id', $tenantId);

            if (! $query->exists()) {
                return false;
            }

            $query->update(['estado_reserva' => $estadoReserva]);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function eliminar($id, $tenantId): bool
    {
        try {
            $query = Reserva::where('id_reserva', $id)->where('tenant_id', $tenantId);

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

    public function listar($tenantId, $fechaInicio = null, $fechaFin = null, $idCliente = null, $estadoReserva = null)
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

            if ($idCliente !== null) {
                $query->where('r.id_cliente', $idCliente);
            }

            if ($estadoReserva !== null) {
                $query->where('r.estado_reserva', $estadoReserva);
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

    public function listarPorEmpleado($idEmpleado, $tenantId, $fecha)
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
                ->where('r.id_empleado', $idEmpleado)
                ->where('r.tenant_id', $tenantId)
                ->where('r.fecha_reserva', $fecha)
                ->where('r.estado', 1)
                ->orderBy('r.hora_inicio')
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Reservas de una fecha para el envío de recordatorios.
     *
     * A diferencia del resto de métodos, este NO recibe tenant_id: es un proceso
     * de sistema (comando programado) que recorre todos los negocios, no una
     * petición hecha por un usuario de un negocio concreto.
     */
    public function listarParaRecordatorio($fecha)
    {
        try {
            return Reserva::from('reservas as r')
                ->join('clientes as c', 'c.id_cliente', '=', 'r.id_cliente')
                ->join('recursos_reservables as rec', 'rec.id_recurso', '=', 'r.id_recurso')
                ->join('negocios as n', 'n.id_negocio', '=', 'r.tenant_id')
                ->leftJoin('empleados as e', 'e.id_empleado', '=', 'r.id_empleado')
                ->select(
                    'r.id_reserva',
                    'r.tenant_id',
                    'r.fecha_reserva',
                    'r.hora_inicio',
                    'r.hora_fin',
                    'r.estado_reserva',
                    'c.nombre as nombre_cliente',
                    'c.email as email_cliente',
                    'rec.nombre as nombre_recurso',
                    'e.nombre as nombre_empleado',
                    'n.nombre_negocio as nombre_negocio'
                )
                ->where('r.fecha_reserva', $fecha)
                ->whereIn('r.estado_reserva', ['pendiente', 'confirmada'])
                ->where('r.estado', 1)
                ->orderBy('r.hora_inicio')
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
