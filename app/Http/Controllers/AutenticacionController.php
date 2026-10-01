<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\IniciaSesion;
use App\Http\Middleware\VerificarSesion;
use App\Models\Negocio;
use App\Service\SvcUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AutenticacionController extends Controller
{
    use IniciaSesion;

    /**
     * Límites contra fuerza bruta en el login. Solo cuentan los intentos
     * FALLIDOS, y se revisan ANTES de comparar la clave: una vez agotado el
     * cupo, ni siquiera la clave correcta entra hasta que pase el minuto.
     *
     * - Por correo + IP: quien prueba claves contra UNA cuenta.
     * - Solo por IP (más alto): quien prueba muchos correos distintos desde el
     *   mismo lugar para esquivar el límite anterior.
     */
    const INTENTOS_POR_CORREO_E_IP = 5;

    const INTENTOS_POR_IP = 20;

    const VENTANA_SEGUNDOS = 60;

    protected SvcUsuario $svcUsuario;

    public function __construct()
    {
        parent::__construct();

        $this->svcUsuario = new SvcUsuario;
    }

    public function mostrarLogin()
    {
        $templateView = [];

        return view('login', $templateView);
    }

    public function validarLogin(): JsonResponse
    {
        $this->setRequestValidationRules([
            'email' => 'required|email',
            'clave' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $claveCorreo = $this->claveLimiteCorreo($datos['email']);
        $claveIp = $this->claveLimiteIp();

        // El bloqueo no mira si el correo existe: responde igual para una
        // cuenta real y para una inventada, así no sirve para averiguarlo.
        if (RateLimiter::tooManyAttempts($claveCorreo, self::INTENTOS_POR_CORREO_E_IP)
            || RateLimiter::tooManyAttempts($claveIp, self::INTENTOS_POR_IP)) {
            $segundos = max(RateLimiter::availableIn($claveCorreo), RateLimiter::availableIn($claveIp));

            $this->agregarError('Demasiados intentos de inicio de sesión. Espera '.$segundos.' segundos e inténtalo de nuevo.');

            return $this->sendResponse();
        }

        $usuario = $this->svcUsuario->getUsuarioByEmail($datos['email']);

        if (! empty($usuario) && Hash::check($datos['clave'], $usuario['clave'])) {
            // Negocio suspendido por el super admin: no se entra. Se revisa
            // DESPUÉS de validar la clave, para que este aviso no le confirme a
            // quien no la conoce que el correo existe. El super admin no tiene
            // negocio (tenant_id null) y nunca se ve afectado.
            if ($usuario['tenant_id'] !== null
                && ! Negocio::where('id_negocio', $usuario['tenant_id'])->where('estado', 1)->exists()) {
                $this->agregarError(VerificarSesion::MENSAJE_NEGOCIO_INACTIVO);

                return $this->sendResponse();
            }

            // Entró: se limpia su contador por correo (no el de la IP, que
            // si no un atacante lo reiniciaría entrando con su propia cuenta).
            RateLimiter::clear($claveCorreo);

            $this->iniciarSesionDeUsuario($usuario);

            $this->respSinError();
        } else {
            RateLimiter::hit($claveCorreo, self::VENTANA_SEGUNDOS);
            RateLimiter::hit($claveIp, self::VENTANA_SEGUNDOS);

            $this->agregarError('El correo o la contraseña no son correctos. Verifica los datos e inténtalo de nuevo.');
        }

        return $this->sendResponse();
    }

    /**
     * El correo se normaliza (minúsculas, sin espacios, sin tildes) para que
     * "Admin@Demo.test " y "admin@demo.test" compartan el mismo contador.
     */
    private function claveLimiteCorreo(string $email): string
    {
        return 'login-correo:'.Str::transliterate(Str::lower(trim($email))).'|'.$this->request->ip();
    }

    private function claveLimiteIp(): string
    {
        return 'login-ip:'.$this->request->ip();
    }

    /**
     * invalidate() y no solo flush(): flush() vacía los datos pero deja vivo
     * el mismo ID de sesión; invalidate() además lo destruye en el almacén y
     * emite uno nuevo. regenerateToken() cambia el token CSRF, para que uno
     * que se haya filtrado durante la sesión tampoco sirva después.
     */
    public function logout()
    {
        session()->invalidate();
        session()->regenerateToken();

        return redirect(url('/'));
    }
}
