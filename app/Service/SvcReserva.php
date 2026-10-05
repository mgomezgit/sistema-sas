<?php

namespace App\Service;

use App\Models\Cliente;
use App\Models\HistorialEstadoReserva;
use App\Models\RecursoReservable;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SvcReserva
{
    /* Resultados posibles de cambiarEstado(). No es un bool porque el
       Controller necesita saber POR QUÉ no se hizo, para dar el mensaje justo. */
    const CAMBIO_HECHO = 'hecho';

    const CAMBIO_NO_DISPONIBLE = 'no_disponible';

    const CAMBIO_COMISION_PAGADA = 'comision_pagada';

    const CAMBIO_ERROR = 'error';

    /* ================= REPORTES ================= */

    /**
     * Detalle de reservas de un rango de fechas, para el reporte de ventas.
     * Los filtros de empleado y estado son opcionales: si no llegan, no acotan.
     */
    public function reportePorFecha($tenantId, $fechaInicio, $fechaFin, $idEmpleado = null, $estadoReserva = null)
    {
        try {
            $query = Reserva::from('reservas as r')
                ->join('clientes as c', 'c.id_cliente', '=', 'r.id_cliente')
                ->join('recursos_reservables as rec', 'rec.id_recurso', '=', 'r.id_recurso')
                ->leftJoin('empleados as e', 'e.id_empleado', '=', 'r.id_empleado')
                ->select(
                    'r.fecha_reserva',
                    'r.hora_inicio',
                    'r.hora_fin',
                    'c.nombre as nombre_cliente',
                    'rec.nombre as nombre_recurso',
                    'e.nombre as nombre_empleado',
                    'r.estado_reserva',
                    'rec.precio'
                )
                ->where('r.tenant_id', $tenantId)
                ->where('r.estado', 1)
                ->whereBetween('r.fecha_reserva', [$fechaInicio, $fechaFin]);

            if (! empty($idEmpleado)) {
                $query->where('r.id_empleado', $idEmpleado);
            }

            if (! empty($estadoReserva)) {
                $query->where('r.estado_reserva', $estadoReserva);
            }

            return $query->orderBy('r.fecha_reserva')
                ->orderBy('r.hora_inicio')
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Cuánto aportó cada servicio en el rango: número de reservas e ingresos.
     * Solo cuenta confirmadas y completadas, igual que los ingresos del panel,
     * porque las pendientes todavía pueden caerse y las canceladas no aportan.
     */
    public function reporteIngresosPorServicio($tenantId, $fechaInicio, $fechaFin)
    {
        try {
            return Reserva::from('reservas as r')
                ->join('recursos_reservables as rec', 'rec.id_recurso', '=', 'r.id_recurso')
                ->select(
                    'rec.nombre as nombre_recurso',
                    DB::raw('COUNT(r.id_reserva) as cantidad_reservas'),
                    DB::raw('SUM(rec.precio) as ingresos_totales')
                )
                ->where('r.tenant_id', $tenantId)
                ->where('r.estado', 1)
                ->whereIn('r.estado_reserva', ['confirmada', 'completada'])
                ->whereBetween('r.fecha_reserva', [$fechaInicio, $fechaFin])
                ->groupBy('r.id_recurso', 'rec.nombre')
                ->orderByDesc('ingresos_totales')
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /* ================= MÉTRICAS DEL DASHBOARD ================= */

    /**
     * Reservas de hoy que siguen en pie (las canceladas no cuentan).
     */
    public function contarHoy($tenantId)
    {
        try {
            return Reserva::where('tenant_id', $tenantId)
                ->where('fecha_reserva', Carbon::today()->toDateString())
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
                ->where('r.fecha_reserva', Carbon::today()->toDateString())
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
                ->where('fecha_reserva', Carbon::today()->toDateString())
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
                ->whereBetween('r.fecha_reserva', [
                    Carbon::now()->startOfMonth()->toDateString(),
                    Carbon::now()->endOfMonth()->toDateString(),
                ])
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

                if (! in_array(Carbon::now()->dayOfWeekIso, $dias, true)) {
                    return 0;
                }
            }

            // Aquí sí es correcto strtotime(): apertura/cierre son horas sueltas
            // ("08:00:00"), no fechas, y no dependen de qué día es hoy.
            $minutosDisponibles = (strtotime($cierre) - strtotime($apertura)) / 60;

            if ($minutosDisponibles <= 0) {
                return 0;
            }

            $minutosReservados = (float) Reserva::from('reservas as r')
                ->join('recursos_reservables as rec', 'rec.id_recurso', '=', 'r.id_recurso')
                ->where('r.tenant_id', $tenantId)
                ->where('r.fecha_reserva', Carbon::today()->toDateString())
                ->where('r.estado', 1)
                ->where('r.estado_reserva', '!=', 'cancelada')
                ->sum('rec.duracion_minutos');

            return (int) round($minutosReservados / $minutosDisponibles * 100);
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return 0;
        }
    }

    /**
     * Crea una SOLICITUD de cita pedida desde la página pública.
     *
     * Una solicitud no es una reserva confirmada: nace 'pendiente', sin
     * empleado asignado, y marcada con origen 'publico' para que el negocio
     * la reconozca como algo que todavía tiene que revisar.
     *
     * ⚠️ POR QUÉ AQUÍ NO SE ENVÍA NINGÚN CORREO AL CLIENTE
     *
     * El correo de "reserva confirmada" (App\Mail\ReservaConfirmada) NO vive
     * en este Service ni en ningún observer del modelo: se dispara en un único
     * sitio, ReservaController::crear(), justo después de llamar a crear().
     * Por eso crear la fila desde aquí no arrastra ese efecto, y por eso este
     * método puede apoyarse en el Model sin rodeos.
     *
     * Eso NO es casualidad que convenga mantener sin querer: si algún día ese
     * envío se mueve a un observer de Reserva o a un evento del modelo, esta
     * ruta empezaría a prometerle al cliente que su cita está confirmada
     * cuando el negocio ni siquiera la ha visto. Si eso llega a plantearse,
     * este método necesita quedar explícitamente fuera.
     *
     * Al cliente se le avisa cuando el negocio acepta o rechaza la solicitud,
     * que es cuando ReservaEstadoActualizado entra en juego (y que además
     * ignora a propósito el estado 'pendiente').
     *
     * @return int|false El id de la reserva creada, o false si no se pudo.
     */
    public function crearSolicitudPublica($tenantId, $info)
    {
        try {
            return DB::transaction(function () use ($tenantId, $info) {
                // El recurso tiene que ser de ESTE negocio y estar activo. Es
                // la comprobación que impide pedir en el negocio A un servicio
                // del negocio B mandando su id a mano.
                $recurso = RecursoReservable::select('id_recurso', 'nombre', 'duracion_minutos')
                    ->where('id_recurso', $info['id_recurso'])
                    ->where('tenant_id', $tenantId)
                    ->where('estado', 1)
                    ->first();

                if ($recurso === null) {
                    throw new \RuntimeException('SOL-RECURSO-INVALIDO');
                }

                if (Carbon::parse($info['fecha_reserva'])->lt(Carbon::today())) {
                    throw new \RuntimeException('SOL-FECHA-PASADA');
                }

                // Mismo formato que el flujo del admin: "HH:mm" del formulario
                // se normaliza a "HH:mm:ss" para guardar y comparar igual.
                $horaInicio = Carbon::parse($info['hora_inicio'])->format('H:i:s');
                $horaFin = Carbon::parse($horaInicio)->addMinutes($recurso->duracion_minutos)->format('H:i:s');

                // Se reutiliza la misma regla de horario que valida el admin:
                // el público no puede pedir cita en un día u hora en que el
                // negocio no atiende.
                if (! (new SvcNegocio)->estaDentroDelHorario($tenantId, $info['fecha_reserva'], $horaInicio, $horaFin)) {
                    throw new \RuntimeException('SOL-FUERA-DE-HORARIO');
                }

                $idCliente = $this->resolverClienteDeSolicitud($tenantId, $info);

                if ($idCliente === false) {
                    throw new \RuntimeException('SOL-CLIENTE');
                }

                $reserva = Reserva::create([
                    // El negocio SIEMPRE sale del parámetro, nunca de $info:
                    // lo resolvió el slug de la URL, y nada que mande el
                    // visitante puede cambiarlo.
                    'tenant_id' => $tenantId,
                    'id_cliente' => $idCliente,
                    'id_recurso' => $recurso->id_recurso,
                    // Sin empleado: repartir la cita es decisión del negocio.
                    'id_empleado' => null,
                    'fecha_reserva' => $info['fecha_reserva'],
                    'hora_inicio' => $horaInicio,
                    'hora_fin' => $horaFin,
                    'estado_reserva' => 'pendiente',
                    'origen' => 'publico',
                    'notas' => $info['notas'] ?? null,
                    'usuario_registra' => 'Pagina publica',
                    'fecha_registro' => date('Y-m-d H:i:s'),
                    'estado' => 1,
                ]);

                return $reserva->id_reserva;
            });
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Encuentra al cliente por su teléfono, o lo da de alta.
     *
     * Si ya existe se le refrescan nombre y correo cuando llegan distintos:
     * quien pide cita hoy manda datos más frescos que los de hace un año. No
     * se pisa un dato guardado con uno vacío.
     *
     * @return int|false
     */
    private function resolverClienteDeSolicitud($tenantId, $info)
    {
        $svcCliente = new SvcCliente;

        $existente = $svcCliente->buscarPorTelefono($info['telefono'], $tenantId);

        if ($existente !== null) {
            $cambios = [];

            if (! empty($info['nombre']) && $info['nombre'] !== $existente['nombre']) {
                $cambios['nombre'] = $info['nombre'];
            }

            if (! empty($info['email']) && $info['email'] !== $existente['email']) {
                $cambios['email'] = $info['email'];
            }

            if (! empty($cambios)) {
                Cliente::where('id_cliente', $existente['id_cliente'])
                    ->where('tenant_id', $tenantId)
                    ->update($cambios);
            }

            return $existente['id_cliente'];
        }

        return $svcCliente->crear([
            'tenant_id' => $tenantId,
            'nombre' => $info['nombre'],
            'telefono' => $info['telefono'],
            'email' => $info['email'] ?? null,
            'usuario_registra' => 'Pagina publica',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
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

    /**
     * Cambia el estado de una reserva y deja el rastro en historial_estados_reserva.
     *
     * Es el ÚNICO punto que escribe estado_reserva sobre una reserva existente:
     * lo usan el admin (modal de edición y círculos del panel de detalle, que
     * es también por donde se confirma una solicitud pública) y el empleado
     * (Mis Citas). Por eso las dos reglas viven aquí y no en los Controllers:
     *
     * 1. Con la comisión ya pagada (id_pago_comision no nulo) NO se cambia, sin
     *    excepción de rol: esa cita ya es parte de un pago confirmado.
     * 2. El cambio y su fila de historial van en la misma transacción: si uno
     *    falla, se revierten los dos.
     *
     * @param  int|null  $idUsuario  Quién hace el cambio (el Controller lo saca
     *                               de la sesión). Null = un proceso de sistema.
     * @return string Una de las constantes CAMBIO_*.
     */
    public function cambiarEstado($id, $estadoReserva, $tenantId, $idUsuario): string
    {
        try {
            return DB::transaction(function () use ($id, $estadoReserva, $tenantId, $idUsuario) {
                // lockForUpdate: dos cambios simultáneos sobre la misma reserva
                // no pueden leer el mismo "estado anterior".
                $reserva = Reserva::select('id_reserva', 'estado_reserva', 'id_pago_comision')
                    ->where('id_reserva', $id)
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                if ($reserva === null) {
                    return self::CAMBIO_NO_DISPONIBLE;
                }

                if ($reserva->id_pago_comision !== null) {
                    return self::CAMBIO_COMISION_PAGADA;
                }

                // Mismo estado: no hubo cambio real, así que no hay nada que
                // registrar. Se responde igual que siempre para no romper a
                // quien lo llame así.
                if ($reserva->estado_reserva === $estadoReserva) {
                    return self::CAMBIO_HECHO;
                }

                Reserva::where('id_reserva', $id)
                    ->where('tenant_id', $tenantId)
                    ->update(['estado_reserva' => $estadoReserva]);

                HistorialEstadoReserva::create([
                    'tenant_id' => $tenantId,
                    'id_reserva' => $reserva->id_reserva,
                    'estado_anterior' => $reserva->estado_reserva,
                    'estado_nuevo' => $estadoReserva,
                    'id_usuario' => $idUsuario,
                    'fecha_cambio' => date('Y-m-d H:i:s'),
                ]);

                return self::CAMBIO_HECHO;
            });
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return self::CAMBIO_ERROR;
        }
    }

    /**
     * Historial de cambios de estado de UNA reserva, del más antiguo al más
     * nuevo, con el nombre de quien hizo cada cambio ("Sistema" si no fue una
     * persona). Siempre acotado al negocio: nunca devuelve el historial de una
     * reserva de otro tenant, aunque se pida su id.
     */
    public function listarHistorialEstados($idReserva, $tenantId)
    {
        try {
            return HistorialEstadoReserva::from('historial_estados_reserva as h')
                ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'h.id_usuario')
                ->select(
                    'h.id_historial',
                    'h.estado_anterior',
                    'h.estado_nuevo',
                    'h.fecha_cambio',
                    'h.id_usuario',
                    'u.nombre as nombre_usuario'
                )
                ->where('h.id_reserva', $idReserva)
                ->where('h.tenant_id', $tenantId)
                ->orderBy('h.fecha_cambio')
                ->orderBy('h.id_historial')
                ->get()
                ->map(function ($fila) {
                    return [
                        'id_historial' => (int) $fila->id_historial,
                        'estado_anterior' => $fila->estado_anterior,
                        'estado_nuevo' => $fila->estado_nuevo,
                        'fecha_cambio' => $fila->fecha_cambio,
                        'nombre_usuario' => $fila->id_usuario === null ? 'Sistema' : ($fila->nombre_usuario ?? 'Usuario desconocido'),
                    ];
                })
                ->all();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
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

    /**
     * Solicitudes pendientes de confirmar, creadas desde la página pública.
     *
     * Solo estado='pendiente' Y origen='publico': una reserva pendiente creada
     * a mano por el admin (origen='admin') no es una solicitud que alguien
     * tenga que revisar, así que no entra aquí.
     *
     * Las más próximas primero, para que el admin atienda lo urgente antes.
     */
    public function listarSolicitudesPendientes($tenantId)
    {
        try {
            return Reserva::from('reservas as r')
                ->join('clientes as c', 'c.id_cliente', '=', 'r.id_cliente')
                ->join('recursos_reservables as rec', 'rec.id_recurso', '=', 'r.id_recurso')
                ->select(
                    'r.id_reserva',
                    'c.nombre as nombre_cliente',
                    'c.telefono as telefono_cliente',
                    'rec.nombre as nombre_servicio',
                    'r.fecha_reserva',
                    'r.hora_inicio'
                )
                ->where('r.tenant_id', $tenantId)
                ->where('r.estado', 1)
                ->where('r.estado_reserva', 'pendiente')
                ->where('r.origen', 'publico')
                ->orderBy('r.fecha_reserva')
                ->orderBy('r.hora_inicio')
                ->get()
                ->toArray() ?? [];
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
                    'n.nombre_negocio as nombre_negocio',
                    'n.color_acento',
                    'n.slug',
                    'n.politica_cancelacion'
                )
                ->where('r.fecha_reserva', $fecha)
                ->whereIn('r.estado_reserva', ['pendiente', 'confirmada'])
                ->where('r.estado', 1)
                // Un negocio suspendido por el super admin no manda correos a
                // sus clientes: quedó fuera de la plataforma aunque sus datos
                // se conserven.
                ->where('n.estado', 1)
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
