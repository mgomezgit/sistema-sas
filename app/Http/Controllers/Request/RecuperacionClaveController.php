<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Mail\CodigoRecuperacionClave;
use App\Models\Usuario;
use App\Service\SvcRecuperacionClave;
use App\Service\SvcUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Recuperación de clave con un código de 6 dígitos. Rutas públicas (quien la
 * usa no puede entrar), para cualquier cuenta: admin, empleado con acceso o
 * super admin.
 */
class RecuperacionClaveController extends Controller
{
    /**
     * La única respuesta a un pedido de código válido, exista o no el correo:
     * el formulario no sirve para averiguar qué correos están registrados.
     */
    const MENSAJE_SOLICITUD = 'Si el correo está registrado, te enviamos un código.';

    /**
     * Intentos de código equivocados por correo + IP. Pasado el límite se
     * rechaza incluso el código correcto hasta que se cumpla la ventana: con
     * 6 dígitos (un millón de combinaciones) y 5 intentos por hora, adivinar
     * no es práctico.
     */
    const INTENTOS_CONFIRMAR = 5;

    const VENTANA_CONFIRMAR_SEGUNDOS = 3600;

    protected SvcRecuperacionClave $svcRecuperacionClave;

    public function __construct()
    {
        parent::__construct();

        $this->svcRecuperacionClave = new SvcRecuperacionClave;
    }

    private function normalizarCorreo(string $correo): string
    {
        return Str::lower(trim($correo));
    }

    /** Paso 1: pedir el código. Con throttle por IP (limitador "recuperacion-solicitar"). */
    public function solicitar(): JsonResponse
    {
        $this->setRequestValidationRules(['correo' => 'required|email']);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $correo = $this->normalizarCorreo($this->getRequestData()['correo']);
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $idUsuario = Usuario::where('email', $correo)->where('estado', 1)->value('id_usuario');

        if ($idUsuario !== null) {
            if ($this->svcRecuperacionClave->solicitar($idUsuario, $codigo)) {
                try {
                    Mail::to($correo)->queue(new CodigoRecuperacionClave(
                        $codigo,
                        SvcRecuperacionClave::MINUTOS_VIGENCIA,
                        url('recuperar-clave').'?'.http_build_query(['correo' => $correo, 'paso' => 'codigo'])
                    ));
                } catch (\Exception $e) {
                    Log::channel('database')->info($e);
                }
            }
        } else {
            // El mismo bcrypt que cuesta guardar un código real: así un correo
            // inexistente tarda lo mismo y el tiempo tampoco delata nada.
            Hash::make($codigo);
        }

        $this->respSinError();
        $this->setDataResponse(self::MENSAJE_SOLICITUD, 'mensaje');

        return $this->sendResponse();
    }

    /** Paso 2: código + clave nueva. */
    public function confirmar(): JsonResponse
    {
        $this->setRequestValidationRules([
            'correo' => 'required|email',
            'codigo' => 'required|digits:6',
            'clave' => 'required|min:'.SvcUsuario::LARGO_MINIMO_CLAVE,
            'confirmar_clave' => 'required|same:clave',
        ], array_merge(SvcUsuario::mensajesDeClave(), [
            'codigo.digits' => 'El código tiene 6 números. Revísalo en el correo.',
            'confirmar_clave.same' => 'Las claves no coinciden.',
        ]));

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();
        $correo = $this->normalizarCorreo($datos['correo']);
        $claveLimite = 'recuperacion-confirmar:'.$correo.'|'.$this->request->ip();

        // Se revisa ANTES de mirar el código: agotado el cupo, ni el correcto
        // entra. Responde igual exista o no el correo.
        if (RateLimiter::tooManyAttempts($claveLimite, self::INTENTOS_CONFIRMAR)) {
            $minutos = (int) ceil(RateLimiter::availableIn($claveLimite) / 60);
            $this->agregarError('Demasiados intentos con códigos equivocados. Espera '.$minutos.' minutos, o pide un código nuevo más tarde.');

            return $this->sendResponse();
        }

        $resultado = $this->svcRecuperacionClave->confirmar($correo, $datos['codigo'], $datos['clave']);

        if ($resultado === SvcRecuperacionClave::CLAVE_CAMBIADA) {
            RateLimiter::clear($claveLimite);

            $this->respSinError();
            $this->setDataResponse('Tu clave se cambió. Ya puedes iniciar sesión con la nueva.', 'mensaje');

            return $this->sendResponse();
        }

        if ($resultado === SvcRecuperacionClave::CODIGO_INVALIDO) {
            RateLimiter::hit($claveLimite, self::VENTANA_CONFIRMAR_SEGUNDOS);
            $this->agregarError('El código no es válido o ya venció. Revisa el correo o pide un código nuevo.');

            return $this->sendResponse();
        }

        $this->agregarErrorSistema('REC-CONFIRMAR');

        return $this->sendResponse();
    }
}
