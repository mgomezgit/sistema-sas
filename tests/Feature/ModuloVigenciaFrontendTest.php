<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guardián del JS nuevo de vigencia de módulos (panel del super admin y
 * dashboard): se pinta con createElement + textContent, nunca con innerHTML /
 * .html() / insertAdjacentHTML / eval, y no lleva hexadecimales (los colores
 * viven en variables de tema). Así, un nombre de negocio o una nota con un
 * payload de XSS se ven como texto, no como HTML.
 */
class ModuloVigenciaFrontendTest extends TestCase
{
    /** Devuelve los trozos del archivo entre marcadores INICIO/FIN dados. */
    private function bloques(string $ruta, string $etiqueta): string
    {
        $contenido = file_get_contents(base_path($ruta));
        $patron = '/INICIO '.preg_quote($etiqueta, '/').'.*?FIN '.preg_quote($etiqueta, '/').'/s';

        preg_match_all($patron, $contenido, $m);

        $this->assertNotEmpty($m[0], "No se encontraron los marcadores de '$etiqueta' en $ruta");

        return implode("\n", $m[0]);
    }

    public function test_el_js_de_vigencia_del_panel_no_usa_html_crudo_ni_hex(): void
    {
        $js = $this->bloques('resources/views/app/superadmin/negocios.blade.php', 'JS VIGENCIA MODULOS');

        foreach (['.innerHTML', '.html(', 'insertAdjacentHTML', 'eval('] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $js, "El JS de vigencia no debe usar $prohibido");
        }

        // Sin hexadecimales de color sueltos.
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}\b/', $js);

        // Debe construir con createElement/textContent.
        $this->assertStringContainsString('crearElemento', $js);
    }

    public function test_el_js_de_la_tarjeta_del_dashboard_no_usa_html_crudo_ni_hex(): void
    {
        $js = $this->bloques('resources/views/app/superadmin/dashboard.blade.php', 'JS MODULOS POR VENCER');

        foreach (['.innerHTML', '.html(', 'insertAdjacentHTML', 'eval('] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $js, "El JS del dashboard no debe usar $prohibido");
        }

        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}\b/', $js);
        $this->assertStringContainsString('crearNodo', $js);
    }
}
