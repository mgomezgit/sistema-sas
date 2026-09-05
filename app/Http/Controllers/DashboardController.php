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

        $tenantId = session('tenant_id');

        // El super admin no pertenece a ningún negocio, así que no hay métricas
        // que calcularle: la vista deja esas tarjetas en "Próximamente".
        if ($tenantId === null) {
            $templateView['reservasHoy'] = null;
            $templateView['clientesActivos'] = null;
            $templateView['ingresosMes'] = null;
            $templateView['ocupacionHoy'] = null;
            $templateView['proximasCitas'] = [];
            $templateView['variacionClientes'] = null;
            $templateView['distribucionHoy'] = [];

            return view('app.dashboard', $templateView);
        }

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
