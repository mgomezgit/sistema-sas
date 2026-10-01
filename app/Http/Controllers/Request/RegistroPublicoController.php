<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Concerns\IniciaSesion;
use App\Http\Controllers\Controller;
use App\Mail\ConfirmarRegistroPublico;
use App\Mail\IntentoRegistroCorreoExistente;
use App\Models\Usuario;
use App\Service\SvcRegistroPendiente;
use App\Service\SvcUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Registro público de negocios, en dos pasos:
 *
 * 1. crear(): valida el formulario y deja un registro PENDIENTE (o, si el
 *    correo ya tiene cuenta activa, le avisa a su dueño). No crea negocio ni
 *    usuario. Responde siempre lo mismo, exista o no el correo.
 * 2. confirmar(): con el token del link del correo, crea el negocio y su
 *    admin, abre la sesión y lleva al dashboard.
 */
class RegistroPublicoController extends Controller
{
    use IniciaSesion;

    protected SvcRegistroPendiente $svcRegistroPendiente;

    protected SvcUsuario $svcUsuario;

    // Rubros habilitados hoy para el registro autoservicio.
    const RUBROS_DISPONIBLES = ['spa'];

    /**
     * La ÚNICA respuesta a un formulario válido: registro nuevo, correo que ya
     * tenía cuenta, y campo trampa lleno. Desde fuera no se puede distinguir
     * cuál de los tres pasó, así que el formulario no sirve para averiguar qué
     * correos están registrados.
     */
    const MENSAJE_REVISA_TU_CORREO = 'Revisa tu correo para continuar.';

    public function __construct()
    {
        parent::__construct();

        $this->svcRegistroPendiente = new SvcRegistroPendiente;
        $this->svcUsuario = new SvcUsuario;
    }

    /**
     * Paso 1. Ruta pública, con throttle (ver el limitador "registro-publico")
     * y campo trampa.
     */
    public function crear(): JsonResponse
    {
        $this->setRequestValidationRules([
            'nombre_negocio' => 'required',
            'telefono_contacto' => 'required',
            'rubro' => 'required',
            'nombre' => 'required',
            'email' => 'required|email',
            'clave' => 'required|min:'.SvcUsuario::LARGO_MINIMO_CLAVE,
            'confirmar_clave' => 'required',
        ], SvcUsuario::mensajesDeClave());

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        // Campo trampa, igual que en publico/*/agendar: "sitio_web" está oculto
        // en el formulario, así que una persona nunca lo rellena. Se revisa
        // DESPUÉS de validar (un bot no puede distinguirlo por los errores que
        // sí vería una persona) y se responde exactamente como un alta buena,
        // sin crear nada, para no enseñarle cuál fue el filtro que lo paró.
        if (! empty($datos['sitio_web'])) {
            Log::channel('database')->info('Registro público descartado por el campo trampa.');

            return $this->respuestaRevisaTuCorreo();
        }

        if (! in_array($datos['rubro'], self::RUBROS_DISPONIBLES, true)) {
            $this->agregarError('Este rubro estará disponible próximamente. Por ahora solo puedes registrar un negocio de tipo Spa.');

            return $this->sendResponse();
        }

        if ($datos['clave'] !== $datos['confirmar_clave']) {
            $this->agregarError('Las claves no coinciden');

            return $this->sendResponse();
        }

        $correo = Str::lower(trim($datos['email']));

        // Se hashea SIEMPRE, antes de saber si el correo existe: así los dos
        // caminos cuestan lo mismo y el tiempo de respuesta tampoco delata
        // cuál se tomó. La clave en claro no pasa de esta línea.
        $claveHash = Hash::make($datos['clave']);

        // Solo compiten las cuentas activas: un correo que quedó en una cuenta
        // desactivada vuelve a estar libre para registrarse de nuevo.
        if (Usuario::where('email', $correo)->where('estado', 1)->exists()) {
            // El link de recuperación llega con SU correo ya escrito: el aviso
            // solo va a la bandeja del dueño real, así que no expone nada.
            $this->enviarCorreo($correo, new IntentoRegistroCorreoExistente(
                url('/login'),
                url('recuperar-clave').'?'.http_build_query(['correo' => $correo])
            ));

            return $this->respuestaRevisaTuCorreo();
        }

        $token = $this->svcRegistroPendiente->solicitar([
            'nombre_negocio' => $datos['nombre_negocio'],
            'rubro' => $datos['rubro'],
            'telefono_contacto' => $datos['telefono_contacto'],
            'nombre_admin' => $datos['nombre'],
            'correo' => $correo,
        ], $claveHash);

        if ($token === false) {
            $this->agregarErrorSistema('REG-PENDIENTE');

            return $this->sendResponse();
        }

        $this->enviarCorreo($correo, new ConfirmarRegistroPublico(
            $datos['nombre'],
            $datos['nombre_negocio'],
            url('request/registro-publico/confirmar/'.$token),
            SvcRegistroPendiente::HORAS_VIGENCIA
        ));

        return $this->respuestaRevisaTuCorreo();
    }

    /**
     * Paso 2. Ruta pública (GET, sin sesión): se llega desde el link del
     * correo. Crea el negocio y su admin, abre la sesión y va al dashboard.
     */
    public function confirmar(string $token)
    {
        $resultado = $this->svcRegistroPendiente->confirmar($token);

        if ($resultado['resultado'] === SvcRegistroPendiente::CONFIRMADO) {
            $usuario = Usuario::select('id_usuario', 'usuario', 'nombre', 'email', 'tenant_id', 'id_rol', 'version_sesion')
                ->where('id_usuario', $resultado['id_usuario'])
                ->first();

            $this->iniciarSesionDeUsuario($usuario->toArray());

            return redirect(url('backoffice/dashboard'));
        }

        $mensajes = [
            SvcRegistroPendiente::YA_CONFIRMADO => [
                'titulo' => 'Tu cuenta ya está activa',
                'texto' => 'Este enlace ya se usó para crear la cuenta. Entra con tu correo y tu clave.',
                'accion' => 'login',
            ],
            SvcRegistroPendiente::CORREO_OCUPADO => [
                'titulo' => 'Ya existe una cuenta con este correo',
                'texto' => 'Mientras tanto se creó una cuenta activa con este mismo correo. Entra con ella.',
                'accion' => 'login',
            ],
            SvcRegistroPendiente::ERROR => [
                'titulo' => 'No pudimos crear tu cuenta',
                'texto' => 'Ocurrió un problema técnico. Vuelve a abrir el enlace en unos minutos; si sigue fallando, regístrate de nuevo.',
                'accion' => 'registro',
            ],
        ];

        // Token que no existe, vencido, o reemplazado por un registro más nuevo.
        $mensaje = $mensajes[$resultado['resultado']] ?? [
            'titulo' => 'Este enlace ya no es válido',
            'texto' => 'Puede que haya vencido o que hayas pedido otro después. Vuelve a registrarte y te enviaremos un enlace nuevo.',
            'accion' => 'registro',
        ];

        return response()->view('registro-confirmacion', $mensaje);
    }

    /** Un fallo al encolar el correo no puede cambiar la respuesta al formulario. */
    private function enviarCorreo(string $correo, $mailable): void
    {
        try {
            Mail::to($correo)->queue($mailable);
        } catch (\Exception $e) {
            Log::channel('database')->info($e);
        }
    }

    private function respuestaRevisaTuCorreo(): JsonResponse
    {
        $this->respSinError();
        $this->setDataResponse(self::MENSAJE_REVISA_TU_CORREO, 'mensaje');

        return $this->sendResponse();
    }
}
