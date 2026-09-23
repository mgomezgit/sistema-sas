<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\Negocio;
use App\Models\RecursoReservable;
use App\Service\SvcBannerPromocional;
use Illuminate\Http\JsonResponse;

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
