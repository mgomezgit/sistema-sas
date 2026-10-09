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
 * M6 — badgeEstadoReserva() volvió a concatenar "...badge-reserva-' +
 *      estadoReserva + ..." sin pasar por la clase sanitizada: 7 tests, 6
 *      passed, 1 FAILED — test_badge_estado_reserva_existe_una_sola_vez_y_no_concatena_el_atributo_sin_filtrar.
 *      Restaurado: 7 passed.
 * M9 — escaparTexto() sin los .replace() de " y ' (como la versión vieja con
 *      jQuery, que no tocaba comillas): 9 tests, 8 passed, 1 FAILED —
 *      test_escapar_texto_cubre_comillas_dobles_y_simples ("debe reemplazar
 *      /"/g por &quot;"). Restaurado: 9 passed.
 * M10 — data-nombre del panel del super admin sin escaparTexto() (fila
 *       269 de superadmin/negocios): 9 tests, 8 passed, 1 FAILED —
 *       test_los_atributos_armados_a_mano_escapan_su_valor, que nombró
 *       "app/superadmin/negocios.blade.php:269: data-nombre=...". Restaurado:
 *       9 passed.
 * M11 — Se volvió a leer el nombre del negocio con
 *       jQuery(this).data('nombre') en vez de .attr() (superadmin/negocios,
 *       sitio del modal de módulos): 11 tests, 10 passed, 1 FAILED —
 *       test_las_claves_data_de_texto_libre_no_se_leen_con_data, que nombró
 *       "app/superadmin/negocios.blade.php: .data('nombre')". Restaurado:
 *       11 passed.
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

    // notificarUsuario() ya no pasa por SweetAlert: muestra un toast que inserta
    // el texto solo con textContent. Su guardián vive en ToastGuardianTest.

    /**
     * badgeEstadoReserva() es una sola copia (antes había 4 idénticas en
     * reservas/listado, mis-citas, reportes/ventas e historial), y no debe
     * concatenar el valor crudo dentro de class="...": un nombre de clase no
     * debe depender de texto libre aunque esté escapado. La clase CSS solo
     * puede ser un valor conocido fijo ("clase" en el código); el valor libre
     * solo puede ir como contenido de texto, ahí sí detrás de escaparTexto().
     */
    public function test_badge_estado_reserva_existe_una_sola_vez_y_no_concatena_el_atributo_sin_filtrar(): void
    {
        $this->assertSame(
            [],
            $this->lineasQueCumplen('/function\s+badgeEstadoReserva\s*\(/'),
            'Las vistas no deben redefinir badgeEstadoReserva(): vive en public/js/utilidades.js'
        );

        $utilidades = File::get(public_path('js/utilidades.js'));
        $this->assertSame(1, substr_count($utilidades, 'function badgeEstadoReserva('));

        $cuerpo = substr($utilidades, strpos($utilidades, 'function badgeEstadoReserva('));
        $cuerpo = substr($cuerpo, 0, strpos($cuerpo, "\n}") + 2);

        $this->assertStringNotContainsString("badge-reserva-' + estadoReserva", $cuerpo);
        $this->assertStringContainsString("badge-reserva-' + clase", $cuerpo);
        $this->assertStringContainsString('escaparTexto(estadoReserva)', $cuerpo);
    }

    /**
     * escaparTexto() se usa también dentro del valor de atributos armados a
     * mano (data-nombre="..."), así que tiene que escapar las dos comillas
     * además de & < >. La versión anterior (jQuery("<div>").text(x).html())
     * no las tocaba, y " onmouseover="alert(1)  se salía del atributo en el
     * panel del super admin (verificado con Chromium real).
     *
     * PHPUnit no ejecuta JS: se revisa el cuerpo real de la función. La
     * ejecución real con payloads se verifica con navegador (ver reporte).
     */
    public function test_escapar_texto_cubre_comillas_dobles_y_simples(): void
    {
        $utilidades = File::get(public_path('js/utilidades.js'));
        $cuerpo = substr($utilidades, strpos($utilidades, 'function escaparTexto('));
        $cuerpo = substr($cuerpo, 0, strpos($cuerpo, "\n}") + 2);

        foreach (['/&/g' => '&amp;', '/</g' => '&lt;', '/>/g' => '&gt;', '/"/g' => '&quot;', "/'/g" => '&#39;'] as $patron => $entidad) {
            $this->assertMatchesRegularExpression(
                '#\.replace\(\s*'.preg_quote($patron, '#').'\s*,\s*"'.preg_quote($entidad, '#').'"\s*\)#',
                $cuerpo,
                "escaparTexto() debe reemplazar $patron por $entidad"
            );
        }

        // & primero: si no, el & de "&lt;" se volvería a escapar a "&amp;lt;".
        $this->assertLessThan(
            strpos($cuerpo, '/</g'),
            strpos($cuerpo, '/&/g'),
            'escaparTexto() debe escapar & antes que el resto'
        );
    }

    /**
     * Atributos armados a mano con concatenación: attr="' + valor + '". El
     * valor tiene que pasar por escaparTexto(), salvo los que se sabe que
     * nunca son texto escrito por una persona (ids numéricos de la base,
     * vocabularios fijos). Cada excepción lleva su porqué en la lista.
     */
    const ATRIBUTOS_CONCATENADOS_PERMITIDOS = [
        // Ids enteros autoincrementales de la base, nunca texto de usuario.
        '/^data-id_[a-z_]+$/' => '/^(data|fila\.id_[a-z_]+|reserva\.id_reserva|cita\.id_reserva|solicitud\.id_reserva)$/',
        // Columnas numéricas de comisiones_tarifas (decimal y 0/1).
        '/^data-porcentaje$/' => '/^fila\.porcentaje_comision$/',
        '/^data-estado$/' => '/^fila\.estado$/',
        // UrlGlobal es la base de la app; definicion.destino sale del catálogo
        // fijo de la guía de inicio (layout/backoffice, "destino: 'backoffice/...'").
        '/^href$/' => '/^UrlGlobal( \+ definicion\.destino)?$/',
        // Clases de ícono literales de la guía de inicio ('bi ' + icono fijo).
        '/^class$/' => "/^(clasesIcono|\\(resultado\\.exito \\? 'fila-exito' : 'fila-error'\\))$/",
        // Índice entero del carrusel de banners de la página pública.
        '/^data-indice$/' => '/^indice$/',
    ];

    public function test_los_atributos_armados_a_mano_escapan_su_valor(): void
    {
        $sinEscapar = [];

        foreach ($this->vistas() as $ruta => $contenido) {
            foreach (preg_split('/\R/', $contenido) as $numero => $linea) {
                // (?<!\[): jQuery('[value="' + x + '"]') es un selector, no HTML.
                preg_match_all('/(?<![\[\w-])([a-zA-Z_-]+)="\'\s*\+\s*(.+?)\s*\+\s*\'/', $linea, $coincidencias, PREG_SET_ORDER);

                foreach ($coincidencias as [, $atributo, $valor]) {
                    if (str_starts_with($valor, 'escaparTexto(')) {
                        continue;
                    }

                    $permitido = false;
                    foreach (self::ATRIBUTOS_CONCATENADOS_PERMITIDOS as $patronAtributo => $patronValor) {
                        if (preg_match($patronAtributo, $atributo) && preg_match($patronValor, $valor)) {
                            $permitido = true;
                            break;
                        }
                    }

                    if (! $permitido) {
                        $sinEscapar[] = $ruta.':'.($numero + 1).': '.$atributo.'="\' + '.$valor;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $sinEscapar,
            "Atributo armado a mano sin escaparTexto(). Escápalo, o si de verdad nunca es texto de usuario, documéntalo en ATRIBUTOS_CONCATENADOS_PERMITIDOS:\n".implode("\n", $sinEscapar)
        );
    }

    /**
     * Claves de data-* que llevan texto escrito por una persona (un nombre,
     * nunca un id ni un vocabulario fijo). jQuery .data() convierte un valor
     * con forma de número ("123") a Number y uno con forma de JSON
     * ('{"a":1}') a objeto: el aviso de "¿Suspender...?" o el de "Marcar como
     * pagado" terminaba mostrando "[object Object]" en vez del nombre real.
     * Por eso estas claves se leen con .attr('data-clave'), que siempre
     * devuelve el string tal cual quedó escrito en el HTML.
     */
    const CLAVES_DATA_DE_TEXTO_LIBRE = ['nombre', 'nombre_empleado'];

    public function test_las_claves_data_de_texto_libre_no_se_leen_con_data(): void
    {
        $encontradas = [];

        foreach ($this->vistas() as $ruta => $contenido) {
            foreach (self::CLAVES_DATA_DE_TEXTO_LIBRE as $clave) {
                if (preg_match('/\.data\(\s*[\'"]'.preg_quote($clave, '/').'[\'"]\s*\)/', $contenido)) {
                    $encontradas[] = "$ruta: .data('$clave')";
                }
            }
        }

        $this->assertSame(
            [],
            $encontradas,
            "Lee estas claves con .attr('data-clave'), no con .data(): un valor numérico o con forma de JSON se convertiría solo:\n".implode("\n", $encontradas)
        );
    }

    /**
     * escaparTexto() tiene que convertir su entrada a texto ANTES de escapar.
     * Sin String(texto), pasarle un número rompería con "texto.replace is
     * not a function" (los números no tienen .replace()). null/undefined
     * deben dar cadena vacía, no "null"/"undefined" literales.
     */
    public function test_escapar_texto_convierte_su_entrada_a_string_de_forma_segura(): void
    {
        $utilidades = File::get(public_path('js/utilidades.js'));
        $cuerpo = substr($utilidades, strpos($utilidades, 'function escaparTexto('));
        $cuerpo = substr($cuerpo, 0, strpos($cuerpo, "\n}") + 2);

        $this->assertStringContainsString(
            'texto === null || texto === undefined',
            $cuerpo,
            'escaparTexto() debe devolver cadena vacía para null/undefined'
        );
        $this->assertStringContainsString(
            'String(texto)',
            $cuerpo,
            'escaparTexto() debe convertir su entrada con String() antes de escapar (para que un número no rompa con .replace is not a function)'
        );
    }
}
