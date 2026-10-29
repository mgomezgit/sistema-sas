<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guardián del JS nuevo del libro de pagos (pantalla y tarjeta del dashboard):
 * se pinta con createElement + textContent, nunca con innerHTML / .html() /
 * insertAdjacentHTML / eval, y sin hexadecimales de color en el JS. Así, el
 * nombre del negocio, la referencia y el motivo con un payload de XSS se ven
 * como texto en la tabla, el modal y la tarjeta.
 */
class PagoPlataformaFrontendTest extends TestCase
{
    private function bloque(string $ruta, string $etiqueta): string
    {
        $contenido = file_get_contents(base_path($ruta));
        $patron = '/INICIO '.preg_quote($etiqueta, '/').'.*?FIN '.preg_quote($etiqueta, '/').'/s';
        preg_match_all($patron, $contenido, $m);
        $this->assertNotEmpty($m[0], "No se encontraron los marcadores de '$etiqueta' en $ruta");

        return implode("\n", $m[0]);
    }

    public function test_el_js_del_libro_de_pagos_no_usa_html_crudo_ni_hex(): void
    {
        $js = $this->bloque('resources/views/app/superadmin/libro-pagos.blade.php', 'JS LIBRO PAGOS');

        foreach (['.innerHTML', '.html(', 'insertAdjacentHTML', 'eval('] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $js, "El JS del libro no debe usar $prohibido");
        }

        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}\b/', $js);
        $this->assertStringContainsString('textContent', $js);
    }

    public function test_el_js_de_la_tarjeta_pagos_del_mes_no_usa_html_crudo_ni_hex(): void
    {
        $js = $this->bloque('resources/views/app/superadmin/dashboard.blade.php', 'JS PAGOS DEL MES');

        foreach (['.innerHTML', '.html(', 'insertAdjacentHTML', 'eval('] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $js, "El JS de la tarjeta no debe usar $prohibido");
        }

        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}\b/', $js);
        $this->assertStringContainsString('crearNodo', $js);
    }
}
