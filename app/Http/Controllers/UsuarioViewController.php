<?php

namespace App\Http\Controllers;

use App\Service\SvcNegocio;
use App\Service\SvcRol;

class UsuarioViewController extends Controller
{
    protected SvcNegocio $svcNegocio;

    protected SvcRol $svcRol;

    public function __construct()
    {
        parent::__construct();

        $this->svcNegocio = new SvcNegocio;
        $this->svcRol = new SvcRol;
    }

    public function listar()
    {
        $tenantId = session('tenant_id');

        $templateView = [];

        if ($tenantId !== null) {
            $templateView['negocios'] = array_filter(
                $this->svcNegocio->listar(),
                fn ($negocio) => $negocio['id_negocio'] == $tenantId
            );
        } else {
            $templateView['negocios'] = $this->svcNegocio->listar();
        }

        $templateView['roles'] = $this->svcRol->listar();
        $templateView['esSuperAdmin'] = $tenantId === null;

        return view('app.usuarios.listado', $templateView);
    }
}
