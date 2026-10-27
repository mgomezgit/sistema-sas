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

        $iaActiva = app(SvcModulo::class)->estaActivo(session('tenant_id'), SvcIA::CLAVE_MODULO);

        $perfil = $iaActiva ? app(SvcIA::class)->obtenerPerfil(session('tenant_id')) : ['especialidad' => '', 'publico' => ''];

        return view('app.configuracion.index', [
            // El botón "Generar con IA" solo existe en la página si el módulo
            // está activo para ESTE negocio (tenant de la sesión).
            'iaActiva' => $iaActiva,
            // Perfil PRIVADO del negocio (solo con el módulo activo; del tenant de la sesión).
            'iaPerfil' => $perfil,
            'iaTienePerfil' => $iaActiva && $perfil !== ['especialidad' => '', 'publico' => ''],
            'iaMensajeLimite' => SvcIA::MENSAJE_LIMITE,
            'iaMensajeNoDisponible' => SvcIA::MENSAJE_NO_DISPONIBLE,
        ]);
    }
}
