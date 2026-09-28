<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarSesion
{
    const CLAVE_SESION = 'xLXAiX0fFTjLKEiJam7X57';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session('app_sesion') !== self::CLAVE_SESION) {
            // Las rutas de tipo Request responden JSON, así que redirigir no
            // sirve: el frontend necesita el mismo formato de respuesta de
            // siempre, y una petición anónima sin sesión debe recibir un
            // rechazo explícito en vez de un 302 que un cliente sin
            // navegador no sigue por su cuenta.
            if ($request->expectsJson() || $request->is('request/*')) {
                return response()->json([
                    'error' => 1,
                    'mensaje' => 'Tu sesión terminó. Vuelve a iniciar sesión para continuar.',
                    'data' => [],
                ]);
            }

            return redirect(url('/login'));
        }

        return $next($request);
    }
}
