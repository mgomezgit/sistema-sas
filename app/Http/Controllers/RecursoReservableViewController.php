<?php

namespace App\Http\Controllers;

use App\Service\SvcIA;
use App\Service\SvcModulo;

class RecursoReservableViewController extends Controller
{
    public function listar()
    {
        if (session('tenant_id') === null) {
            return redirect(url('backoffice/dashboard'));
        }

        // El botón "Generar con IA" solo existe en la página si el módulo está
        // activo para ESTE negocio (tenant de la sesión). La descripción pública
        // en sí NO depende del módulo.
        $iaActiva = app(SvcModulo::class)->estaActivo(session('tenant_id'), SvcIA::CLAVE_MODULO);

        $perfil = $iaActiva ? app(SvcIA::class)->obtenerPerfil(session('tenant_id')) : ['especialidad' => '', 'publico' => ''];

        return view('app.recursos.listado', [
            'iaActiva' => $iaActiva,
            'iaTienePerfil' => $iaActiva && $perfil !== ['especialidad' => '', 'publico' => ''],
            'iaMensajeLimite' => SvcIA::MENSAJE_LIMITE,
            'iaMensajeNoDisponible' => SvcIA::MENSAJE_NO_DISPONIBLE,
        ]);
    }
}
