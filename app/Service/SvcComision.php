<?php

namespace App\Service;

use App\Models\ComisionTarifa;
use App\Models\PagoComision;
use App\Models\Reserva;
use Carbon\Carbon;
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

    /* Resultados posibles de anularPago(). No es un bool porque el Controller
       necesita saber POR QUÉ no se hizo, para dar el mensaje justo. */
    const ANULACION_HECHA = 'anulado';

    const ANULACION_NO_EXISTE = 'no_existe';

    const ANULACION_YA_ANULADO = 'ya_anulado';

    const ANULACION_ERROR = 'error';

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

    /**
     * Por defecto solo trae las tarifas activas: una dada de baja ya no manda
     * sobre el porcentaje general del empleado, así que tampoco debe seguir
     * ocupando el listado. $incluirInactivas es la puerta para verlas igual,
     * desde el filtro "Mostrar inactivas" de la pestaña, que es lo único que
     * permite abrir una en modo edición y volver a activarla.
     */
    public function listarTarifasEspecificas($tenantId, $idEmpleado = null, $incluirInactivas = false)
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
                ->where('ct.tenant_id', $tenantId);

            if (! $incluirInactivas) {
                $query->where('ct.estado', 1);
            }

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
     *
     * Esta es también la vía de reactivación: $info['estado'] llega desde el
     * interruptor del modal, así que poner en 1 una tarifa dada de baja la
     * devuelve al listado con el porcentaje que se esté guardando. Si no viene
     * el dato se asume 1, que es el caso de un alta y también el de volver a
     * guardar la misma combinación empleado+servicio desde "Nueva tarifa":
     * ahí no hay interruptor, y guardar sobre una baja siempre la reactiva.
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
                    'estado' => array_key_exists('estado', $info) ? (int) $info['estado'] : 1,
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

    /**
     * Historial de pagos del negocio, vigentes y anulados.
     *
     * De la anulación se devuelve el NOMBRE de quien anuló ("Sistema" si no
     * quedó registrado), nunca su id; y cuántas citas liberó, nunca la lista.
     * cantidad_citas son las que el pago tiene ligadas hoy (las que volverían
     * al informe si se anula). Ningún total se calcula aquí: un pago anulado
     * no se suma en ningún lado.
     */
    public function listarHistorialPagos($tenantId, $idEmpleado = null)
    {
        try {
            $query = PagoComision::from('pagos_comisiones as p')
                ->join('empleados as e', 'e.id_empleado', '=', 'p.id_empleado')
                // Acotado al mismo negocio: aunque anulado_por apuntara a una
                // cuenta de otro negocio, su nombre no saldría de aquí.
                ->leftJoin('usuarios as ua', function ($join) {
                    $join->on('ua.id_usuario', '=', 'p.anulado_por')
                        ->on('ua.tenant_id', '=', 'p.tenant_id');
                })
                ->select(
                    'p.id_pago_comision',
                    'p.id_empleado',
                    'e.nombre as nombre_empleado',
                    'p.fecha_inicio',
                    'p.fecha_fin',
                    'p.monto_total',
                    'p.fecha_pago',
                    'p.estado',
                    'p.anulado_en',
                    'p.motivo_anulacion',
                    'p.reservas_liberadas',
                    'ua.nombre as nombre_anulado_por',
                    DB::raw('(SELECT COUNT(*) FROM reservas r WHERE r.id_pago_comision = p.id_pago_comision AND r.tenant_id = p.tenant_id) as cantidad_citas')
                )
                ->where('p.tenant_id', $tenantId)
                ->where('p.estado', 1);

            if (! empty($idEmpleado)) {
                $query->where('p.id_empleado', $idEmpleado);
            }

            return $query->orderByDesc('p.fecha_pago')
                ->get()
                ->map(function ($fila) {
                    $anulado = $fila->anulado_en !== null;
                    $liberadas = $anulado ? count(json_decode((string) $fila->reservas_liberadas, true) ?: []) : 0;

                    return [
                        'id_pago_comision' => (int) $fila->id_pago_comision,
                        'id_empleado' => (int) $fila->id_empleado,
                        'nombre_empleado' => $fila->nombre_empleado,
                        'fecha_inicio' => $fila->fecha_inicio,
                        'fecha_fin' => $fila->fecha_fin,
                        'monto_total' => $fila->monto_total,
                        'fecha_pago' => $fila->fecha_pago,
                        'estado' => (int) $fila->estado,
                        'cantidad_citas' => (int) $fila->cantidad_citas,
                        'anulado' => $anulado,
                        'anulado_en' => $fila->anulado_en,
                        'anulado_por' => $anulado ? ($fila->nombre_anulado_por ?? 'Sistema') : null,
                        'motivo_anulacion' => $fila->motivo_anulacion,
                        'citas_liberadas' => $liberadas,
                    ];
                })
                ->all();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Anula un pago marcado por error. El pago NUNCA se borra ni cambia su
     * monto_total: queda con anulado_en, anulado_por y motivo_anulacion. Sus
     * citas quedan libres (id_pago_comision = null), vuelven al informe como
     * pendientes y se pueden volver a liquidar; sus ids quedan guardados en
     * reservas_liberadas. No se toca estado_reserva ni historial_estados_reserva.
     *
     * Todo en UNA transacción, con bloqueo de fila del pago y de sus reservas:
     * dos anulaciones simultáneas no pueden pasar las dos el chequeo de "ya
     * anulado", y una cita no puede cambiar de pago mientras se libera.
     *
     * Un pago de otro negocio responde igual que uno inexistente.
     *
     * @return string Una de las constantes ANULACION_*.
     */
    public function anularPago($idPago, $tenantId, $idUsuario, $motivo): string
    {
        try {
            $resultado = DB::transaction(function () use ($idPago, $tenantId, $idUsuario, $motivo) {
                $pago = PagoComision::select('id_pago_comision', 'anulado_en')
                    ->where('id_pago_comision', $idPago)
                    ->where('tenant_id', $tenantId)
                    ->where('estado', 1)
                    ->lockForUpdate()
                    ->first();

                if ($pago === null) {
                    return self::ANULACION_NO_EXISTE;
                }

                if ($pago->anulado_en !== null) {
                    return self::ANULACION_YA_ANULADO;
                }

                $idsReservas = Reserva::where('tenant_id', $tenantId)
                    ->where('id_pago_comision', $idPago)
                    ->orderBy('id_reserva')
                    ->lockForUpdate()
                    ->pluck('id_reserva')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                Reserva::where('tenant_id', $tenantId)
                    ->where('id_pago_comision', $idPago)
                    ->update(['id_pago_comision' => null]);

                PagoComision::where('id_pago_comision', $idPago)
                    ->where('tenant_id', $tenantId)
                    ->update([
                        'anulado_en' => Carbon::now()->format('Y-m-d H:i:s'),
                        'anulado_por' => $idUsuario,
                        'motivo_anulacion' => $motivo,
                        'reservas_liberadas' => json_encode($idsReservas),
                    ]);

                return $idsReservas;
            });

            if (is_string($resultado)) {
                return $resultado;
            }

            Log::channel('database')->info(sprintf(
                'ANULACION DE PAGO DE COMISION: el usuario %s anulo el pago %d del negocio %d el %s; reservas liberadas: %d (%s); motivo: %s',
                $idUsuario ?? 'sistema',
                $idPago,
                $tenantId,
                Carbon::now()->format('Y-m-d H:i:s'),
                count($resultado),
                implode(', ', $resultado),
                json_encode($motivo, JSON_UNESCAPED_UNICODE)
            ));

            return self::ANULACION_HECHA;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return self::ANULACION_ERROR;
        }
    }
}
