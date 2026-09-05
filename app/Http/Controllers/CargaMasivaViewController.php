<?php

namespace App\Http\Controllers;

class CargaMasivaViewController extends Controller
{
    public function index()
    {
        // El super admin no pertenece a un negocio, así que no tiene a dónde cargar.
        if (session('tenant_id') === null) {
            return redirect(url('backoffice/dashboard'));
        }

        return view('app.carga-masiva.index');
    }
}
