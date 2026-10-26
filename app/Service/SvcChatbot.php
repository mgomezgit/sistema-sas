<?php

namespace App\Service;

use App\Ai\Agents\AsistenteNegocio;
use App\Models\ChatbotUso;
use App\Models\Negocio;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Chatbot de la página pública (módulo de pago "chatbot"), versión 1: SOLO
 * responde preguntas.
 *
 * Reglas que sostienen este Service (ver también CLAUDE.md):
 *
 * - Sin session(): el tenant llega SIEMPRE como parámetro, resuelto por el
 *   controller desde el slug de la URL.
 * - El contexto sale ÚNICAMENTE de SvcPaginaPublica (la misma lista blanca de
 *   la página pública). Nada que la página no muestre llega al modelo.
 * - El historial vive solo en la caché del servidor, bajo una clave que lleva
 *   el tenant y un id aleatorio. Nunca se acepta historial enviado por el
 *   cliente.
 * - Lo único permanente son los contadores de chatbot_uso: ni preguntas, ni
 *   respuestas, ni datos del visitante.
 * - El límite del negocio y el tope global se comprueban y se reservan ANTES
 *   de llamar a la IA, bajo un bloqueo y con un UPDATE condicional.
 */
class SvcChatbot
{
    /**
     * Texto fijo para TODO caso en que el chatbot no responde con la IA:
     * módulo apagado, límite agotado, tope global, conversación demasiado
     * larga o fallo del proveedor. Es el mismo en todos a propósito: desde
     * fuera no se puede saber cuál fue el motivo ni cuánto uso queda.
     */
    const TEXTO_DERIVAR = 'En este momento no puedo ayudarte por aquí. Escríbenos por WhatsApp y con gusto te atendemos.';

    const CLAVE_MODULO = 'chatbot';

    const NOMBRES_DIAS = [
        1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves',
        5 => 'viernes', 6 => 'sábado', 7 => 'domingo',
    ];

    public function __construct(
        private SvcPaginaPublica $svcPaginaPublica = new SvcPaginaPublica,
        private SvcModulo $svcModulo = new SvcModulo,
    ) {}

    /* ================= DISPONIBILIDAD Y CUPO ================= */

    /**
     * ¿El chatbot de este negocio puede responder ahora?
     *
     * Interruptor global + módulo activo + bajo su límite del mes + bajo el
     * tope global del mes. El default es negar.
     */
    public function disponible($tenantId): bool
    {
        try {
            if (empty($tenantId) || ! config('chatbot.habilitado')) {
                return false;
            }

            if (! $this->svcModulo->estaActivo($tenantId, self::CLAVE_MODULO)) {
                return false;
            }

            $mes = $this->mesActual();

            if ($this->mensajesDelMes($tenantId, $mes) >= $this->limiteMensual($tenantId)) {
                return false;
            }

            return $this->mensajesGlobalesDelMes($mes) < (int) config('chatbot.tope_global_mensual');
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Reserva un mensaje del cupo del mes, de forma atómica.
     *
     * Dos capas: el bloqueo serializa la comprobación del tope global (una
     * suma entre negocios no se puede condicionar en un solo UPDATE), y el
     * UPDATE condicional "mensajes < límite" garantiza en la propia base que
     * el contador del negocio nunca pasa su límite aunque dos peticiones
     * lleguen a la vez. Si no se consigue el bloqueo a tiempo, se niega.
     */
    public function reservarCupo($tenantId): bool
    {
        try {
            $lock = Cache::lock('chatbot:cupo', 10);

            if (! $lock->block((int) config('chatbot.espera_bloqueo_segundos'))) {
                return false;
            }

            try {
                if (! $this->disponible($tenantId)) {
                    return false;
                }

                return $this->incrementarSiBajoLimite($tenantId, $this->mesActual(), $this->limiteMensual($tenantId));
            } finally {
                $lock->release();
            }
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * El UPDATE condicional: suma 1 solo si el contador sigue bajo el límite.
     * Devuelve false si otra petición se llevó el último cupo.
     */
    public function incrementarSiBajoLimite($tenantId, string $mes, int $limite): bool
    {
        try {
            ChatbotUso::insertOrIgnore([
                'tenant_id' => $tenantId,
                'mes' => $mes,
                'mensajes' => 0,
                'tokens_entrada' => 0,
                'tokens_salida' => 0,
                'fecha_registro' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            $afectadas = ChatbotUso::where('tenant_id', $tenantId)
                ->where('mes', $mes)
                ->where('mensajes', '<', $limite)
                ->increment('mensajes');

            return $afectadas === 1;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Devuelve el cupo reservado cuando la IA no llegó a responder.
     */
    private function liberarCupo($tenantId, string $mes): void
    {
        try {
            ChatbotUso::where('tenant_id', $tenantId)
                ->where('mes', $mes)
                ->where('mensajes', '>', 0)
                ->decrement('mensajes');
        } catch (\Exception $e) {
            Log::channel('database')->info($e);
        }
    }

    private function sumarTokens($tenantId, string $mes, int $entrada, int $salida): void
    {
        try {
            ChatbotUso::where('tenant_id', $tenantId)
                ->where('mes', $mes)
                ->incrementEach([
                    'tokens_entrada' => max(0, $entrada),
                    'tokens_salida' => max(0, $salida),
                ]);
        } catch (\Exception $e) {
            Log::channel('database')->info($e);
        }
    }

    public function limiteMensual($tenantId): int
    {
        try {
            return self::limiteEfectivo(Negocio::where('id_negocio', $tenantId)->value('chatbot_limite_mensual'));
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return 0;
        }
    }

    /** Límite máximo que el super admin puede fijar a un negocio. */
    const LIMITE_MAXIMO_PROPIO = 10000;

    /**
     * La ÚNICA regla del límite mensual: el propio del negocio si lo tiene
     * (null = no tiene), y si no, el de config. Un 0 propio es un límite
     * válido: el negocio queda bloqueado (disponible() da false). La usan
     * disponible(), reservarCupo() y la pantalla del super admin: así lo que
     * se muestra es exactamente lo que se aplica.
     */
    public static function limiteEfectivo($limitePropio): int
    {
        return $limitePropio !== null ? (int) $limitePropio : (int) config('chatbot.limite_mensual');
    }

    /**
     * Mes YYYY-MM validado estrictamente: null o vacío = el actual; uno
     * futuro, mal formado o inexistente (2026-13) = null. Un mes pasado es
     * válido (historial).
     */
    public function normalizarMes($mes): ?string
    {
        if ($mes === null || $mes === '') {
            return $this->mesActual();
        }

        if (! is_string($mes) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) || $mes > $this->mesActual()) {
            return null;
        }

        return $mes;
    }

    /**
     * Uso de UN negocio en un mes: solo números. Un negocio sin fila de uso
     * devuelve ceros, no un error. Lista blanca de campos explícita.
     */
    public function usoDelMes($tenantId, ?string $mes = null): array
    {
        try {
            $mes = $this->normalizarMes($mes) ?? $this->mesActual();

            $uso = ChatbotUso::select('mensajes', 'tokens_entrada', 'tokens_salida')
                ->where('tenant_id', $tenantId)
                ->where('mes', $mes)
                ->first();

            $propio = Negocio::where('id_negocio', $tenantId)->value('chatbot_limite_mensual');

            return [
                'mes' => $mes,
                'mensajes' => (int) ($uso->mensajes ?? 0),
                'tokens_entrada' => (int) ($uso->tokens_entrada ?? 0),
                'tokens_salida' => (int) ($uso->tokens_salida ?? 0),
                'limite_efectivo' => self::limiteEfectivo($propio),
                'limite_propio' => $propio !== null ? (int) $propio : null,
            ];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function mensajesDelMes($tenantId, string $mes): int
    {
        try {
            return (int) ChatbotUso::where('tenant_id', $tenantId)->where('mes', $mes)->value('mensajes');
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return PHP_INT_MAX;
        }
    }

    /**
     * ⚠️ EXCEPCIÓN DOCUMENTADA A LA REGLA MULTI-TENANT: suma los mensajes del
     * mes de TODOS los negocios, sin filtro de tenant. Devuelve solo un
     * número (el uso total de la plataforma contra el tope global), nunca
     * datos de ningún negocio, y no se expone a nadie: solo decide si el
     * chatbot está disponible.
     */
    public function mensajesGlobalesDelMes(string $mes): int
    {
        try {
            return (int) ChatbotUso::where('mes', $mes)->sum('mensajes');
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return PHP_INT_MAX;
        }
    }

    public function mesActual(): string
    {
        return Carbon::now()->format('Y-m');
    }

    /* ================= CONTEXTO ================= */

    /**
     * Lo que el modelo sabe del negocio. TODO sale de SvcPaginaPublica, y de
     * ahí solo los campos que la página pública ya muestra: sin ids, sin
     * correos, sin teléfonos de empleados.
     */
    public function construirContexto($tenantId): array
    {
        try {
            $negocio = $this->svcPaginaPublica->resolverPorId((int) $tenantId);

            if ($negocio === null) {
                return [];
            }

            $info = $this->svcPaginaPublica->informacion($negocio);

            $servicios = array_map(function ($servicio) {
                return [
                    'nombre' => $servicio['nombre'],
                    'categoria' => $servicio['categoria'],
                    'duracion_minutos' => $servicio['duracion_minutos'],
                    'precio' => $this->formatearPrecio($servicio['precio']),
                ];
            }, $this->svcPaginaPublica->servicios((int) $tenantId));

            $equipo = array_map(
                fn ($empleado) => $empleado['nombre'],
                $this->svcPaginaPublica->equipo((int) $tenantId)
            );

            return [
                'nombre_negocio' => $info['nombre_negocio'],
                'horario' => [
                    'dias' => $this->diasLegibles($info['dias_atencion']),
                    'hora_apertura' => $this->horaCorta($info['hora_apertura']),
                    'hora_cierre' => $this->horaCorta($info['hora_cierre']),
                ],
                'servicios' => $servicios,
                'equipo' => $equipo,
                'politica_cancelacion' => $info['politica_cancelacion'] ?? '',
                'whatsapp' => $info['whatsapp_numero'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    private function diasLegibles($diasAtencion): array
    {
        if (empty($diasAtencion)) {
            return [];
        }

        $dias = array_map('intval', explode(',', (string) $diasAtencion));

        return array_values(array_filter(array_map(
            fn ($dia) => self::NOMBRES_DIAS[$dia] ?? null,
            $dias
        )));
    }

    private function horaCorta($hora): string
    {
        return empty($hora) ? '' : substr((string) $hora, 0, 5);
    }

    private function formatearPrecio($precio): string
    {
        $precio = (float) $precio;
        $decimales = floor($precio) == $precio ? 0 : 2;

        return '$'.number_format($precio, $decimales, ',', '.');
    }

    /* ================= CONVERSACIÓN ================= */

    /**
     * Responde un mensaje del visitante.
     *
     * Devuelve siempre ['respuesta', 'conversacion_id', 'derivar_whatsapp'];
     * la respuesta es texto plano (el frontend la pinta con textContent).
     * Nunca lanza: cualquier problema termina en TEXTO_DERIVAR.
     */
    public function responder($tenantId, $conversacionId, $mensaje): array
    {
        $conversacion = $this->cargarConversacion($tenantId, $conversacionId);
        $idConversacion = $conversacion['id'];

        $derivar = fn () => [
            'respuesta' => self::TEXTO_DERIVAR,
            'conversacion_id' => $idConversacion,
            'derivar_whatsapp' => true,
        ];

        try {
            $mensaje = is_string($mensaje) ? trim($mensaje) : '';

            if ($mensaje === '' || mb_strlen($mensaje) > (int) config('chatbot.longitud_maxima_mensaje')) {
                return $derivar();
            }

            if ($conversacion['mensajes'] >= (int) config('chatbot.max_mensajes_por_conversacion')) {
                return $derivar();
            }

            // El cupo se reserva ANTES de llamar a la IA.
            if (! $this->reservarCupo($tenantId)) {
                return $derivar();
            }

            $mes = $this->mesActual();

            try {
                $respuesta = (new AsistenteNegocio($this->construirContexto($tenantId)))
                    ->withMessages($conversacion['turnos'])
                    ->prompt(
                        $mensaje,
                        provider: config('chatbot.proveedor'),
                        model: config('chatbot.modelo'),
                        timeout: (int) config('chatbot.timeout'),
                    );
            } catch (\Throwable $e) {
                $this->liberarCupo($tenantId, $mes);
                $this->registrarFallo($tenantId, $e);

                return $derivar();
            }

            $this->sumarTokens($tenantId, $mes, (int) $respuesta->usage->inputTokens, (int) $respuesta->usage->outputTokens);

            $texto = $this->textoPlano($respuesta->text);

            // Una respuesta vacía (p. ej. cortada por el tope de tokens) no se
            // muestra ni se recuerda: se deriva igual que un fallo. El
            // mensaje sí costó, así que el cupo no se devuelve.
            if ($texto === '') {
                return $derivar();
            }

            $this->guardarTurno($tenantId, $idConversacion, $conversacion, $mensaje, $texto);

            return [
                'respuesta' => $texto,
                'conversacion_id' => $idConversacion,
                'derivar_whatsapp' => stripos($texto, 'whatsapp') !== false,
            ];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return $derivar();
        }
    }

    /**
     * Conversación guardada en la caché, o una nueva.
     *
     * La clave lleva el tenant: un id de otro negocio, inventado o vencido
     * simplemente no se encuentra, y se responde con una conversación nueva
     * con OTRO id, sin decir si aquel existió.
     */
    private function cargarConversacion($tenantId, $conversacionId): array
    {
        try {
            if (is_string($conversacionId) && preg_match('/^[A-Za-z0-9]{40}$/', $conversacionId)) {
                $guardada = Cache::get($this->claveCache($tenantId, $conversacionId));

                if (is_array($guardada) && isset($guardada['turnos'])) {
                    return [
                        'id' => $conversacionId,
                        'turnos' => $guardada['turnos'],
                        'mensajes' => (int) ($guardada['mensajes'] ?? 0),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::channel('database')->info($e);
        }

        return ['id' => Str::random(40), 'turnos' => [], 'mensajes' => 0];
    }

    /**
     * Agrega la pregunta y la respuesta, recorta a los últimos N turnos y
     * renueva la vida de la conversación.
     */
    private function guardarTurno($tenantId, string $idConversacion, array $conversacion, string $mensaje, string $texto): void
    {
        try {
            $turnos = $conversacion['turnos'];
            $turnos[] = ['role' => 'user', 'content' => $mensaje];
            $turnos[] = ['role' => 'assistant', 'content' => $texto];

            $maxMensajes = max(0, (int) config('chatbot.max_turnos_recordados')) * 2;
            $turnos = array_slice($turnos, -$maxMensajes);

            Cache::put(
                $this->claveCache($tenantId, $idConversacion),
                ['turnos' => $turnos, 'mensajes' => $conversacion['mensajes'] + 1],
                Carbon::now()->addMinutes((int) config('chatbot.vida_conversacion_minutos'))
            );
        } catch (\Exception $e) {
            Log::channel('database')->info($e);
        }
    }

    /**
     * El visitante termina su conversación: el servidor olvida el historial
     * de inmediato.
     *
     * Solo borra la conversación de ESTE negocio: la clave lleva el tenant,
     * así que un id de otro negocio apunta a una clave que no existe y no
     * borra nada. No devuelve nada a propósito: quien llama responde lo mismo
     * exista la conversación o no. Un id con formato distinto al que genera
     * el servidor no toca la caché. No llama a la IA ni toca los contadores.
     */
    public function terminar($tenantId, $conversacionId): void
    {
        try {
            if (empty($tenantId) || ! is_string($conversacionId) || ! preg_match('/^[A-Za-z0-9]{40}$/', $conversacionId)) {
                return;
            }

            Cache::forget($this->claveCache($tenantId, $conversacionId));
        } catch (\Exception $e) {
            Log::channel('database')->info($e);
        }
    }

    /**
     * ⚠️ PROCESO DE SISTEMA (comando del Scheduler, sin sesión ni tenant): la
     * tabla de caché y la de bloqueos no son datos de ningún negocio.
     *
     * La caché tipo database solo borra una fila vencida cuando alguien lee
     * esa misma clave (y los bloqueos, por sorteo del 2 %). Una conversación
     * abandonada nunca se vuelve a leer, así que su texto se quedaría ahí.
     * Esto borra SOLO las filas con expiración anterior a ahora, de ambas
     * tablas. Devuelve cuántas borró de cada una, o false si falló.
     *
     * @return array{cache: int, locks: int}|false
     */
    public function limpiarCacheVencida(): array|false
    {
        try {
            $ahora = Carbon::now()->getTimestamp();
            $almacen = config('cache.stores.database', []);
            $conexion = DB::connection($almacen['connection'] ?? null);

            return [
                'cache' => $conexion->table($almacen['table'] ?? 'cache')
                    ->where('expiration', '<', $ahora)
                    ->delete(),
                'locks' => $conexion->table($almacen['lock_table'] ?? 'cache_locks')
                    ->where('expiration', '<', $ahora)
                    ->delete(),
            ];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    private function claveCache($tenantId, string $idConversacion): string
    {
        return 'chatbot:conversacion:'.$tenantId.':'.$idConversacion;
    }

    /**
     * La respuesta se devuelve como texto plano: se quitan etiquetas por si
     * el modelo las generara. El escape final lo hace el frontend al pintar
     * con textContent.
     */
    private function textoPlano($texto): string
    {
        return trim(strip_tags((string) $texto));
    }

    /**
     * Registra un fallo del proveedor SIN el contenido del mensaje y SIN el
     * texto de la excepción: el cuerpo de un 401 de OpenAI puede traer la
     * clave enmascarada, y el mensaje del visitante no se guarda nunca. Solo
     * negocio, tipo de error y código HTTP.
     */
    private function registrarFallo($tenantId, \Throwable $e): void
    {
        $codigo = $e instanceof RequestException && $e->response !== null
            ? $e->response->status()
            : ($e->getPrevious() instanceof RequestException && $e->getPrevious()->response !== null
                ? $e->getPrevious()->response->status()
                : $e->getCode());

        Log::channel('database')->info(
            'Chatbot: fallo del proveedor en el negocio '.$tenantId.': '.class_basename($e).' (codigo '.$codigo.').'
        );
    }
}
