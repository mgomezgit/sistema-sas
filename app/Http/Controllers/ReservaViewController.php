<?php

namespace App\Http\Controllers;

class ReservaViewController extends Controller
{
    public function listar()
    {
        if (session('tenant_id') === null) {
            return redirect(url('backoffice/dashboard'));
        }

        return view('app.reservas.listado');
    }
}
