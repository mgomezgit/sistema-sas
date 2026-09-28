<?php

namespace App\Http\Middleware;

use App\Models\Rol;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puerta del panel del super admin: la única zona del sistema que trabaja
 * legítimamente ENTRE negocios. Un fallo aquí expone datos de todos.
 *
 * Exige las DOS cosas a la vez:
 *   1. Sesión válida (app_sesion con la clave del login real).
 *   2. Rol super_admin, consultado en la tabla roles.
 *
 * NUNCA identifica al super admin por tener tenant_id null: una petición
 * anónima también tiene tenant_id null, y un usuario mal cargado con rol admin
 * y sin negocio también. Manda el rol.
 */
class SoloSuperAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sesionValida = session('app_sesion') === VerificarSesion::CLAVE_SESION;

        if ($sesionValida && Rol::esRolSuperAdmin(session('id_rol'))) {
            return $next($request);
        }

        // Las rutas de tipo Request responden JSON, así que redirigir no sirve.
        if ($request->expectsJson() || $request->is('request/*')) {
            return response()->json([
                'error' => 1,
                'mensaje' => 'No tienes permiso para acceder a esta sección.',
                'data' => [],
            ]);
        }

        return redirect(url($sesionValida ? 'backoffice/dashboard' : '/login'));
    }
}
