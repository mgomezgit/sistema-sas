<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Service\SvcCliente;
use App\Service\SvcNotificacionReserva;
use App\Service\SvcReserva;
use Illuminate\Http\JsonResponse;

class ClienteController extends Controller
{
    protected SvcCliente $svcCliente;

    protected SvcReserva $svcReserva;

    protected SvcNotificacionReserva $svcNotificacionReserva;

    public function __construct()
    {
        parent::__construct();

        $this->svcCliente = new SvcCliente;
        $this->svcReserva = new SvcReserva;
        $this->svcNotificacionReserva = new SvcNotificacionReserva;
    }

    /**
     * Cuántas reservas futuras cancelables tiene un cliente: alimenta el aviso
     * que se muestra antes de darlo de baja. Un id de otro negocio (o que no
     * existe) responde exactamente igual: total 0, sin decir nada más.
     */
    public function reservasFuturas(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los clientes se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_cliente' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($this->svcReserva->contarFuturasDeCliente($this->getRequestData()['id_cliente'], $tenantId), 'total');

        return $this->sendResponse();
    }

    /**
     * Camino de baja cuando el formulario dijo qué hacer con las reservas
     * futuras (cancelar_reservas_futuras = 0 o 1). Sin ese parámetro, editar()
     * y eliminar() siguen exactamente igual que antes.
     *
     * Los correos de cancelación se encolan aquí, DESPUÉS de que
     * SvcCliente::aplicarBaja() confirmó su transacción.
     */
    private function aplicarBajaConEleccion($idCliente, $tenantId, array $info, bool $cancelar, string $codigoError): JsonResponse
    {
        $resultado = $this->svcCliente->aplicarBaja($idCliente, $tenantId, $info, $cancelar, session('id_usuario'));

        if ($resultado === false) {
            $this->agregarErrorNoDisponible('el cliente', $codigoError);

            return $this->sendResponse();
        }

        foreach ($resultado['canceladas'] as $idReserva) {
            $this->svcNotificacionReserva->notificarCambioEstado($idReserva, $tenantId, 'cancelada');
        }

        $this->respSinError();
        $this->setDataResponse(count($resultado['canceladas']), 'reservas_canceladas');
        $this->setDataResponse($resultado['omitidas'], 'reservas_omitidas');

        return $this->sendResponse();
    }

    public function crear(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los clientes se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'nombre' => 'required',
            'telefono' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $info = [
            'tenant_id' => $tenantId,
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'email' => $datos['email'] ?? null,
            'documento_identidad' => $datos['documento_identidad'] ?? null,
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
            'notas' => $datos['notas'] ?? null,
            'usuario_registra' => session('nombre_usuario'),
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ];

        $idCliente = $this->svcCliente->crear($info);

        if ($idCliente === false) {
            $this->agregarErrorSistema('CLI-CREAR');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($idCliente, 'id_cliente');

        return $this->sendResponse();
    }

    public function editar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los clientes se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_cliente' => 'required',
            'nombre' => 'required',
            'telefono' => 'required',
            'estado' => 'required|in:0,1',
            'cancelar_reservas_futuras' => 'sometimes|in:0,1',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $info = [
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'email' => $datos['email'] ?? null,
            'documento_identidad' => $datos['documento_identidad'] ?? null,
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
            'notas' => $datos['notas'] ?? null,
            'estado' => (int) $datos['estado'],
        ];

        if (array_key_exists('cancelar_reservas_futuras', $datos)) {
            return $this->aplicarBajaConEleccion($datos['id_cliente'], $tenantId, $info, (int) $datos['cancelar_reservas_futuras'] === 1, 'CLI-EDIT');
        }

        $resultado = $this->svcCliente->editar($datos['id_cliente'], $info, $tenantId);

        if (! $resultado) {
            $this->agregarErrorNoDisponible('el cliente', 'CLI-EDIT');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function eliminar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los clientes se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_cliente' => 'required',
            'cancelar_reservas_futuras' => 'sometimes|in:0,1',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        if (array_key_exists('cancelar_reservas_futuras', $datos)) {
            return $this->aplicarBajaConEleccion($datos['id_cliente'], $tenantId, ['estado' => 0], (int) $datos['cancelar_reservas_futuras'] === 1, 'CLI-ELIM');
        }

        $resultado = $this->svcCliente->eliminar($datos['id_cliente'], $tenantId);

        if (! $resultado) {
            $this->agregarErrorNoDisponible('el cliente', 'CLI-ELIM');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function listar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los clientes se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        // "incluir_inactivos=1" es lo que activa el filtro "Mostrar inactivos"
        // de la tabla. Sin él, un cliente dado de baja no aparece: es la única
        // forma de que desactivar se sienta reversible y no como un borrado.
        $incluirInactivos = (bool) ($datos['incluir_inactivos'] ?? false);

        $this->respSinError();
        $this->setDataResponse($this->svcCliente->listar($tenantId, $incluirInactivos), 'clientes');

        return $this->sendResponse();
    }
}
