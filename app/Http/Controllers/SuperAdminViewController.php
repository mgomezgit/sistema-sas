<?php

namespace App\Http\Controllers;

class SuperAdminViewController extends Controller
{
    /**
     * No hay autorización que verificar aquí: las rutas de backoffice/superadmin/*
     * ya llevan solo.superadmin (sesión válida Y rol super_admin) en
     * routes/web.php. Este controller solo entrega las vistas; los datos los
     * trae el frontend de request/superadmin/* con la misma sesión.
     */
    public function dashboard()
    {
        return view('app.superadmin.dashboard');
    }

    public function negocios()
    {
        return view('app.superadmin.negocios');
    }
}
