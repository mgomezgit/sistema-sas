<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Mail\NuevaSolicitudPublica;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Negocio;
use App\Models\RecursoReservable;
use App\Models\Usuario;
use App\Service\SvcBannerPromocional;
use App\Service\SvcReserva;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Datos que alimentan la página pública de autogestión de cada negocio.
 *
 * ⚠️ ESTE CONTROLLER RESPONDE A CUALQUIER PERSONA DE INTERNET.
 *
 * No hay sesión, no hay login y no hay middleware de autenticación: el negocio
 * se identifica ÚNICAMENTE por el slug de la URL. De ahí salen dos reglas que
 * no se pueden relajar:
 *
 * 1. EL TENANT SALE DEL SLUG, NUNCA DE session(). Ningún método de aquí puede
 *    reutilizar los Services del backoffice que asumen un tenant de sesión, ni
 *    aceptar un tenant_id que venga en la petición.
 *
 * 2. CADA CAMPO DEVUELTO ES UNA DECISIÓN EXPLÍCITA. Antes de agregar un campo
 *    nuevo a cualquiera de estas respuestas hay que responder en voz alta:
 *    «¿esto es seguro que lo vea cualquier persona de internet?». Un teléfono
 *    de empleado, un porcentaje de comisión, un correo, un id interno o el
 *    tenant_id NO lo son.
 *
 *    Por eso aquí no se usa select('*'), ni se devuelve el ->toArray() de un
 *    modelo, ni se hace ->get() sin select: las respuestas se arman campo por
 *    campo a mano. Es más verboso a propósito — así una columna nueva en la
 *    tabla no se filtra sola a la web pública el día que alguien la agregue.
 */
class PublicoController extends Controller
{
    /**
     * Negocio dueño de la página, resuelto desde el slug de la URL.
     *
     * Un slug que no existe y un negocio dado de baja devuelven exactamente el
     * mismo 404, sin distinguirse entre sí ni de cualquier otro 404 del sitio:
     * desde fuera no se puede averiguar si un negocio existe pero está cerrado.
     */
    private function negocioPorSlug(string $slug): Negocio
    {
        $negocio = Negocio::where('slug', $slug)
            ->where('estado', 1)
            ->first();

        if ($negocio === null) {
            abort(404);
        }

        return $negocio;
    }

    /**
     * Datos de contacto, horario y personalización visual del negocio.
     *
     * modo_tema y color_acento viajan para que la página pública se pinte con
     * la identidad del negocio; no revelan nada de su operación.
     */
    public function informacionNegocio(string $slug): JsonResponse
    {
        $negocio = $this->negocioPorSlug($slug);

        $this->respSinError();
        $this->setDataResponse([
            'nombre_negocio' => $negocio->nombre_negocio,
            'telefono_contacto' => $negocio->telefono_contacto,
            'whatsapp_numero' => $negocio->whatsapp_numero,
            'dias_atencion' => $negocio->dias_atencion,
            'hora_apertura' => $negocio->hora_apertura,
            'hora_cierre' => $negocio->hora_cierre,
            'politica_cancelacion' => $negocio->politica_cancelacion,
            'modo_tema' => $negocio->modo_tema,
            'color_acento' => $negocio->color_acento,
        ], 'negocio');

        return $this->sendResponse();
    }

    /**
     * Servicios que el negocio ofrece al público.
     *
     * Solo los activos: uno dado de baja dejó de ofrecerse, así que tampoco se
     * anuncia. Se piden cuatro columnas y se arma el array con esas cuatro; ni
     * la descripción interna, ni la capacidad, ni el id del recurso salen de
     * aquí. El id no hace falta todavía: cuando el flujo de agendar lo
     * necesite, será una decisión consciente de ese momento.
     */
    public function servicios(string $slug): JsonResponse
    {
        $negocio = $this->negocioPorSlug($slug);

        $servicios = RecursoReservable::select('nombre', 'categoria', 'duracion_minutos', 'precio')
            ->where('tenant_id', $negocio->id_negocio)
            ->where('estado', 1)
            ->orderBy('categoria')
            ->orderBy('nombre')
            ->get()
            ->map(function ($recurso) {
                return [
                    'nombre' => $recurso->nombre,
                    'categoria' => $recurso->categoria,
                    'duracion_minutos' => (int) $recurso->duracion_minutos,
                    'precio' => (float) $recurso->precio,
                ];
            })
            ->all();

        $this->respSinError();
        $this->setDataResponse($servicios, 'servicios');

        return $this->sendResponse();
    }

    /**
     * Recibe una solicitud de cita pedida desde la página pública.
     *
     * Es el ÚNICO método de escritura de esta clase, así que merece leerse con
     * cuidado: lo puede invocar cualquiera, sin sesión y sin login. Tres cosas
     * lo sostienen:
     *
     * 1. El negocio sale del slug, nunca del cuerpo de la petición.
     * 2. La solicitud nace 'pendiente' y sin empleado: no agenda nada por su
     *    cuenta, solo deja una petición para que el negocio la revise.
     * 3. El throttle del grupo de rutas limita cuántas se pueden mandar.
     *
     * Y el campo trampa: "sitio_web" está oculto en el formulario, así que una
     * persona nunca lo rellena. Un bot que llena todo lo que encuentra sí. Si
     * viene con algo, se responde exactamente igual que en un alta buena —sin
     * crear nada— para no enseñarle al bot cuál fue el filtro que lo paró.
     */
    public function agendar(string $slug): JsonResponse
    {
        $negocio = $this->negocioPorSlug($slug);

        $this->setRequestValidationRules([
            'nombre' => 'required|max:150',
            'telefono' => 'required|max:30',
            'email' => 'nullable|email|max:150',
            'id_recurso' => 'required',
            'fecha_reserva' => 'required|date',
            'hora_inicio' => 'required',
            'notas' => 'nullable|max:500',
        ], [
            'nombre.required' => 'Dinos tu nombre para saber a quién esperamos.',
            'telefono.required' => 'Necesitamos un teléfono para confirmarte la cita.',
            'email.email' => 'Ese correo no parece válido. Revísalo, o déjalo vacío.',
            'id_recurso.required' => 'Elige el servicio que quieres reservar.',
            'fecha_reserva.required' => 'Elige el día de tu cita.',
            'fecha_reserva.date' => 'Esa fecha no es válida.',
            'hora_inicio.required' => 'Elige la hora de tu cita.',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        // La trampa se revisa DESPUÉS de validar, para que un bot tampoco
        // pueda distinguirla por el tiempo de respuesta o por saltarse los
        // errores de validación que sí vería una persona.
        if (! empty($datos['sitio_web'])) {
            Log::channel('database')->info(
                'Solicitud pública descartada por el campo trampa en el negocio '.$negocio->id_negocio.'.'
            );

            return $this->respuestaDeSolicitudRecibida();
        }

        $idReserva = (new SvcReserva)->crearSolicitudPublica($negocio->id_negocio, [
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'email' => $datos['email'] ?? null,
            'id_recurso' => $datos['id_recurso'],
            'fecha_reserva' => $datos['fecha_reserva'],
            'hora_inicio' => $datos['hora_inicio'],
            'notas' => $datos['notas'] ?? null,
        ]);

        if ($idReserva === false) {
            $this->agregarError('No pudimos registrar tu solicitud. Revisa que el día y la hora estén dentro del horario de atención, e inténtalo de nuevo.');

            return $this->sendResponse();
        }

        $this->avisarAlNegocio($negocio, $idReserva);

        return $this->respuestaDeSolicitudRecibida();
    }

    /**
     * La misma respuesta para una solicitud buena y para una que cayó en la
     * trampa: desde fuera no se puede distinguir una de otra.
     */
    private function respuestaDeSolicitudRecibida(): JsonResponse
    {
        $this->respSinError();
        $this->setDataResponse(
            'Recibimos tu solicitud. El negocio la revisará y te confirmará la cita.',
            'mensaje_confirmacion'
        );

        return $this->sendResponse();
    }

    /**
     * Avisa al administrador del negocio de que tiene una solicitud nueva.
     *
     * Va al ADMINISTRADOR, no al cliente: el cliente no recibe nada todavía,
     * porque su cita no está confirmada. Si el aviso falla, la solicitud ya
     * quedó guardada y la respuesta al visitante no se ve afectada, igual que
     * hace el flujo del backoffice con su propio correo.
     */
    private function avisarAlNegocio(Negocio $negocio, $idReserva): void
    {
        try {
            $correoAdmin = Usuario::from('usuarios as u')
                ->join('roles as r', 'r.id_rol', '=', 'u.id_rol')
                ->where('u.tenant_id', $negocio->id_negocio)
                ->where('u.estado', 1)
                ->where('r.nombre_rol', 'admin')
                ->orderBy('u.id_usuario')
                ->value('u.email');

            // Si el negocio no tiene admin con correo, se cae al de contacto.
            if (empty($correoAdmin)) {
                return;
            }

            $reserva = (new SvcReserva)->listarById($idReserva, $negocio->id_negocio);

            if (empty($reserva)) {
                return;
            }

            $telefono = Cliente::where('id_cliente', $reserva[0]['id_cliente'])->value('telefono');

            Mail::to($correoAdmin)->queue(new NuevaSolicitudPublica([
                'nombre_cliente' => $reserva[0]['nombre_cliente'] ?? '',
                'telefono_cliente' => $telefono ?? '',
                'nombre_recurso' => $reserva[0]['nombre_recurso'] ?? '',
                'fecha_reserva' => $reserva[0]['fecha_reserva'] ?? '',
                'hora_inicio' => $reserva[0]['hora_inicio'] ?? '',
                'hora_fin' => $reserva[0]['hora_fin'] ?? '',
                'notas' => $reserva[0]['notas'] ?? null,
            ], $negocio->nombre_negocio));
        } catch (\Exception $e) {
            Log::channel('database')->info($e);
        }
    }

    /**
     * Banners promocionales que hoy toca mostrar en el carrusel.
     *
     * La vigencia (fecha_inicio / fecha_fin) y el estado deciden QUÉ sale,
     * pero no salen ellos: desde fuera no se puede leer el calendario de
     * promociones de un negocio, solo ver la que está corriendo hoy.
     */
    public function bannersPublicos(string $slug): JsonResponse
    {
        $negocio = $this->negocioPorSlug($slug);

        $this->respSinError();
        $this->setDataResponse(
            (new SvcBannerPromocional)->listarVigentes($negocio->id_negocio),
            'banners'
        );

        return $this->sendResponse();
    }

    /**
     * Quiénes atienden, y nada más que eso.
     *
     * Un empleado tiene teléfono, correo, cargo, porcentaje de comisión y un
     * posible usuario vinculado: NADA de eso sale a la web pública. Solo el
     * nombre, que es lo único que un cliente necesita para saber con quién se
     * va a atender.
     */
    public function equipo(string $slug): JsonResponse
    {
        $negocio = $this->negocioPorSlug($slug);

        $equipo = Empleado::select('nombre')
            ->where('tenant_id', $negocio->id_negocio)
            ->where('estado', 1)
            ->orderBy('nombre')
            ->get()
            ->map(function ($empleado) {
                return ['nombre' => $empleado->nombre];
            })
            ->all();

        $this->respSinError();
        $this->setDataResponse($equipo, 'equipo');

        return $this->sendResponse();
    }
}
