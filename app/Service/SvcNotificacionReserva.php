<?php

namespace App\Service;

use App\Mail\ReservaEstadoActualizado;
use App\Models\Cliente;
use App\Models\Negocio;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Correos al CLIENTE de una reserva. Antes vivían como métodos privados de
 * ReservaController; se sacaron aquí para que también los use la baja de
 * clientes (ClienteController) sin duplicar la lógica.
 *
 * Nada de esto se dispara solo desde SvcReserva: quien cambia algo decide si
 * notifica, y lo hace DESPUÉS de que el cambio quedó guardado. La
 * confirmación de una reserva nueva sigue encolándose únicamente en
 * ReservaController::crear() (ver CLAUDE.md, página pública).
 */
class SvcNotificacionReserva
{
    protected SvcReserva $svcReserva;

    public function __construct()
    {
        $this->svcReserva = new SvcReserva;
    }

    /**
     * Reúne lo necesario para notificar al cliente de una reserva: sus datos ya
     * resueltos, el correo del cliente y los datos del negocio.
     *
     * Retorna null si la reserva no existe (o es de otro negocio) o si el
     * cliente no tiene correo registrado, caso en el que no se envía nada.
     */
    public function datosParaNotificar($idReserva, $tenantId): ?array
    {
        try {
            $reserva = $this->svcReserva->listarById($idReserva, $tenantId);

            if (empty($reserva)) {
                return null;
            }

            $email = Cliente::where('id_cliente', $reserva[0]['id_cliente'])
                ->where('tenant_id', $tenantId)
                ->value('email');

            if (empty($email)) {
                return null;
            }

            $negocio = Negocio::where('id_negocio', $tenantId)
                ->first(['nombre_negocio', 'color_acento', 'slug', 'politica_cancelacion']);

            return [
                'reserva' => $reserva[0],
                'email' => $email,
                // Todo lo que necesitan los correos al cliente para pintarse:
                // nombre, acento (el nombre guardado; ColorAcento lo traduce a
                // hex en la vista), slug (el botón se omite si falta) y la
                // política de cancelación (el pie la omite si está vacía).
                'negocio' => [
                    'nombre_negocio' => $negocio->nombre_negocio ?? '',
                    'color_acento' => $negocio->color_acento ?? null,
                    'slug' => $negocio->slug ?? null,
                    'politica_cancelacion' => $negocio->politica_cancelacion ?? null,
                ],
            ];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return null;
        }
    }

    /**
     * Avisa al cliente que su reserva cambió de estado. "Pendiente" es el estado
     * inicial, así que no amerita notificación. Un fallo al encolar no se
     * propaga: el cambio ya quedó guardado y la respuesta no debe verse
     * afectada.
     */
    public function notificarCambioEstado($idReserva, $tenantId, $estadoReserva): void
    {
        if ($estadoReserva === 'pendiente') {
            return;
        }

        try {
            $datos = $this->datosParaNotificar($idReserva, $tenantId);

            if ($datos !== null) {
                Mail::to($datos['email'])->queue(
                    new ReservaEstadoActualizado($datos['reserva'], $datos['negocio'], $estadoReserva)
                );
            }
        } catch (\Exception $e) {
            Log::channel('database')->info($e);
        }
    }
}
