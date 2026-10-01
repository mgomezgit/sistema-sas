<?php

namespace App\Http\Middleware;

use App\Models\Empleado;
use App\Models\Negocio;
use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarSesion
{
    const CLAVE_SESION = 'xLXAiX0fFTjLKEiJam7X57';

    const MENSAJE_NEGOCIO_INACTIVO = 'Tu cuenta está inactiva. Comunícate con soporte.';

    const MENSAJE_USUARIO_INACTIVO = 'Tu usuario fue desactivado. Si crees que es un error, comunícate con el administrador de tu negocio.';

    const MENSAJE_CLAVE_CAMBIADA = 'La clave de tu cuenta cambió. Inicia sesión de nuevo con la clave nueva.';

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

        // Lo mismo si se desactivó a la persona y no al negocio: sin esto, un
        // usuario (o empleado) dado de baja seguía trabajando con la sesión
        // que ya tenía abierta hasta que caducara sola. Aplica también al
        // super admin. Son dos consultas por llave primaria.
        //
        // Se corta cuando la fila existe y está en estado 0. Una fila que no
        // existe no se trata como baja porque en este sistema usuarios y
        // empleados nunca se borran de verdad (la baja siempre es estado = 0),
        // así que ese caso no se da con datos reales.
        $motivo = $this->motivoParaCortar();

        if ($motivo !== null) {
            session()->flush();

            return $this->rechazar($request, $motivo, true);
        }

        return $next($request);
    }

    /**
     * Motivo por el que la sesión ya no vale aunque el negocio siga activo, o
     * null si vale. Una sola consulta por llave primaria al usuario (estado y
     * versión de sesión) y, si es empleado, otra al empleado.
     */
    private function motivoParaCortar(): ?string
    {
        $idUsuario = session('id_usuario');

        $usuario = $idUsuario !== null
            ? Usuario::select('estado', 'version_sesion')->where('id_usuario', $idUsuario)->first()
            : null;

        if ($usuario !== null && (int) $usuario->estado === 0) {
            return self::MENSAJE_USUARIO_INACTIVO;
        }

        // La clave cambió después de abrir esta sesión (recuperación de
        // clave): se cierran TODAS las sesiones de esa cuenta. Una sesión
        // anterior a esta columna no trae versión y cuenta como 0, que es el
        // valor inicial en la base: solo se corta si la clave cambió.
        if ($usuario !== null && (int) $usuario->version_sesion !== (int) session('version_sesion', 0)) {
            return self::MENSAJE_CLAVE_CAMBIADA;
        }

        $idEmpleado = session('id_empleado');

        if ($idEmpleado !== null && Empleado::where('id_empleado', $idEmpleado)->where('estado', 0)->exists()) {
            return self::MENSAJE_USUARIO_INACTIVO;
        }

        return null;
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
