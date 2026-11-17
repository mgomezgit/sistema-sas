<?php

namespace App\Http\Controllers;

use App\Service\SvcNotificacionReserva;

class ClienteViewController extends Controller
{
    public function listar()
    {
        if (session('tenant_id') === null) {
            return redirect(url('backoffice/dashboard'));
        }

        return view('app.clientes.listado', [
            // El aviso de baja con reservas futuras solo promete un correo al
            // cliente si el negocio tiene el módulo "correos" (tenant de la sesión).
            'correosActivos' => app(SvcNotificacionReserva::class)->correosActivos(session('tenant_id')),
        ]);
    }
}
