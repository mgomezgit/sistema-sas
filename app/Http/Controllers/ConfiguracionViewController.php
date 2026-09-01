<?php

namespace App\Http\Controllers;

class ConfiguracionViewController extends Controller
{
    public function mostrar()
    {
        // El super admin administra la plataforma, no un negocio concreto.
        if (session('tenant_id') === null) {
            return redirect(url('backoffice/dashboard'));
        }

        return view('app.configuracion.index');
    }
}
