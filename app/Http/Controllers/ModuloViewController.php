<?php

namespace App\Http\Controllers;

use App\Models\ModuloPlataforma;
use App\Service\SvcModulo;
use App\Support\Plataforma;

/**
 * Pantalla de "módulo no disponible": lo que ve un admin cuando entra (o lo
 * mandan) a un módulo de pago que su negocio no tiene activo.
 *
 * No muestra datos de ningún otro negocio ni de otros módulos: solo el nombre
 * y la frase del módulo pedido, y el contacto de la plataforma para activarlo.
 * El tenant sale SIEMPRE de la sesión; la clave del módulo, de la URL.
 */
class ModuloViewController extends Controller
{
    public function mostrar(string $clave)
    {
        $tenantId = session('tenant_id');

        // El super admin no pertenece a un negocio: esta pantalla no le aplica.
        if ($tenantId === null) {
            return redirect(url('backoffice/dashboard'));
        }

        // Una clave que no existe en el catálogo es un 404, igual que cualquier
        // URL inventada: no se distingue de una ruta que no existe.
        $modulo = ModuloPlataforma::where('clave', $clave)->where('estado', 1)->first();

        if ($modulo === null) {
            abort(404);
        }

        $svcModulo = app(SvcModulo::class);
        $textos = Plataforma::modulo($clave);

        // Si el negocio SÍ tiene el módulo activo, esta pantalla sobra: lo
        // llevamos a la pantalla real del módulo (o al dashboard si no tiene una).
        if ($svcModulo->estaActivo($tenantId, $clave)) {
            return redirect(url($textos['destino'] ?? 'backoffice/dashboard'));
        }

        $contacto = Plataforma::contacto();
        $nombreNegocio = session('nombre_negocio_sesion') ?? 'mi negocio';

        // Mensaje precargado del WhatsApp. El plan va solo si existe; wa.me
        // recibe el texto sin codificar (whatsappLink lo codifica).
        $mensajeWhatsapp = $textos['plan'] !== null
            ? 'Hola, soy del spa '.$nombreNegocio.' y quiero el plan '.$textos['plan'].' ('.$modulo->nombre.').'
            : 'Hola, soy del spa '.$nombreNegocio.' y quiero activar '.$modulo->nombre.'.';

        return view('app.modulo.no-disponible', [
            'claveModulo' => $clave,
            'nombreModulo' => $modulo->nombre,
            'descripcionModulo' => $textos['descripcion'],
            'plan' => $textos['plan'],
            'fechaVencido' => $svcModulo->fechaDeVencimiento($tenantId, $clave),
            'whatsappLink' => Plataforma::whatsappLink($mensajeWhatsapp),
            'correoContacto' => $contacto['correo'],
            'tieneContacto' => $contacto['tiene'],
            'nombrePlataforma' => $contacto['nombre'],
        ]);
    }
}
