<?php

namespace App\Http\Controllers;

use App\Models\ModuloPlataforma;
use App\Service\SvcIA;
use App\Service\SvcModulo;
use App\Service\SvcNotificacionReserva;
use App\Support\Plataforma;

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

        return view('app.configuracion.index', array_merge([
            // El botón "Generar con IA" solo existe en la página si el módulo
            // está activo para ESTE negocio (tenant de la sesión).
            'iaActiva' => $iaActiva,
            // Perfil PRIVADO del negocio (solo con el módulo activo; del tenant de la sesión).
            'iaPerfil' => $perfil,
            'iaTienePerfil' => $iaActiva && $perfil !== ['especialidad' => '', 'publico' => ''],
            'iaMensajeLimite' => SvcIA::MENSAJE_LIMITE,
            'iaMensajeNoDisponible' => SvcIA::MENSAJE_NO_DISPONIBLE,
        ], $this->datosTarjetaCorreos()));
    }

    /**
     * Tarjeta "Correos a tus clientas": activo (qué correos salen) o candado
     * con el plan y el mismo contacto de la pantalla "no disponible". Todo del
     * tenant de la sesión; solo textos de la plataforma, ningún dato de otro negocio.
     */
    private function datosTarjetaCorreos(): array
    {
        $clave = SvcNotificacionReserva::CLAVE_MODULO;
        $textos = Plataforma::modulo($clave);
        $contacto = Plataforma::contacto();
        $nombreModulo = ModuloPlataforma::where('clave', $clave)->value('nombre') ?? 'Correos a tus clientas';

        return [
            'correosActivos' => app(SvcNotificacionReserva::class)->correosActivos(session('tenant_id')),
            'correosNombreModulo' => $nombreModulo,
            'correosPlan' => $textos['plan'],
            'correosWhatsappLink' => Plataforma::whatsappLink(
                Plataforma::mensajeWhatsappModulo(session('nombre_negocio_sesion') ?? 'mi negocio', $textos['plan'], $nombreModulo)
            ),
            'correosCorreoContacto' => $contacto['correo'],
            'correosTieneContacto' => $contacto['tiene'],
        ];
    }
}
