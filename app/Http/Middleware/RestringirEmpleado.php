<?php

namespace App\Http\Middleware;

use App\Models\Rol;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestringirEmpleado
{
    /**
     * Handle an incoming request.
     *
     * Un usuario con rol "empleado" solo puede ver su propia agenda, así que
     * cualquier otra ruta de backoffice lo devuelve a "Mis Citas".
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Rol::esRolEmpleado(session('id_rol'))) {
            // Las rutas de tipo Request responden JSON, así que redirigir no sirve:
            // el frontend necesita el mismo formato de respuesta de siempre.
            if ($request->expectsJson() || $request->is('request/*')) {
                return response()->json([
                    'error' => 1,
                    'mensaje' => 'No tienes permiso para acceder a esta sección. Tu usuario solo puede consultar sus propias citas.',
                    'data' => [],
                ]);
            }

            return redirect(url('backoffice/mis-citas'));
        }

        return $next($request);
    }
}
