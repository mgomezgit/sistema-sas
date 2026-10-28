<?php

namespace App\Http\Controllers;

use App\Models\Rol;
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

        // El selector de rol solo ofrece roles de negocio (admin, empleado). El
        // rol super_admin NUNCA aparece como opción: esa cuenta es única y no se
        // crea ni se asigna desde aquí. El filtro es por nombre, no por id.
        $templateView['roles'] = array_values(array_filter(
            $this->svcRol->listar(),
            fn ($rol) => in_array($rol['nombre_rol'] ?? null, Rol::ROLES_DE_NEGOCIO, true)
        ));
        $templateView['esSuperAdmin'] = $tenantId === null;

        return view('app.usuarios.listado', $templateView);
    }
}
