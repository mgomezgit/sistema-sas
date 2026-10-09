<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guardianes del sistema de toast (notificarUsuario). PHPUnit no ejecuta JS:
 * revisan el código real de public/js/utilidades.js y de los estilos. La
 * ejecución real (toast en pantalla, tiempos, pausa, tope de 4, payloads XSS,
 * modos claro/oscuro/super admin) se verificó con Chromium (ver reporte).
 *
 * Reemplaza a test_notificar_usuario_escapa_su_mensaje de XssGuardianTest: ese
 * aviso ya no pasa por SweetAlert (que interpretaba HTML y por eso exigía
 * escapar); ahora el texto entra solo por textContent, que no interpreta nada.
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * Las 9 pasaron a la primera; se mutó el código para confirmar que detectan
 * cada falla (después de cada una: restaurado, 9 passed).
 *
 * T1 — mensaje con innerHTML en vez de textContent: 9 tests, 8 passed, 1
 *      FAILED — test_el_toast_no_inserta_titulo_ni_mensaje_como_html
 *      ("El código del toast usa innerHTML").
 * T2 — título con innerHTML: 9 tests, 8 passed, 1 FAILED — la misma.
 * T3 — notificarUsuario() vuelve a llamar a Swal.fire: 9 tests, 8 passed, 1
 *      FAILED — test_notificar_usuario_mantiene_su_firma_y_ya_no_usa_sweetalert.
 * T4 — un hexadecimal (#ffffff) en los estilos del toast: 9 tests, 8 passed,
 *      1 FAILED — test_los_estilos_del_toast_no_tienen_hexadecimales...
 * T5 — un llamador (productos) pasando escaparTexto(...) a notificarUsuario():
 *      9 tests, 8 passed, 1 FAILED — test_ningun_llamador_pasa_texto_escapado_ni_html
 *      (nombró el archivo y la llamada).
 * T6 — z-index del contenedor en 1000 (bajo los modales): 9 tests, 8 passed,
 *      1 FAILED — la misma de estilos ("debe quedar por encima de modales").
 */
class ToastGuardianTest extends TestCase
{
    /** Código del toast: desde sus constantes hasta antes de axiosSipleInterno, sin comentarios. */
    private function codigoToast(): string
    {
        $js = File::get(public_path('js/utilidades.js'));
        $inicio = strpos($js, 'var TOAST_DURACION_MS');
        $fin = strpos($js, 'const axiosSipleInterno');

        $this->assertNotFalse($inicio, 'No se encontró el código del toast en utilidades.js');
        $this->assertNotFalse($fin);

        return $this->sinComentarios(substr($js, $inicio, $fin - $inicio));
    }

    private function sinComentarios(string $codigo): string
    {
        $codigo = preg_replace('#/\*.*?\*/#s', '', $codigo);

        return preg_replace('#^\s*//.*$#m', '', $codigo);
    }

    private function cuerpoDeFuncion(string $codigo, string $nombre): string
    {
        $inicio = strpos($codigo, 'function '.$nombre.'(');
        $this->assertNotFalse($inicio, "No existe function $nombre()");

        return substr($codigo, $inicio, strpos($codigo, "\n}\n", $inicio) - $inicio + 3);
    }

    public function test_el_toast_no_inserta_titulo_ni_mensaje_como_html(): void
    {
        $codigo = $this->codigoToast();

        foreach (['innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'createContextualFragment', '.html(', 'jQuery('] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $codigo, "El código del toast usa $prohibido: el texto debe entrar solo por textContent");
        }

        $this->assertStringContainsString('tituloEl.textContent = titulo;', $codigo);
        $this->assertStringContainsString('mensajeEl.textContent = mensaje;', $codigo);
    }

    public function test_notificar_usuario_mantiene_su_firma_y_ya_no_usa_sweetalert(): void
    {
        $js = File::get(public_path('js/utilidades.js'));

        $this->assertStringContainsString('function notificarUsuario(Mensaje = "", icono = "info", urlRedireccion = "")', $js);
        $this->assertStringNotContainsString('async function notificarUsuario', $js, 'No debe devolver una promesa');

        $cuerpo = $this->sinComentarios($this->cuerpoDeFuncion($js, 'notificarUsuario'));

        $this->assertStringNotContainsString('Swal', $cuerpo, 'notificarUsuario() no debe abrir SweetAlert para mensajes');
        $this->assertStringNotContainsString('escaparTexto', $cuerpo, 'El mensaje entra por textContent: no se escapa');
        $this->assertStringNotContainsString('await', $cuerpo);
        $this->assertStringContainsString('mostrarToast(', $cuerpo);
        $this->assertStringNotContainsString('Swal', $this->codigoToast());
    }

    public function test_equivalencias_de_tipos_de_sweetalert_a_toast(): void
    {
        $codigo = $this->codigoToast();

        $this->assertStringContainsString('{ success: "exito", warning: "aviso", error: "error", info: "info" }', $codigo);

        foreach (['exito' => 'Listo', 'aviso' => 'Atención', 'error' => 'No se pudo completar', 'info' => 'Información'] as $tipo => $titulo) {
            $this->assertStringContainsString($tipo.': { titulo: "'.$titulo.'"', $codigo);
        }
    }

    public function test_duraciones_tope_y_accesibilidad(): void
    {
        $codigo = $this->codigoToast();

        $this->assertStringContainsString('var TOAST_DURACION_MS = 5000;', $codigo);
        $this->assertStringContainsString('var TOAST_DURACION_ERROR_MS = 8000;', $codigo);
        $this->assertStringContainsString('var TOAST_MAXIMO = 4;', $codigo);
        $this->assertStringContainsString('tipo === "error" ? TOAST_DURACION_ERROR_MS : TOAST_DURACION_MS', $codigo);
        $this->assertStringContainsString('"aria-live", "polite"', $codigo);
        $this->assertStringContainsString('tipo === "error" ? "alert" : "status"', $codigo);
        $this->assertStringContainsString('"aria-label", "Cerrar aviso"', $codigo);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $codigo);
        // Pausa con el ratón y con el foco, y repetido que no se apila.
        foreach (['mouseenter', 'mouseleave', 'focusin', 'focusout', 'toast.tipo === tipo && toast.texto === texto'] as $pieza) {
            $this->assertStringContainsString($pieza, $codigo);
        }
    }

    public function test_los_estilos_del_toast_no_tienen_hexadecimales_y_van_sobre_los_modales(): void
    {
        $css = File::get(resource_path('views/partials/estilos-toast.blade.php'));
        $sinComentarios = $this->sinComentarios($css);

        $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b/', $sinComentarios), 'Hexadecimal suelto en los estilos del toast: usa variables del tema');
        $this->assertSame(0, preg_match('/\brgba?\(|\bhsla?\(/', $sinComentarios), 'Color literal en los estilos del toast: usa variables del tema');

        foreach (['--success', '--warning', '--danger', '--accent'] as $variable) {
            $this->assertStringContainsString("var($variable)", $css);
        }

        preg_match('/\.toast-app-pila\s*\{[^}]*z-index:\s*(\d+)/s', $css, $z);
        $this->assertGreaterThan(1060, (int) ($z[1] ?? 0), 'El contenedor debe quedar por encima de modales (1055) y SweetAlert (1060)');
        $this->assertStringContainsString('@media (max-width: 575.98px)', $css);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
    }

    public function test_los_estilos_se_incluyen_en_el_layout_y_en_las_pantallas_de_acceso(): void
    {
        foreach (['layout/backoffice', 'login', 'registro', 'recuperar-clave'] as $vista) {
            $this->assertStringContainsString(
                "@include('partials.estilos-toast')",
                File::get(resource_path("views/$vista.blade.php")),
                "$vista debe incluir los estilos del toast"
            );
        }
    }

    /** Las pantallas de acceso tienen su propio tema: deben publicar las variables que usa el toast. */
    public function test_las_pantallas_de_acceso_definen_las_variables_del_toast(): void
    {
        foreach (['partials/estilos-acceso', 'registro'] as $vista) {
            $contenido = File::get(resource_path("views/$vista.blade.php"));

            foreach (['--success:', '--warning:', '--danger:', '--bg-card:', '--border-color:', '--text-primary:', '--text-secondary:', '--shadow-card:', '--accent:'] as $variable) {
                $this->assertStringContainsString($variable, $contenido, "$vista no define $variable");
            }
        }
    }

    /**
     * Sin doble escape: ningún llamador escapa el texto antes de pasárselo a
     * notificarUsuario() (se vería "&amp;" o "&#39;" literal), ni le pasa HTML.
     */
    public function test_ningun_llamador_pasa_texto_escapado_ni_html(): void
    {
        $sospechosos = [];
        $llamadas = 0;

        $archivos = array_merge(
            array_map(fn ($a) => $a->getPathname(), File::allFiles(resource_path('views'))),
            [public_path('js/utilidades.js')]
        );

        foreach ($archivos as $ruta) {
            $contenido = File::get($ruta);

            preg_match_all('/(?<!function )notificarUsuario\(((?:[^;])*?)\);/s', $contenido, $coincidencias);

            foreach ($coincidencias[1] as $argumentos) {
                $llamadas++;

                if (preg_match('/escaparTexto|renderTextoSeguro|&amp;|&lt;|&gt;|&quot;|&#39;|<\/?[a-z][a-z0-9]*[\s>\/]/i', $argumentos)) {
                    $sospechosos[] = basename($ruta).': notificarUsuario('.trim(preg_replace('/\s+/', ' ', $argumentos)).')';
                }
            }
        }

        $this->assertGreaterThan(80, $llamadas, 'El guardián no está viendo las llamadas reales');
        $this->assertSame([], $sospechosos, "Llamadas a notificarUsuario() con texto escapado o HTML:\n".implode("\n", $sospechosos));
    }

    /** Las confirmaciones (decisiones) siguen en SweetAlert: solo el aviso informativo pasó a toast. */
    public function test_las_confirmaciones_siguen_usando_sweetalert(): void
    {
        foreach (['app/clientes/listado', 'app/comisiones/informe', 'app/superadmin/negocios'] as $vista) {
            $this->assertStringContainsString('showCancelButton', File::get(resource_path("views/$vista.blade.php")));
        }
    }
}
