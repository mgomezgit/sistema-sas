<?php

namespace App\Service;

use App\Models\Empleado;
use App\Models\Negocio;
use App\Models\RecursoReservable;
use Illuminate\Support\Facades\Log;

/**
 * Datos de la página pública de autogestión (publico/{slug}).
 *
 * Un solo lugar para que el endpoint JSON (PublicoController) y la vista que
 * se arma en el servidor (PaginaPublicaViewController) lean exactamente la
 * misma consulta, el mismo filtro de tenant y la misma lista blanca de
 * campos — antes vivían duplicadas entre los dos.
 *
 * Sin sesión: el negocio SIEMPRE llega como parámetro explícito (el id, o el
 * slug para resolverlo primero), nunca de session('tenant_id'). Cada método
 * arma su array campo por campo — nunca select('*') ni ->toArray() de un
 * modelo completo — porque cualquier columna nueva en estas tablas es una
 * decisión consciente antes de exponerse a cualquier persona de internet.
 */
class SvcPaginaPublica
{
    /**
     * El negocio dueño de la página, o null si el slug no existe o está dado
     * de baja. Los dos casos se resuelven igual a propósito: quien llama no
     * puede distinguir el motivo, solo que no hay página que mostrar.
     */
    public function resolverPorSlug(string $slug): ?Negocio
    {
        try {
            return Negocio::where('slug', $slug)->where('estado', 1)->first();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return null;
        }
    }

    /**
     * Contacto, horario y personalización visual. modo_tema y color_acento no
     * los usa el diseño fijo de esta página, pero se conservan porque ya
     * eran parte de la lista blanca de este endpoint y PaginaPublicaTest los
     * verifica explícitamente.
     */
    public function informacion(Negocio $negocio): array
    {
        return [
            'nombre_negocio' => $negocio->nombre_negocio,
            'telefono_contacto' => $negocio->telefono_contacto,
            'whatsapp_numero' => $negocio->whatsapp_numero,
            'dias_atencion' => $negocio->dias_atencion,
            'hora_apertura' => $negocio->hora_apertura,
            'hora_cierre' => $negocio->hora_cierre,
            'politica_cancelacion' => $negocio->politica_cancelacion,
            'modo_tema' => $negocio->modo_tema,
            'color_acento' => $negocio->color_acento,
        ];
    }

    /**
     * Servicios activos del negocio.
     *
     * id_recurso sale porque el formulario de agendar lo necesita para decir
     * cuál se pide: crearSolicitudPublica() ya vuelve a comprobar que ese id
     * sea de este mismo tenant y esté activo antes de crear nada, así que
     * exponerlo no abre ninguna puerta que el backend no cierre.
     */
    public function servicios(int $tenantId): array
    {
        try {
            return RecursoReservable::select('id_recurso', 'nombre', 'categoria', 'duracion_minutos', 'precio')
                ->where('tenant_id', $tenantId)
                ->where('estado', 1)
                ->orderBy('categoria')
                ->orderBy('nombre')
                ->get()
                ->map(function ($recurso) {
                    return [
                        'id_recurso' => (int) $recurso->id_recurso,
                        'nombre' => $recurso->nombre,
                        'categoria' => $recurso->categoria,
                        'duracion_minutos' => (int) $recurso->duracion_minutos,
                        'precio' => (float) $recurso->precio,
                    ];
                })
                ->all();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Quiénes atienden, y nada más que eso.
     *
     * Un empleado tiene teléfono, correo, cargo, porcentaje de comisión y un
     * posible usuario vinculado: nada de eso sale a la web pública. Solo el
     * nombre, que es lo único que un cliente necesita para saber con quién
     * se va a atender.
     */
    public function equipo(int $tenantId): array
    {
        try {
            return Empleado::select('nombre')
                ->where('tenant_id', $tenantId)
                ->where('estado', 1)
                ->orderBy('nombre')
                ->get()
                ->map(function ($empleado) {
                    return ['nombre' => $empleado->nombre];
                })
                ->all();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Banners vigentes hoy. Delegado a SvcBannerPromocional, que ya es la
     * única fuente de esa regla (vigencia + estado); no se duplica aquí.
     */
    public function banners(int $tenantId): array
    {
        return (new SvcBannerPromocional)->listarVigentes($tenantId);
    }
}
