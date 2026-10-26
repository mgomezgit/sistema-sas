<?php

namespace App\Service;

use App\Models\ChatbotUso;
use App\Models\ModuloPlataforma;
use App\Models\Negocio;
use App\Models\NegocioModulo;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de plataforma para el panel del super admin.
 *
 * EXCEPCIÓN DOCUMENTADA A LA REGLA MULTI-TENANT: ningún método de esta clase
 * recibe ni filtra por tenant_id, porque su trabajo es justamente mirar TODOS
 * los negocios. Es seguro solo porque:
 *
 *   1. Únicamente se llama desde SuperAdminController, cuyas rutas llevan el
 *      middleware solo.superadmin (sesión válida Y rol super_admin). Hay una
 *      prueba guardián que rompe la suite si una ruta del panel queda sin él.
 *   2. Devuelve SOLO metadatos de la cuenta de cada negocio: nombre, slug,
 *      rubro, estado, fecha de alta, su contacto administrador y qué módulos
 *      tiene. NUNCA clientes, reservas, empleados, productos, ingresos ni
 *      ningún otro dato operativo. Todos los select son listas blancas
 *      explícitas, verificadas por prueba.
 *
 * No usar esta clase desde ningún otro controlador.
 */
class SvcSuperAdmin
{
    /** Ventana de "negocios nuevos" del resumen. */
    const DIAS_NEGOCIO_NUEVO = 30;

    /**
     * Totales de la plataforma. Solo conteos, ningún registro individual.
     */
    public function resumenPlataforma(): array
    {
        try {
            $totales = Negocio::select(
                DB::raw('COUNT(*) as total_negocios'),
                DB::raw('SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) as negocios_activos'),
                DB::raw('SUM(CASE WHEN estado = 1 THEN 0 ELSE 1 END) as negocios_inactivos')
            )->first();

            $nuevos = Negocio::where('fecha_registro', '>=', now()->subDays(self::DIAS_NEGOCIO_NUEVO))->count();

            // Por módulo del catálogo, cuántos negocios lo tienen encendido.
            // LEFT JOIN para que un módulo que nadie tiene salga con cero en
            // vez de desaparecer del resumen.
            $modulos = ModuloPlataforma::from('modulos_plataforma as mp')
                ->leftJoin('negocio_modulos as nm', function ($join) {
                    $join->on('nm.id_modulo', '=', 'mp.id_modulo')
                        ->where('nm.activo', '=', 1);
                })
                ->select(
                    'mp.clave',
                    'mp.nombre',
                    DB::raw('COUNT(nm.id_negocio_modulo) as negocios_con_modulo')
                )
                ->where('mp.estado', 1)
                ->groupBy('mp.clave', 'mp.nombre')
                ->orderBy('mp.nombre')
                ->get()
                ->map(fn ($fila) => [
                    'clave' => $fila->clave,
                    'nombre' => $fila->nombre,
                    'negocios_con_modulo' => (int) $fila->negocios_con_modulo,
                ])
                ->toArray();

            return [
                'total_negocios' => (int) ($totales->total_negocios ?? 0),
                'negocios_activos' => (int) ($totales->negocios_activos ?? 0),
                'negocios_inactivos' => (int) ($totales->negocios_inactivos ?? 0),
                'negocios_nuevos_30_dias' => $nuevos,
                'modulos' => $modulos,
            ];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Negocios de la plataforma con su contacto administrador y sus módulos.
     *
     * El contacto es el admin ACTIVO más antiguo (menor id_usuario): si el que
     * abrió la cuenta fue dado de baja, el contacto útil es quien sigue activo.
     */
    public function listarNegocios($busqueda = null): array
    {
        try {
            $idRolAdmin = Rol::where('nombre_rol', 'admin')->value('id_rol');

            // Un admin por negocio: el de menor id entre los activos.
            $adminMasAntiguo = Usuario::select('tenant_id', DB::raw('MIN(id_usuario) as id_usuario'))
                ->where('id_rol', $idRolAdmin)
                ->where('estado', 1)
                ->whereNotNull('tenant_id')
                ->groupBy('tenant_id');

            $query = Negocio::from('negocios as n')
                ->leftJoinSub($adminMasAntiguo, 'am', 'am.tenant_id', '=', 'n.id_negocio')
                ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'am.id_usuario')
                ->select(
                    'n.id_negocio',
                    'n.nombre_negocio',
                    'n.slug',
                    'n.rubro',
                    'n.estado',
                    'n.fecha_registro',
                    'u.nombre as nombre_admin',
                    'u.email as email_admin'
                );

            $busqueda = trim((string) $busqueda);

            if ($busqueda !== '') {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('n.nombre_negocio', 'like', '%'.$busqueda.'%')
                        ->orWhere('n.slug', 'like', '%'.$busqueda.'%');
                });
            }

            $negocios = $query->orderBy('n.nombre_negocio')->get()->toArray() ?? [];

            if (empty($negocios)) {
                return [];
            }

            // Claves de módulos activos, en una sola consulta para todos los
            // negocios de la página (no una por negocio).
            $modulosPorNegocio = NegocioModulo::from('negocio_modulos as nm')
                ->join('modulos_plataforma as mp', 'mp.id_modulo', '=', 'nm.id_modulo')
                ->select('nm.tenant_id', 'mp.clave')
                ->whereIn('nm.tenant_id', array_column($negocios, 'id_negocio'))
                ->where('nm.activo', 1)
                ->where('mp.estado', 1)
                ->orderBy('mp.clave')
                ->get()
                ->groupBy('tenant_id')
                ->map(fn ($filas) => $filas->pluck('clave')->values()->all());

            foreach ($negocios as &$negocio) {
                $negocio['modulos_activos'] = $modulosPorNegocio->get($negocio['id_negocio'], []);
            }
            unset($negocio);

            return $negocios;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /* ================= CHATBOT: USO Y LÍMITE MENSUAL ================= */

    /**
     * Uso del chatbot de cada negocio en un mes: SOLO números (mensajes y
     * tokens) más el límite que se aplica. Nunca contenido de conversaciones
     * (no se guarda), ni dinero (depende del proveedor y del modelo), ni
     * ningún dato operativo del negocio. Lista blanca de campos explícita.
     *
     * $mes ya viene validado (SvcChatbot::normalizarMes). $idNegocio opcional:
     * con él, la lista trae solo ese negocio (el modal de un negocio).
     * Un negocio sin fila de uso sale con ceros.
     */
    public function usoChatbotDeTodosLosNegocios(string $mes, ?int $idNegocio = null): array
    {
        try {
            $query = Negocio::from('negocios as n')
                ->leftJoin('chatbot_uso as cu', function ($join) use ($mes) {
                    $join->on('cu.tenant_id', '=', 'n.id_negocio')
                        ->where('cu.mes', '=', $mes);
                })
                ->select(
                    'n.id_negocio',
                    'n.nombre_negocio',
                    'n.chatbot_limite_mensual as limite_propio',
                    'cu.mensajes',
                    'cu.tokens_entrada',
                    'cu.tokens_salida',
                    DB::raw('(SELECT COUNT(*) FROM negocio_modulos nm JOIN modulos_plataforma mp ON mp.id_modulo = nm.id_modulo WHERE nm.tenant_id = n.id_negocio AND nm.activo = 1 AND mp.estado = 1 AND mp.clave = \''.SvcChatbot::CLAVE_MODULO.'\') AS modulo_activo')
                );

            if ($idNegocio !== null) {
                $query->where('n.id_negocio', $idNegocio);
            }

            return $query->orderBy('n.nombre_negocio')
                ->get()
                ->map(fn ($fila) => [
                    'id_negocio' => (int) $fila->id_negocio,
                    'nombre_negocio' => $fila->nombre_negocio,
                    'modulo_activo' => (int) $fila->modulo_activo > 0,
                    'mensajes' => (int) $fila->mensajes,
                    'tokens_entrada' => (int) $fila->tokens_entrada,
                    'tokens_salida' => (int) $fila->tokens_salida,
                    // La MISMA regla que aplica SvcChatbot::disponible().
                    'limite_efectivo' => SvcChatbot::limiteEfectivo($fila->limite_propio),
                    'limite_propio' => $fila->limite_propio !== null ? (int) $fila->limite_propio : null,
                ])
                ->all();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Resumen del chatbot para la tarjeta del dashboard: números agregados de
     * la plataforma. Los mensajes del mes salen del mismo método que usa el
     * tope global (SvcChatbot::mensajesGlobalesDelMes): una sola regla.
     */
    public function resumenChatbot(string $mes): array
    {
        try {
            $svc = new SvcChatbot;

            $tokens = ChatbotUso::select(
                DB::raw('COALESCE(SUM(tokens_entrada), 0) AS tokens_entrada'),
                DB::raw('COALESCE(SUM(tokens_salida), 0) AS tokens_salida')
            )->where('mes', $mes)->first();

            $negociosConModulo = NegocioModulo::from('negocio_modulos as nm')
                ->join('modulos_plataforma as mp', 'mp.id_modulo', '=', 'nm.id_modulo')
                ->where('mp.clave', SvcChatbot::CLAVE_MODULO)
                ->where('mp.estado', 1)
                ->where('nm.activo', 1)
                ->count();

            return [
                'mes' => $mes,
                'habilitado' => (bool) config('chatbot.habilitado'),
                'mensajes_del_mes' => $svc->mensajesGlobalesDelMes($mes),
                'tope_global_mensual' => (int) config('chatbot.tope_global_mensual'),
                'tokens_entrada' => (int) ($tokens->tokens_entrada ?? 0),
                'tokens_salida' => (int) ($tokens->tokens_salida ?? 0),
                'negocios_con_modulo' => $negociosConModulo,
            ];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Fija (o quita, con null) el límite mensual propio del chatbot de un
     * negocio. Solo toca negocios.chatbot_limite_mensual: los contadores de
     * chatbot_uso y su historial no cambian. $limite ya viene validado (null
     * o entero 0..LIMITE_MAXIMO_PROPIO). Devuelve false si el negocio no existe.
     */
    public function cambiarLimiteChatbot($idNegocio, ?int $limite, string $usuario): bool
    {
        try {
            $anterior = Negocio::where('id_negocio', $idNegocio)->first(['chatbot_limite_mensual']);

            if ($anterior === null) {
                return false;
            }

            Negocio::where('id_negocio', $idNegocio)->update(['chatbot_limite_mensual' => $limite]);

            $texto = fn ($valor) => $valor === null ? 'por defecto' : (string) $valor;

            Log::channel('database')->info(sprintf(
                'SUPERADMIN: limite mensual del chatbot del negocio %d cambiado de %s a %s por %s el %s',
                $idNegocio,
                $texto($anterior->chatbot_limite_mensual),
                $texto($limite),
                $usuario,
                now()->format('Y-m-d H:i:s')
            ));

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Suspende (0) o reactiva (1) un negocio.
     *
     * Solo toca negocios.estado: suspender NO borra ni modifica ningún dato del
     * negocio. El corte de acceso lo hacen el login y el middleware
     * sesion.activa, que revisan este campo.
     */
    public function cambiarEstadoNegocio($idNegocio, $estado, $usuario): bool
    {
        try {
            $afectadas = Negocio::where('id_negocio', $idNegocio)->update(['estado' => (int) $estado]);

            if ($afectadas === 0 && ! Negocio::where('id_negocio', $idNegocio)->exists()) {
                return false;
            }

            Log::channel('database')->info(sprintf(
                'SUPERADMIN: negocio %d %s por %s el %s',
                $idNegocio,
                (int) $estado === 1 ? 'REACTIVADO' : 'SUSPENDIDO',
                $usuario,
                now()->format('Y-m-d H:i:s')
            ));

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }
}
