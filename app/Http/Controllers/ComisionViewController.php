<?php

namespace App\Http\Controllers;

use App\Service\SvcEmpleado;
use App\Service\SvcRecursoReservable;

class ComisionViewController extends Controller
{
    protected SvcEmpleado $svcEmpleado;

    protected SvcRecursoReservable $svcRecursoReservable;

    public function __construct()
    {
        parent::__construct();

        $this->svcEmpleado = new SvcEmpleado;
        $this->svcRecursoReservable = new SvcRecursoReservable;
    }

    public function informe()
    {
        $tenantId = session('tenant_id');

        // El super admin no pertenece a un negocio, así que no tiene empleados
        // a quienes liquidar comisiones.
        if ($tenantId === null) {
            return redirect(url('backoffice/dashboard'));
        }

        $templateView = [];
        // Para el filtro del informe y para el formulario de tarifas específicas.
        $templateView['empleados'] = $this->svcEmpleado->listarActivos($tenantId);
        $templateView['servicios'] = $this->svcRecursoReservable->listar($tenantId);

        return view('app.comisiones.informe', $templateView);
    }
}
