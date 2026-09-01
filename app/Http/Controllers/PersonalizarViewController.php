<?php

namespace App\Http\Controllers;

class PersonalizarViewController extends Controller
{
    public function mostrar()
    {
        // El super admin administra la plataforma, no un negocio: no tiene tema propio.
        if (session('tenant_id') === null) {
            return redirect(url('backoffice/dashboard'));
        }

        return view('app.personalizar.index');
    }
}
