<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Frontend del chatbot (chat del asistente en la página pública). PHPUnit no
 * ejecuta JS: revisa lo que el servidor entrega y el código real de los
 * parciales. El comportamiento en pantalla (abrir y cerrar, envío, XSS con
 * respuestas simuladas, persistencia, celular, etc.) se verificó con Chromium
 * y las peticiones simuladas con page.route (ver reporte): ninguna prueba llama
 * a la API de OpenAI.
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * Las 15 pasaron a la primera; se mutó el código una mutación a la vez,
 * restaurando cada vez (después: 15 passed).
 *
 * F1 — innerHTML en vez de textContent en el JS del chat: 1 FAILED —
 *      test_el_codigo_del_chat_no_inserta_nada_como_html.
 * F2 — un hexadecimal suelto (#9a5360) en el CSS del chat: 1 FAILED —
 *      test_el_chat_no_tiene_hexadecimales_ni_colores_literales.
 * F3 — un acceso a sessionStorage fuera de try/catch: 1 FAILED —
 *      test_todo_acceso_a_session_storage_va_dentro_de_try_catch.
 * F4 — el chat incluido en el layout del backoffice: 1 FAILED —
 *      test_el_chat_solo_se_incluye_desde_la_pagina_publica.
 * F5 — un <script src> externo nuevo en el parcial: 1 FAILED —
 *      test_el_parcial_no_carga_scripts_ni_recursos_externos_nuevos.
 * F6 — fetch en vez de axios: 1 FAILED —
 *      test_el_chat_usa_el_mismo_mecanismo_de_peticiones_que_el_modal_de_agendar.
 * F7 — un hexadecimal de la paleta fuera del bloque :root del layout: 1 FAILED
 *      — test_los_dos_colores_nuevos_viven_solo_en_el_bloque_de_la_paleta.
 */
class ChatbotFrontendTest extends TestCase
{
    use RefreshDatabase;

    const PAYLOAD = '<img src=x onerror=alert(1)>';

    private function crearNegocio(string $nombre, string $slug, array $extra = []): int
    {
        return DB::table('negocios')->insertGetId(array_merge([
            'nombre_negocio' => $nombre, 'slug' => $slug, 'rubro' => 'spa',
            'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01 08:00:00', 'estado' => 1,
        ], $extra));
    }

    private function js(string $parcial = 'chatbot'): string
    {
        $contenido = File::get(resource_path("views/publico/partials/$parcial.blade.php"));
        preg_match('#<script>(.*)</script>#s', $contenido, $m);

        return $this->sinComentarios($m[1] ?? '');
    }

    private function sinComentarios(string $codigo): string
    {
        $codigo = preg_replace('#/\*.*?\*/#s', '', $codigo);

        return preg_replace('#^\s*//.*$#m', '', $codigo);
    }

    /* ================= LA PÁGINA RENDERIZA EL CHAT ================= */

    public function test_la_pagina_trae_el_chat_con_sus_atributos_data(): void
    {
        $this->crearNegocio('Spa Aurora', 'spa-aurora', [
            'whatsapp_numero' => '+57 (300) 111-2233', 'politica_cancelacion' => 'Cancela con 24 horas.',
        ]);

        $html = $this->get('reservar/spa-aurora')->assertOk()->getContent();

        $this->assertStringContainsString('id="chat-raiz"', $html);
        $this->assertStringContainsString('data-slug="spa-aurora"', $html);
        $this->assertStringContainsString('data-nombre="Spa Aurora"', $html);
        $this->assertStringContainsString('data-tiene-politica="1"', $html);
        $this->assertStringContainsString('data-vida-minutos="'.config('chatbot.vida_conversacion_minutos').'"', $html);
        $this->assertStringContainsString('data-max-mensaje="'.config('chatbot.longitud_maxima_mensaje').'"', $html);
        // El mismo enlace (dígitos normalizados y mensaje precargado) del botón flotante.
        $this->assertStringContainsString('data-whatsapp-url="https://wa.me/573001112233?text=Hola%2C%20quiero%20agendar%20una%20cita"', $html);
        foreach (['estado', 'mensaje', 'terminar'] as $accion) {
            $this->assertStringContainsString('data-url-'.$accion.'="'.url('publico/spa-aurora/chatbot/'.$accion).'"', $html);
        }
        $this->assertStringContainsString('role="dialog" aria-label="Asistente virtual"', $html);
        $this->assertStringContainsString('role="log" aria-live="polite"', $html);
        $this->assertStringContainsString('aria-controls="chat-panel"', $html);
        $this->assertStringContainsString('escríbenos por WhatsApp.', $html);
        $this->assertStringNotContainsString('chat-lanzador chat-sin-wa', $html);
        $this->assertStringNotContainsString('chat-panel chat-sin-wa', $html);
        $this->assertSame(1, substr_count($html, '--oro-fuerte: #9a5360;'));
    }

    public function test_sin_whatsapp_no_hay_enlace_el_pie_cambia_y_el_chat_baja(): void
    {
        $this->crearNegocio('Casa Canela', 'casa-canela', ['politica_cancelacion' => 'Sin costo hasta 24 h antes.']);

        $html = $this->get('reservar/casa-canela')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-whatsapp-url="', $html);
        $this->assertStringNotContainsString('class="btn-whatsapp"', $html);
        $this->assertStringContainsString('para confirmar, contacta directamente al negocio.', $html);
        $this->assertStringNotContainsString('para confirmar, escríbenos por WhatsApp.', $html);
        $this->assertStringContainsString('class="chat-lanzador chat-sin-wa"', $html);
        $this->assertStringContainsString('class="chat-panel chat-sin-wa"', $html);
    }

    public function test_sin_politica_la_marca_va_en_cero(): void
    {
        $this->crearNegocio('Sin Politica', 'sin-politica', ['whatsapp_numero' => '573001112233']);

        $this->get('reservar/sin-politica')->assertOk()->assertSee('data-tiene-politica="0"', false);
    }

    public function test_la_vida_de_la_conversacion_sale_de_la_config(): void
    {
        config(['chatbot.vida_conversacion_minutos' => 7]);
        $this->crearNegocio('Spa Aurora', 'spa-aurora');

        $this->get('reservar/spa-aurora')->assertSee('data-vida-minutos="7"', false);
    }

    public function test_los_atributos_de_un_negocio_no_se_mezclan_con_los_de_otro(): void
    {
        $this->crearNegocio('Spa Aurora', 'spa-aurora', ['whatsapp_numero' => '573001112233']);
        $this->crearNegocio('Barberia Boreal', 'barberia-boreal', ['whatsapp_numero' => '573009998877', 'politica_cancelacion' => 'Politica secreta de Boreal.']);

        $html = $this->get('reservar/spa-aurora')->assertOk()->getContent();

        $this->assertStringNotContainsString('Barberia Boreal', $html);
        $this->assertStringNotContainsString('573009998877', $html);
        $this->assertStringNotContainsString('Politica secreta de Boreal', $html);
        $this->assertStringNotContainsString('barberia-boreal', $html);
        $this->assertStringContainsString('data-tiene-politica="0"', $html);
    }

    /* ================= ESCAPE ================= */

    public function test_un_nombre_con_html_y_comillas_sale_escapado_en_todas_partes(): void
    {
        $nombre = self::PAYLOAD.' "dobles" y \'simples\'';
        $this->crearNegocio($nombre, 'negocio-xss', ['whatsapp_numero' => '573001112233']);

        $html = $this->get('reservar/negocio-xss')->assertOk()->getContent();

        $this->assertStringNotContainsString(self::PAYLOAD, $html, 'El payload salió sin escapar en algún lugar');
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('data-nombre="&lt;img src=x onerror=alert(1)&gt; &quot;dobles&quot; y &#039;simples&#039;"', $html);
        // Aparece escapado también en el título y en el pie de la página.
        $this->assertGreaterThanOrEqual(3, substr_count($html, '&lt;img src=x onerror=alert(1)&gt;'));
    }

    /* ================= GUARDIANES DEL CÓDIGO ================= */

    public function test_el_codigo_del_chat_no_inserta_nada_como_html(): void
    {
        $this->assertNotSame('', $this->js(), 'No se encontró el script del chat');

        foreach (['innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'eval(', 'new Function', '.html(', 'jQuery(', '$(', 'createContextualFragment', 'srcdoc'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $this->js(), "El chat usa $prohibido: todo debe entrar con textContent");
        }

        $this->assertGreaterThanOrEqual(7, substr_count($this->js(), '.textContent = '));
        $this->assertStringContainsString("contenido.textContent = texto;", $this->js());
        $this->assertStringContainsString("linea.textContent = texto;", $this->js());
    }

    public function test_el_chat_no_tiene_hexadecimales_ni_colores_literales(): void
    {
        foreach (['chatbot', 'chatbot-estilos'] as $parcial) {
            $contenido = $this->sinComentarios(preg_replace('/\{\{--.*?--\}\}/s', '', File::get(resource_path("views/publico/partials/$parcial.blade.php"))));

            $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b/', $contenido), "Hexadecimal suelto en el parcial $parcial");
            $this->assertSame(0, preg_match('/\b(rgb|rgba|hsl|hsla)\(/i', $contenido), "Color literal en el parcial $parcial");
        }
    }

    public function test_los_dos_colores_nuevos_viven_solo_en_el_bloque_de_la_paleta(): void
    {
        $layout = File::get(resource_path('views/layout/publico.blade.php'));
        preg_match('/:root\s*\{(.*?)\n        \}/s', $layout, $bloque);

        $this->assertStringContainsString('--oro-fuerte: #9a5360;', $bloque[1]);
        $this->assertStringContainsString('--apagado: #6f655d;', $bloque[1]);
        $this->assertSame(1, substr_count($layout, '#9a5360'));
        $this->assertSame(1, substr_count($layout, '#6f655d'));
    }

    public function test_el_chat_solo_se_incluye_desde_la_pagina_publica(): void
    {
        $incluyen = [];

        foreach (File::allFiles(resource_path('views')) as $archivo) {
            $ruta = str_replace('\\', '/', $archivo->getRelativePathname());
            $contenido = File::get($archivo->getPathname());

            if (str_contains($contenido, "publico.partials.chatbot") || str_contains($contenido, 'id="chat-raiz"')) {
                $incluyen[] = $ruta;
            }
        }

        $this->assertEqualsCanonicalizing(['publico/pagina.blade.php', 'publico/partials/chatbot.blade.php'], $incluyen);

        foreach (File::allFiles(resource_path('views/app')) as $archivo) {
            $this->assertStringNotContainsString('chatbot-estilos', File::get($archivo->getPathname()), 'El chat no debe estar en el backoffice: '.$archivo->getFilename());
        }
        $this->assertStringNotContainsString('publico.partials', File::get(resource_path('views/layout/backoffice.blade.php')));
    }

    public function test_el_parcial_no_carga_scripts_ni_recursos_externos_nuevos(): void
    {
        $contenido = File::get(resource_path('views/publico/partials/chatbot.blade.php'))
            .File::get(resource_path('views/publico/partials/chatbot-estilos.blade.php'));

        $this->assertStringNotContainsString('<script src', $contenido);
        $this->assertStringNotContainsString('<link', $contenido);
        $this->assertStringNotContainsString('@import', $contenido);
        $this->assertStringNotContainsString('url(', File::get(resource_path('views/publico/partials/chatbot-estilos.blade.php')));
        $this->assertSame(1, substr_count($contenido, '<script>'), 'Un solo script, en línea');

        // La única dirección externa que menciona el código es el prefijo de WhatsApp.
        preg_match_all('#https?://[^\s\'"]+#', $this->js(), $direcciones);
        $this->assertSame(['https://wa.me/'], array_unique($direcciones[0]));
    }

    public function test_el_chat_usa_el_mismo_mecanismo_de_peticiones_que_el_modal_de_agendar(): void
    {
        $js = $this->js();
        $pagina = File::get(resource_path('views/publico/pagina.blade.php'));

        $this->assertStringContainsString("axios.post(urlAgendar, datos, { headers: { 'Accept': 'application/json' } })", $pagina);
        $this->assertStringContainsString("var CABECERAS = { headers: { 'Accept': 'application/json' } };", $js);
        $this->assertStringContainsString('axios.post(urlMensaje, cuerpo, CABECERAS)', $js);
        $this->assertStringContainsString('axios.post(urlTerminar, { conversacion_id: idAnterior }, CABECERAS)', $js);
        $this->assertStringContainsString('axios.get(urlEstado, CABECERAS)', $js);
        $this->assertStringNotContainsString('fetch(', $js);
        $this->assertStringNotContainsString('XMLHttpRequest', $js);
        $this->assertStringNotContainsString('console.', $js, 'El chat no escribe en la consola');
    }

    public function test_todo_acceso_a_session_storage_va_dentro_de_try_catch(): void
    {
        $js = $this->js();

        $this->assertSame(3, substr_count($js, 'window.sessionStorage'), 'Un acceso para leer, uno para guardar y uno para borrar');

        foreach (['getItem', 'setItem', 'removeItem'] as $metodo) {
            $posicion = strpos($js, $metodo);
            $this->assertNotFalse($posicion);
            $antes = substr($js, max(0, $posicion - 700), 700);
            $this->assertGreaterThan(strrpos($antes, '} catch'), strrpos($antes, 'try {') === false ? -1 : strrpos($antes, 'try {') + 1, "$metodo no está dentro de un try");
        }

        $this->assertStringContainsString('catch (e)', $js);
    }

    public function test_la_ventana_del_silencio_y_los_topes_de_la_especificacion(): void
    {
        $js = $this->js();

        $this->assertStringContainsString('var ESPERA_SILENCIO_MS = 120000;', $js);
        $this->assertStringContainsString('var MAXIMO_GUARDADOS = 40;', $js);
        $this->assertStringContainsString("'chat:' + (raiz.getAttribute('data-slug')", $js);
        $this->assertStringContainsString("indexOf('https://wa.me/') !== 0", $js);
        $this->assertStringContainsString("Tu conversación anterior terminó por inactividad.", $js);
        $this->assertStringContainsString("axios.post(urlTerminar", $js);
    }

    public function test_no_hay_formularios_ni_botones_submit_en_el_chat(): void
    {
        $contenido = File::get(resource_path('views/publico/partials/chatbot.blade.php'));

        $this->assertStringNotContainsString('<form', $contenido);
        $this->assertStringNotContainsString('type="submit"', $contenido);
        $this->assertSame(substr_count($contenido, '<button'), substr_count($contenido, '<button type="button"'));
    }
}
