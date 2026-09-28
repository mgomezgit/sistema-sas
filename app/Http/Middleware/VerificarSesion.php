<?php

namespace App\Http\Middleware;

use App\Models\Negocio;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarSesion
{
    const CLAVE_SESION = 'xLXAiX0fFTjLKEiJam7X57';

    const MENSAJE_NEGOCIO_INACTIVO = 'Tu cuenta está inactiva. Comunícate con soporte.';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session('app_sesion') !== self::CLAVE_SESION) {
            return $this->rechazar($request, 'Tu sesión terminó. Vuelve a iniciar sesión para continuar.', false);
        }

        // Un negocio suspendido por el super admin corta también las sesiones
        // que ya estaban abiertas, en la siguiente petición: no basta con
        // negar el login, porque quien ya entró seguiría trabajando hasta que
        // su sesión caducara sola. Es una consulta por llave primaria.
        //
        // El super admin no tiene negocio (tenant_id null), así que nunca
        // entra a este chequeo.
        $tenantId = session('tenant_id');

        if ($tenantId !== null && ! Negocio::where('id_negocio', $tenantId)->where('estado', 1)->exists()) {
            session()->flush();

            return $this->rechazar($request, self::MENSAJE_NEGOCIO_INACTIVO, true);
        }

        return $next($request);
    }

    /**
     * Las rutas de tipo Request responden JSON, así que redirigir no sirve:
     * el frontend necesita el mismo formato de respuesta de siempre. Las
     * pantallas vuelven al login con el motivo, para que se muestre allí.
     */
    private function rechazar(Request $request, string $mensaje, bool $avisarEnLogin): Response
    {
        if ($request->expectsJson() || $request->is('request/*')) {
            return response()->json([
                'error' => 1,
                'mensaje' => $mensaje,
                'data' => [],
            ]);
        }

        // Sin sesión se vuelve al login como siempre, sin aviso: quien nunca
        // entró no tiene nada que "terminó". El aviso es para la suspensión.
        if (! $avisarEnLogin) {
            return redirect(url('/login'));
        }

        return redirect(url('/login'))->with('aviso_login', $mensaje);
    }
}
