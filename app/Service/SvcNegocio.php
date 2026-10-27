<?php

namespace App\Service;

use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\RecursoReservable;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SvcNegocio
{
    /**
     * Arma el slug con el que un negocio se identifica en su página pública.
     *
     * La unicidad se comprueba contra TODA la tabla, sin acotar por tenant: el
     * slug vive en una URL pública global (/reservar/spa-fashion), así que dos
     * negocios distintos no pueden compartirlo aunque no se conozcan entre sí.
     * Si el nombre ya está tomado se le añade un sufijo numérico incremental.
     *
     * Str::slug() ya hace el trabajo de normalizar: pasa a minúsculas,
     * transcribe las tildes y la eñe, y convierte los espacios y los signos en
     * guiones. Si de un nombre no queda nada utilizable (un nombre hecho solo
     * de símbolos, por ejemplo) se cae a "negocio", porque un slug vacío daría
     * una URL rota.
     *
     * @param  int|null  $idNegocioExcluir  El propio negocio, al editarlo: su
     *                                      slug actual no cuenta como choque.
     */
    public function generarSlug($nombreNegocio, $idNegocioExcluir = null): string
    {
        $base = Str::slug((string) $nombreNegocio);

        if ($base === '') {
            $base = 'negocio';
        }

        $base = Str::limit($base, 90, '');
        $candidato = $base;
        $sufijo = 1;

        while ($this->slugOcupado($candidato, $idNegocioExcluir)) {
            $sufijo++;
            $candidato = $base.'-'.$sufijo;
        }

        return $candidato;
    }

    /**
     * ¿Ese slug ya lo tiene OTRO negocio? La consulta es global a propósito.
     */
    public function slugOcupado($slug, $idNegocioExcluir = null): bool
    {
        $query = Negocio::where('slug', $slug);

        if ($idNegocioExcluir !== null) {
            $query->where('id_negocio', '!=', $idNegocioExcluir);
        }

        return $query->exists();
    }

    public function crear($info)
    {
        try {
            $negocio = Negocio::create($info);

            return $negocio->id_negocio;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function listar()
    {
        try {
            return Negocio::select('id_negocio', 'nombre_negocio', 'rubro', 'telefono_contacto', 'modo_tema', 'color_acento', 'estado')
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function obtenerConfiguracion($tenantId)
    {
        try {
            $negocio = Negocio::select(
                'id_negocio',
                'nombre_negocio',
                'slug',
                'telefono_contacto',
                'whatsapp_numero',
                'dias_atencion',
                'hora_apertura',
                'hora_cierre',
                'politica_cancelacion',
                'frase_destacada',
                'frase_hasta'
            )
                ->where('id_negocio', $tenantId)
                ->first();

            if (! $negocio) {
                return [];
            }

            $config = $negocio->toArray();
            // Solo para el panel (nunca público): si la frase se ve hoy.
            $config['frase_vigente'] = self::fraseVigente($config['frase_destacada'], $config['frase_hasta']);

            return $config;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Guarda la configuración del negocio.
     *
     * EL SLUG NUNCA SE REGENERA SOLO. Una vez que existe —lo haya puesto el
     * alta del negocio o el propio admin— es una dirección pública que ya se
     * repartió en enlaces, códigos QR y redes. Renombrar el negocio no puede
     * romper eso por la espalda: cambiar el nombre mil veces deja el slug
     * exactamente igual.
     *
     * La única forma de cambiarlo es que el admin lo escriba él mismo en
     * Configuración, que es cuando llega en $info['slug'].
     */
    public function actualizarConfiguracion($tenantId, $info): bool
    {
        try {
            if (empty($info['nombre_negocio'])) {
                return false;
            }

            $query = Negocio::where('id_negocio', $tenantId);

            if (! $query->exists()) {
                return false;
            }

            $campos = [
                'nombre_negocio' => $info['nombre_negocio'],
                'telefono_contacto' => $info['telefono_contacto'] ?? null,
                'dias_atencion' => $info['dias_atencion'] ?? null,
                'hora_apertura' => $info['hora_apertura'] ?? null,
                'hora_cierre' => $info['hora_cierre'] ?? null,
            ];

            // WhatsApp y política de cancelación son opcionales y aquí el
            // comportamiento es distinto al del slug: si el admin dejó el campo
            // vacío es porque quiere BORRARLO, no que se conserve lo que había.
            // El Controller ya normalizó y validó antes; el Service solo escribe.
            //
            // NOTA de seguridad: politica_cancelacion se muestra en la página
            // pública y llega escrito por el admin. Al renderizarlo, la vista
            // pública debe escaparlo con Blade {{ }}, nunca {!! !!}. Aquí se
            // guarda tal cual: no es cifra ni saludo, es texto libre del negocio.
            if (array_key_exists('whatsapp_numero', $info)) {
                $campos['whatsapp_numero'] = $info['whatsapp_numero'];
            }

            if (array_key_exists('politica_cancelacion', $info)) {
                $campos['politica_cancelacion'] = $info['politica_cancelacion'];
            }

            // Solo si el admin escribió una dirección nueva. Si no viene, el
            // slug guardado ni se menciona en el update: se queda como está.
            $slugPedido = isset($info['slug']) ? Str::slug((string) $info['slug']) : '';

            if ($slugPedido !== '') {
                if ($this->slugOcupado($slugPedido, $tenantId)) {
                    return false;
                }

                $campos['slug'] = $slugPedido;
            }

            $query->update($campos);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /* ================= FRASE DESTACADA ================= */

    const FRASE_MAXIMO = 140;

    /**
     * Frase tal como se guarda: sin espacios en los extremos ni repetidos
     * (los saltos de línea y tabulaciones cuentan como espacios). Devuelve
     * null si no sirve: vacía, de más de 140 caracteres, con caracteres de
     * control o con texto que no es UTF-8 válido.
     *
     * Se guarda tal cual la escribió el admin: el escape se hace al PINTAR
     * (Blade {{ }}, textContent), nunca al guardar.
     */
    public static function normalizarFrase($texto): ?string
    {
        if (! is_string($texto)) {
            return null;
        }

        $limpio = preg_replace('/\s+/u', ' ', $texto);

        if ($limpio === null) {
            return null;
        }

        $limpio = trim($limpio);

        if ($limpio === '' || mb_strlen($limpio) > self::FRASE_MAXIMO || preg_match('/[\p{Cc}\p{Cf}]/u', $limpio) === 1) {
            return null;
        }

        return $limpio;
    }

    /**
     * Fecha de fin válida: vacía (sin vencimiento) o AAAA-MM-DD real y no
     * anterior a hoy. Se compara contra Carbon::today(), no contra el reloj
     * del validador de Laravel, para que el reloj congelado de las pruebas
     * mande.
     */
    public static function fechaHastaValida($hasta): bool
    {
        if ($hasta === null || $hasta === '') {
            return true;
        }

        if (! is_string($hasta) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) !== 1) {
            return false;
        }

        $fecha = Carbon::createFromFormat('!Y-m-d', $hasta);

        return $fecha !== false && $fecha->format('Y-m-d') === $hasta && $hasta >= Carbon::today()->format('Y-m-d');
    }

    /**
     * ¿Se ve hoy la frase? Hay texto y, o no tiene fecha de fin, o hoy es ese
     * día o antes (inclusive). ÚNICA regla de vigencia: la usan la página
     * pública y el estado que muestra el panel, para que digan lo mismo.
     */
    public static function fraseVigente($frase, $hasta): bool
    {
        if ($frase === null || trim((string) $frase) === '') {
            return false;
        }

        return $hasta === null || $hasta === '' || substr((string) $hasta, 0, 10) >= Carbon::today()->format('Y-m-d');
    }

    /**
     * Guarda (o reemplaza) la frase destacada del negocio. El texto y la
     * fecha se vuelven a validar aquí: el Service no confía en que el
     * Controller lo haya hecho. $hasta vacío = sin vencimiento.
     */
    public function guardarFrase($tenantId, $texto, $hasta): bool
    {
        try {
            $frase = self::normalizarFrase($texto);

            if (empty($tenantId) || $frase === null || ! self::fechaHastaValida($hasta)) {
                return false;
            }

            $afectados = Negocio::where('id_negocio', $tenantId)->update([
                'frase_destacada' => $frase,
                'frase_hasta' => ($hasta === null || $hasta === '') ? null : $hasta,
            ]);

            return $afectados === 1;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /** Borra el texto y la fecha de la frase destacada. */
    public function quitarFrase($tenantId): bool
    {
        try {
            if (empty($tenantId)) {
                return false;
            }

            Negocio::where('id_negocio', $tenantId)->update([
                'frase_destacada' => null,
                'frase_hasta' => null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Horario de atención, usado por el calendario de reservas para calcular
     * las franjas disponibles.
     */
    public function obtenerHorario($tenantId)
    {
        try {
            $negocio = Negocio::select('dias_atencion', 'hora_apertura', 'hora_cierre')
                ->where('id_negocio', $tenantId)
                ->first();

            return $negocio ? $negocio->toArray() : [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Verifica que la fecha y el horario caigan dentro de los días y las horas
     * de atención configurados por el negocio. Cada dato ausente (día no
     * configurado, o apertura/cierre no configurados) simplemente no se valida,
     * así que un negocio que no ha configurado nada de esto no queda bloqueado.
     */
    public function estaDentroDelHorario($tenantId, $fecha, $horaInicio, $horaFin)
    {
        try {
            $horario = $this->obtenerHorario($tenantId);

            $diasAtencion = $horario['dias_atencion'] ?? null;
            $horaApertura = $horario['hora_apertura'] ?? null;
            $horaCierre = $horario['hora_cierre'] ?? null;

            if (! empty($diasAtencion)) {
                // Carbon::parse() interpreta la fecha de forma consistente sin
                // depender de la zona horaria del servidor (a diferencia de
                // strtotime(), que sí se ve afectado por ella); dayOfWeekIso ya
                // usa la misma convención que dias_atencion: 1=lunes...7=domingo.
                $dias = array_map('intval', explode(',', $diasAtencion));
                $diaSemana = Carbon::parse($fecha)->dayOfWeekIso;

                if (! in_array($diaSemana, $dias, true)) {
                    return false;
                }
            }

            // Se ancla al mismo día de referencia en ambos lados para que la
            // comparación sea puramente de horas, sin importar el formato exacto
            // ("08:00" vs "08:00:00") con el que llegue cada dato.
            if (! empty($horaApertura) && Carbon::parse($horaInicio)->lt(Carbon::parse($horaApertura))) {
                return false;
            }

            if (! empty($horaCierre) && Carbon::parse($horaFin)->gt(Carbon::parse($horaCierre))) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            // Ante un fallo no se bloquea la reserva: es preferible no romper la
            // creación de reservas por un problema al leer el horario.
            return true;
        }
    }

    /**
     * Progreso del onboarding: cada paso se deduce del estado real del negocio,
     * no de banderas que haya que ir marcando a mano.
     */
    public function obtenerProgresoOnboarding($tenantId)
    {
        try {
            $negocio = Negocio::select(
                'dias_atencion',
                'tema_personalizado',
                'tour_completado',
                'bienvenida_vista',
                'reportes_tour_visto'
            )
                ->where('id_negocio', $tenantId)
                ->first();

            if (! $negocio) {
                return ['tour_completado' => true, 'bienvenida_vista' => true, 'pasos' => []];
            }

            return [
                'tour_completado' => (bool) $negocio->tour_completado,
                'bienvenida_vista' => (bool) $negocio->bienvenida_vista,
                'pasos' => [
                    [
                        'id' => 'personalizar',
                        'completado' => (bool) $negocio->tema_personalizado,
                    ],
                    [
                        'id' => 'horario',
                        'completado' => ! empty($negocio->dias_atencion),
                    ],
                    [
                        'id' => 'recurso',
                        'completado' => RecursoReservable::where('tenant_id', $tenantId)->where('estado', 1)->exists(),
                    ],
                    [
                        'id' => 'empleado',
                        'completado' => Empleado::where('tenant_id', $tenantId)->where('estado', 1)->exists(),
                    ],
                    [
                        'id' => 'cliente',
                        'completado' => Cliente::where('tenant_id', $tenantId)->where('estado', 1)->exists(),
                    ],
                    [
                        'id' => 'reserva',
                        'completado' => Reserva::where('tenant_id', $tenantId)->where('estado', 1)->exists(),
                    ],
                    [
                        'id' => 'inventario',
                        'completado' => Producto::where('tenant_id', $tenantId)->where('estado', 1)->exists(),
                    ],
                    [
                        // Único paso que no se deduce de un registro creado: no
                        // hay nada que "crear" en reportes, así que se marca
                        // cuando el negocio termina el tour que los explica.
                        'id' => 'reportes',
                        'completado' => (bool) $negocio->reportes_tour_visto,
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            // Ante un fallo se responde como "ya terminado" para que el widget
            // simplemente no aparezca, en vez de romper la pantalla.
            return ['tour_completado' => true, 'bienvenida_vista' => true, 'pasos' => []];
        }
    }

    /**
     * Marca que el negocio ya completó el tour guiado de Reportes, que es lo
     * que da por cumplido ese paso del onboarding.
     */
    public function marcarReportesTourVisto($tenantId): bool
    {
        try {
            $query = Negocio::where('id_negocio', $tenantId);

            if (! $query->exists()) {
                return false;
            }

            $query->update(['reportes_tour_visto' => true]);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function marcarBienvenidaVista($tenantId): bool
    {
        try {
            $query = Negocio::where('id_negocio', $tenantId);

            if (! $query->exists()) {
                return false;
            }

            $query->update(['bienvenida_vista' => true]);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function completarOnboarding($tenantId): bool
    {
        try {
            $query = Negocio::where('id_negocio', $tenantId);

            if (! $query->exists()) {
                return false;
            }

            $query->update(['tour_completado' => true]);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    // Valores admitidos para el tema del backoffice.
    const MODOS_VALIDOS = ['claro', 'oscuro'];

    const ACENTOS_VALIDOS = ['oro_rosa', 'dorado', 'amarillo', 'naranja', 'rojo', 'azul', 'verde'];

    public function actualizarTema($tenantId, $modoTema, $colorAcento): bool
    {
        try {
            // Se validan aquí también (no solo en el Controller) para que ningún
            // llamador pueda guardar un valor que el CSS no sabe representar.
            if (! in_array($modoTema, self::MODOS_VALIDOS, true)) {
                return false;
            }

            if (! in_array($colorAcento, self::ACENTOS_VALIDOS, true)) {
                return false;
            }

            $query = Negocio::where('id_negocio', $tenantId);

            if (! $query->exists()) {
                return false;
            }

            $query->update([
                'modo_tema' => $modoTema,
                'color_acento' => $colorAcento,
                // Guardar el tema cuenta como paso completado del onboarding.
                'tema_personalizado' => true,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }
}
