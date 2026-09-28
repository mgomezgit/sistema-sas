<?php

namespace App\Http\Controllers;

use App\Service\SvcCliente;
use App\Service\SvcReserva;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $templateView = [];
        $templateView['fechaHoy'] = Carbon::now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY');

        // El super admin no opera un negocio: su dashboard es el de la
        // plataforma (backoffice/superadmin/dashboard), no una versión vacía
        // de este. Se identifica por rol, nunca por tenant_id null a secas
        // (ver App\Models\Rol::esRolSuperAdmin).
        if (\App\Models\Rol::esRolSuperAdmin(session('id_rol'))) {
            return redirect(url('backoffice/superadmin/dashboard'));
        }

        $tenantId = session('tenant_id');

        $svcReserva = new SvcReserva;
        $svcCliente = new SvcCliente;

        // Son cifras agregadas del propio negocio, no datos sensibles de nadie,
        // así que también se muestran al rol empleado.
        $clientesActivos = $svcCliente->contarActivos($tenantId);

        $templateView['reservasHoy'] = $svcReserva->contarHoy($tenantId);
        $templateView['clientesActivos'] = $clientesActivos;
        $templateView['ingresosMes'] = $svcReserva->calcularIngresosMes($tenantId);
        $templateView['ocupacionHoy'] = $svcReserva->calcularOcupacionHoy($tenantId);

        $templateView['proximasCitas'] = $svcReserva->listarProximasHoy($tenantId, 4);
        // Cuántos clientes se sumaron (o se dieron de baja) en lo que va del mes.
        $templateView['variacionClientes'] = $clientesActivos - $svcCliente->contarActivosMesAnterior($tenantId);
        $templateView['distribucionHoy'] = $svcReserva->distribucionHoyPorHora($tenantId);

        return view('app.dashboard', $templateView);
    }
}
