<?php

namespace App\Http\Controllers;

class ClienteViewController extends Controller
{
    public function listar()
    {
        if (session('tenant_id') === null) {
            return redirect(url('backoffice/dashboard'));
        }

        return view('app.clientes.listado');
    }
}
