<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Mail\ReservaConfirmada;
use App\Mail\ReservaEstadoActualizado;
use App\Models\Cliente;
use App\Models\Negocio;
use App\Service\SvcRecursoReservable;
use App\Service\SvcReserva;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReservaController extends Controller
{
    protected SvcReserva $svcReserva;

    protected SvcRecursoReservable $svcRecursoReservable;

    const ESTADOS_VALIDOS = ['pendiente', 'confirmada', 'completada', 'cancelada'];

    // Estados que un empleado puede aplicar sobre sus propias citas.
    const ESTADOS_EMPLEADO = ['confirmada', 'completada'];

    public function __construct()
    {
        parent::__construct();

        $this->svcReserva = new SvcReserva;
        $this->svcRecursoReservable = new SvcRecursoReservable;
    }

    /**
     * Reúne lo necesario para notificar al cliente de una reserva: sus datos ya
     * resueltos, el correo del cliente y el nombre del negocio.
     *
     * Retorna null si la reserva no existe o si el cliente no tiene correo
     * registrado, caso en el que simplemente no se envía nada.
     */
    private function datosParaNotificar($idReserva, $tenantId): ?array
    {
        $reserva = $this->svcReserva->listarById($idReserva, $tenantId);

        if (empty($reserva)) {
            return null;
        }

        $email = Cliente::where('id_cliente', $reserva[0]['id_cliente'])->value('email');

        if (empty($email)) {
            return null;
        }

        return [
            'reserva' => $reserva[0],
            'email' => $email,
            'nombre_negocio' => Negocio::where('id_negocio', $tenantId)->value('nombre_negocio') ?? '',
        ];
    }

    public function crear(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las reservas se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_cliente' => 'required',
            'id_recurso' => 'required',
            'fecha_reserva' => 'required',
            'hora_inicio' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $recurso = $this->svcRecursoReservable->listarById($datos['id_recurso'], $tenantId);

        if (empty($recurso)) {
            $this->agregarError('El servicio seleccionado ya no está disponible. Actualiza la página y vuelve a elegirlo de la lista.');

            return $this->sendResponse();
        }

        $horaFin = Carbon::parse($datos['hora_inicio'])->addMinutes($recurso[0]['duracion_minutos'])->format('H:i:s');

        $idEmpleado = $datos['id_empleado'] ?? null;

        if (! empty($idEmpleado)) {
            $disponible = $this->svcReserva->verificarDisponibilidad(
                $idEmpleado,
                $datos['fecha_reserva'],
                $datos['hora_inicio'],
                $horaFin,
                $tenantId
            );

            if (! $disponible) {
                $this->agregarError('El empleado ya tiene una reserva en ese horario. Elige otra hora, otra fecha, u otro empleado disponible.');

                return $this->sendResponse();
            }
        }

        $info = [
            'tenant_id' => $tenantId,
            'id_cliente' => $datos['id_cliente'],
            'id_recurso' => $datos['id_recurso'],
            'id_empleado' => $idEmpleado ?: null,
            'fecha_reserva' => $datos['fecha_reserva'],
            'hora_inicio' => $datos['hora_inicio'],
            'hora_fin' => $horaFin,
            'estado_reserva' => 'pendiente',
            'notas' => $datos['notas'] ?? null,
            'usuario_registra' => session('nombre_usuario'),
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ];

        $idReserva = $this->svcReserva->crear($info);

        if ($idReserva === false) {
            $this->agregarErrorSistema('RES-CREAR');

            return $this->sendResponse();
        }

        // El correo es una notificación adicional: si falla, la reserva ya quedó
        // creada y la respuesta al usuario no debe verse afectada.
        try {
            $datos = $this->datosParaNotificar($idReserva, $tenantId);

            if ($datos !== null) {
                Mail::to($datos['email'])->queue(new ReservaConfirmada($datos['reserva'], $datos['nombre_negocio']));
            }
        } catch (\Exception $e) {
            Log::channel('database')->info($e);
        }

        $this->respSinError();
        $this->setDataResponse($idReserva, 'id_reserva');

        return $this->sendResponse();
    }

    public function editar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las reservas se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_reserva' => 'required',
            'id_cliente' => 'required',
            'id_recurso' => 'required',
            'fecha_reserva' => 'required',
            'hora_inicio' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $recurso = $this->svcRecursoReservable->listarById($datos['id_recurso'], $tenantId);

        if (empty($recurso)) {
            $this->agregarError('El servicio seleccionado ya no está disponible. Actualiza la página y vuelve a elegirlo de la lista.');

            return $this->sendResponse();
        }

        $horaFin = Carbon::parse($datos['hora_inicio'])->addMinutes($recurso[0]['duracion_minutos'])->format('H:i:s');

        $idEmpleado = $datos['id_empleado'] ?? null;

        if (! empty($idEmpleado)) {
            $disponible = $this->svcReserva->verificarDisponibilidad(
                $idEmpleado,
                $datos['fecha_reserva'],
                $datos['hora_inicio'],
                $horaFin,
                $tenantId,
                $datos['id_reserva']
            );

            if (! $disponible) {
                $this->agregarError('El empleado ya tiene una reserva en ese horario. Elige otra hora, otra fecha, u otro empleado disponible.');

                return $this->sendResponse();
            }
        }

        $info = [
            'id_cliente' => $datos['id_cliente'],
            'id_recurso' => $datos['id_recurso'],
            'id_empleado' => $idEmpleado ?: null,
            'fecha_reserva' => $datos['fecha_reserva'],
            'hora_inicio' => $datos['hora_inicio'],
            'hora_fin' => $horaFin,
            'notas' => $datos['notas'] ?? null,
        ];

        $resultado = $this->svcReserva->editar($datos['id_reserva'], $info, $tenantId);

        if (! $resultado) {
            $this->agregarErrorNoDisponible('la reserva', 'RES-EDIT');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function cambiarEstado(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las reservas se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_reserva' => 'required',
            'estado_reserva' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        if (! in_array($datos['estado_reserva'], self::ESTADOS_VALIDOS, true)) {
            $this->agregarError('El estado seleccionado no es válido. Elige una opción de la lista: pendiente, confirmada, completada o cancelada.');

            return $this->sendResponse();
        }

        $resultado = $this->svcReserva->cambiarEstado($datos['id_reserva'], $datos['estado_reserva'], $tenantId);

        if (! $resultado) {
            $this->agregarErrorNoDisponible('la reserva', 'RES-ESTADO');

            return $this->sendResponse();
        }

        $this->notificarCambioEstado($datos['id_reserva'], $tenantId, $datos['estado_reserva']);

        $this->respSinError();

        return $this->sendResponse();
    }

    /**
     * Avisa al cliente que su reserva cambió de estado. "Pendiente" es el estado
     * inicial, así que no amerita notificación.
     */
    private function notificarCambioEstado($idReserva, $tenantId, $estadoReserva): void
    {
        if ($estadoReserva === 'pendiente') {
            return;
        }

        try {
            $datos = $this->datosParaNotificar($idReserva, $tenantId);

            if ($datos !== null) {
                Mail::to($datos['email'])->queue(
                    new ReservaEstadoActualizado($datos['reserva'], $datos['nombre_negocio'], $estadoReserva)
                );
            }
        } catch (\Exception $e) {
            Log::channel('database')->info($e);
        }
    }

    public function eliminar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las reservas se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_reserva' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $resultado = $this->svcReserva->eliminar($datos['id_reserva'], $tenantId);

        if (! $resultado) {
            $this->agregarErrorNoDisponible('la reserva', 'RES-ELIM');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function listar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las reservas se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $fechaInicio = $this->request->query('fecha_inicio');
        $fechaFin = $this->request->query('fecha_fin');
        // Filtros opcionales del historial: vacío equivale a "sin filtrar".
        $idCliente = $this->request->query('id_cliente') ?: null;
        $estadoReserva = $this->request->query('estado_reserva') ?: null;

        $this->respSinError();
        $this->setDataResponse(
            $this->svcReserva->listar($tenantId, $fechaInicio, $fechaFin, $idCliente, $estadoReserva),
            'reservas'
        );

        return $this->sendResponse();
    }

    /**
     * Mismo listado que listar(), pero con el formato de evento que espera
     * FullCalendar. Los colores por estado no se calculan aquí: el backend no
     * puede leer variables CSS, así que el frontend los resuelve con
     * getComputedStyle() (según el tema activo) y los manda como query string.
     */
    public function listarParaCalendario(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Las reservas se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $fechaInicio = $this->request->query('fecha_inicio');
        $fechaFin = $this->request->query('fecha_fin');

        $coloresPorEstado = [
            'pendiente' => [
                'fondo' => $this->request->query('fondo_pendiente'),
                'borde' => $this->request->query('borde_pendiente'),
            ],
            'confirmada' => [
                'fondo' => $this->request->query('fondo_confirmada'),
                'borde' => $this->request->query('borde_confirmada'),
            ],
            'completada' => [
                'fondo' => $this->request->query('fondo_completada'),
                'borde' => $this->request->query('borde_completada'),
            ],
            'cancelada' => [
                'fondo' => $this->request->query('fondo_cancelada'),
                'borde' => $this->request->query('borde_cancelada'),
            ],
        ];

        $reservas = $this->svcReserva->listar($tenantId, $fechaInicio, $fechaFin);

        $eventos = array_map(function ($reserva) use ($coloresPorEstado) {
            $color = $coloresPorEstado[$reserva['estado_reserva']] ?? $coloresPorEstado['pendiente'];

            return [
                'id' => (string) $reserva['id_reserva'],
                'title' => $reserva['nombre_cliente'].' - '.$reserva['nombre_recurso'],
                'start' => $reserva['fecha_reserva'].'T'.$reserva['hora_inicio'],
                'end' => $reserva['fecha_reserva'].'T'.$reserva['hora_fin'],
                'backgroundColor' => $color['fondo'],
                'borderColor' => $color['borde'],
                'extendedProps' => [
                    'nombre_empleado' => $reserva['nombre_empleado'],
                    'telefono_cliente' => $reserva['telefono_cliente'],
                    'id_cliente' => $reserva['id_cliente'],
                    'id_recurso' => $reserva['id_recurso'],
                    'id_empleado' => $reserva['id_empleado'],
                    'estado_reserva' => $reserva['estado_reserva'],
                    'notas' => $reserva['notas'],
                ],
            ];
        }, $reservas);

        $this->respSinError();
        $this->setDataResponse($eventos, 'eventos');

        return $this->sendResponse();
    }

    /**
     * Agenda propia del empleado logueado. Siempre acotada a su id_empleado de
     * sesión, nunca a un id recibido por parámetro.
     */
    public function misCitas(): JsonResponse
    {
        $idEmpleado = session('id_empleado');

        if ($idEmpleado === null) {
            $this->agregarError('Este usuario no está vinculado a ningún empleado. Comunícate con el administrador de tu negocio para que revise tu cuenta.');

            return $this->sendResponse();
        }

        $fecha = $this->request->get('fecha', date('Y-m-d'));

        $this->respSinError();
        $this->setDataResponse(
            $this->svcReserva->listarPorEmpleado($idEmpleado, session('tenant_id'), $fecha),
            'citas'
        );

        return $this->sendResponse();
    }

    /**
     * Cambio de estado acotado: el empleado solo puede avanzar SUS propias citas
     * a confirmada o completada. Cancelar o devolver a pendiente es del admin.
     */
    public function cambiarEstadoMiCita(): JsonResponse
    {
        $idEmpleado = session('id_empleado');

        if ($idEmpleado === null) {
            $this->agregarError('Este usuario no está vinculado a ningún empleado. Comunícate con el administrador de tu negocio para que revise tu cuenta.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_reserva' => 'required',
            'estado_reserva' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $reserva = $this->svcReserva->listarById($datos['id_reserva'], session('tenant_id'));

        if (empty($reserva) || $reserva[0]['id_empleado'] != $idEmpleado) {
            $this->agregarError('No tienes permiso para modificar esta cita. Solo puedes cambiar el estado de las citas asignadas a ti.');

            return $this->sendResponse();
        }

        if (! in_array($datos['estado_reserva'], self::ESTADOS_EMPLEADO, true)) {
            $this->agregarError('Estado no permitido para este usuario. Solo puedes marcar una cita como confirmada o completada; para cancelarla, comunícate con el administrador.');

            return $this->sendResponse();
        }

        $resultado = $this->svcReserva->cambiarEstado($datos['id_reserva'], $datos['estado_reserva'], session('tenant_id'));

        if (! $resultado) {
            $this->agregarErrorNoDisponible('la cita', 'RES-MICITA-ESTADO');

            return $this->sendResponse();
        }

        // El cliente debe enterarse igual, sin importar si el cambio lo hizo el
        // administrador o el propio empleado desde su agenda.
        $this->notificarCambioEstado($datos['id_reserva'], session('tenant_id'), $datos['estado_reserva']);

        $this->respSinError();

        return $this->sendResponse();
    }
}
