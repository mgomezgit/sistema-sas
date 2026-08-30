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
            return redirect(url('/login'));
        }

        return $next($request);
    }
}
