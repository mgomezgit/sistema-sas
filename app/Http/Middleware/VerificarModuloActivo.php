<?php

namespace App\Http\Middleware;

use App\Service\SvcModulo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta el acceso a un módulo de pago que el negocio no tiene activo.
 *
 * Se aplica con la clave del módulo como parámetro: 'verificar.modulo:comisiones'.
 *
 * Bloquea tanto la vista de backoffice/* como los endpoints de request/*, con la
 * misma disciplina que RestringirEmpleado: esconder el enlace del sidebar es solo
 * experiencia de usuario, y quien escriba la URL a mano, o llame al endpoint
 * directamente, tiene que rebotar aquí.
 */
class VerificarModuloActivo
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $claveModulo): Response
    {
        $tenantId = session('tenant_id');

        // El super admin no pertenece a ningún negocio, así que no hay módulo
        // que comprobarle: pasa siempre, sin consultar nada.
        if ($tenantId === null) {
            return $next($request);
        }

        if (app(SvcModulo::class)->estaActivo($tenantId, $claveModulo)) {
            return $next($request);
        }

        // Las rutas de tipo Request responden JSON, así que redirigir no sirve:
        // el frontend necesita el mismo formato de respuesta de siempre.
        if ($request->expectsJson() || $request->is('request/*')) {
            return response()->json([
                'error' => 1,
                'mensaje' => 'Este módulo no está activo para tu negocio. Comunícate con el administrador de la plataforma para habilitarlo.',
                'data' => [],
            ]);
        }

        return redirect(url('backoffice/dashboard'));
    }
}
