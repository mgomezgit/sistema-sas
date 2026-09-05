<?php

namespace App\Http\Controllers;

class ReporteViewController extends Controller
{
    public function ventas()
    {
        // El super admin no pertenece a un negocio, así que no tiene reportes.
        if (session('tenant_id') === null) {
            return redirect(url('backoffice/dashboard'));
        }

        return view('app.reportes.ventas');
    }

    public function servicios()
    {
        if (session('tenant_id') === null) {
            return redirect(url('backoffice/dashboard'));
        }

        return view('app.reportes.servicios');
    }
}
