<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guardianes estáticos contra XSS: recorren el código de las vistas y fallan
 * si aparece una forma conocida de pintar texto de usuario sin escapar.
 *
 * No reemplazan a la verificación con navegador (que se hizo en la auditoría
 * y se documenta en el reporte), pero hacen que una vista nueva con el mismo
 * error rompa la suite en vez de llegar a producción.
 *
 * La regla del proyecto: todo texto que escribe una persona y se concatena en
 * HTML pasa por escaparTexto() (public/js/utilidades.js, la ÚNICA función de
 * escape), o se asigna con .text(). En Blade, siempre {{ }}.
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * M1 — Se agregó {!! $x !!} en app/dashboard.blade.php: 6 tests, 5 passed,
 *      1 FAILED — test_no_hay_salida_blade_sin_escapar_fuera_de_la_lista_blanca,
 *      que nombró "app/dashboard.blade.php: {!! $x !!}". Restaurado: 6 passed.
 * M5 — La columna 'nombre' de Clientes volvió a { data: 'nombre' } sin render:
 *      6 tests, 5 passed, 1 FAILED —
 *      test_ninguna_columna_de_datatables_pinta_texto_sin_render. Restaurado:
 *      6 passed.
 * (M2, M3 y M4 se documentan en XssHttpTest.)
 */
class XssGuardianTest extends TestCase
{
    /**
     * Expresiones que SÍ pueden imprimirse sin escapar con {!! !!}, porque se
     * sabe que su contenido es seguro. Hoy la lista está VACÍA a propósito: el
     * proyecto no usa {!! !!} en ninguna vista. Para sumar una expresión aquí
     * hay que escribir al lado por qué su contenido nunca lo controla un
     * usuario (por ejemplo, HTML generado por el propio código a partir de
     * datos ya escapados).
     *
     * Formato: 'ruta relativa a resources/views' => ['expresión exacta', ...]
     */
    const LISTA_BLANCA_SIN_ESCAPAR = [];

    /** @return array<string, string> ruta relativa => contenido */
    private function vistas(): array
    {
        $vistas = [];

        foreach (File::allFiles(resource_path('views')) as $archivo) {
            if (str_ends_with($archivo->getFilename(), '.blade.php')) {
                $vistas[str_replace('\\', '/', $archivo->getRelativePathname())] = $archivo->getContents();
            }
        }

        return $vistas;
    }

    /** Líneas de todas las vistas que cumplen un patrón, como "archivo:línea: texto". */
    private function lineasQueCumplen(string $patron, ?callable $excepto = null): array
    {
        $encontradas = [];

        foreach ($this->vistas() as $ruta => $contenido) {
            foreach (preg_split('/\R/', $contenido) as $numero => $linea) {
                if (preg_match($patron, $linea) && ($excepto === null || ! $excepto($linea))) {
                    $encontradas[] = $ruta.':'.($numero + 1).': '.trim($linea);
                }
            }
        }

        return $encontradas;
    }

    public function test_no_hay_salida_blade_sin_escapar_fuera_de_la_lista_blanca(): void
    {
        $sinPermiso = [];

        foreach ($this->vistas() as $ruta => $contenido) {
            preg_match_all('/\{!!\s*(.+?)\s*!!\}/s', $contenido, $coincidencias);

            foreach ($coincidencias[1] as $expresion) {
                $permitidas = self::LISTA_BLANCA_SIN_ESCAPAR[$ruta] ?? [];

                if (! in_array($expresion, $permitidas, true)) {
                    $sinPermiso[] = "$ruta: {!! $expresion !!}";
                }
            }
        }

        $this->assertSame(
            [],
            $sinPermiso,
            "Salida Blade sin escapar. Usa {{ }} o documenta la expresión en LISTA_BLANCA_SIN_ESCAPAR:\n".implode("\n", $sinPermiso)
        );
    }

    /**
     * DataTables 1.13 mete el valor de una columna como innerHTML. Una
     * columna sin "render" pinta el texto crudo.
     */
    public function test_ninguna_columna_de_datatables_pinta_texto_sin_render(): void
    {
        $this->assertSame(
            [],
            $this->lineasQueCumplen("/\{\s*data:\s*'[a-zA-Z_]+'\s*\}/"),
            'Columna de DataTables sin render: usa { data: \'x\', render: renderTextoSeguro }'
        );
    }

    /** El patrón "data ? data : '<span>—</span>'" devolvía el dato crudo. */
    public function test_ningun_render_devuelve_el_dato_crudo(): void
    {
        $this->assertSame(
            [],
            $this->lineasQueCumplen(
                '/return\s+.*(\?\s*data\s*:|\+\s*data\b|\bdata\s*\+|\+\s*fila\.[a-z_]+|fila\.[a-z_]+\s*\+)/',
                fn ($linea) => (bool) preg_match('/escaparTexto|data-id_|generarAvatar|formatear|recortarHora/', $linea)
            ),
            'Render de DataTables que concatena el dato sin escaparTexto()'
        );
    }

    /**
     * "title" y "html" de SweetAlert2 interpretan HTML: cualquier dato que se
     * concatene ahí tiene que pasar por escaparTexto(). Ya hubo un caso real
     * en el panel del super admin.
     */
    public function test_los_titulos_y_html_de_sweetalert_escapan_lo_que_concatenan(): void
    {
        $this->assertSame(
            [],
            $this->lineasQueCumplen(
                '/^\s*(title|html)\s*:\s*.*\+/',
                fn ($linea) => (bool) preg_match('/escaparTexto\(|Escapado/', $linea)
            ),
            'title:/html: de SweetAlert2 con un dato concatenado sin escaparTexto()'
        );
    }

    /** Una sola función de escape: nada de copias locales que puedan divergir. */
    public function test_la_funcion_de_escape_existe_una_sola_vez(): void
    {
        $this->assertSame([], $this->lineasQueCumplen('/function\s+escaparTexto\s*\(/'), 'Las vistas no deben redefinir escaparTexto()');
        $this->assertSame(1, substr_count(File::get(public_path('js/utilidades.js')), 'function escaparTexto('));
    }

    /**
     * notificarUsuario pinta con Swal.fire(title, html, icon): las dos
     * posiciones interpretan HTML, así que debe escapar el mensaje.
     */
    public function test_notificar_usuario_escapa_su_mensaje(): void
    {
        $utilidades = File::get(public_path('js/utilidades.js'));
        $cuerpo = substr($utilidades, strpos($utilidades, 'async function notificarUsuario'));

        $this->assertStringContainsString('Mensaje = escaparTexto(Mensaje)', $cuerpo);
        $this->assertStringContainsString('escaparTexto(Mensaje[i])', $cuerpo);
    }
}
