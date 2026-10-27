<?php

namespace App\Http\Controllers;

use App\Service\SvcIA;
use App\Service\SvcModulo;

class ConfiguracionViewController extends Controller
{
    public function mostrar()
    {
        // El super admin administra la plataforma, no un negocio concreto.
        if (session('tenant_id') === null) {
            return redirect(url('backoffice/dashboard'));
        }

        return view('app.configuracion.index', [
            // El botón "Generar con IA" solo existe en la página si el módulo
            // está activo para ESTE negocio (tenant de la sesión).
            'iaActiva' => app(SvcModulo::class)->estaActivo(session('tenant_id'), SvcIA::CLAVE_MODULO),
            'iaMensajeLimite' => SvcIA::MENSAJE_LIMITE,
            'iaMensajeNoDisponible' => SvcIA::MENSAJE_NO_DISPONIBLE,
        ]);
    }
}
