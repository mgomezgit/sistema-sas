<?php

namespace App\Http\Controllers;

class EmpleadoViewController extends Controller
{
    public function listar()
    {
        if (session('tenant_id') === null) {
            return redirect(url('backoffice/dashboard'));
        }

        return view('app.empleados.listado');
    }
}
