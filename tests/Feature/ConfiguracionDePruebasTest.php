<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guardián de que las pruebas NO dependen del .env de cada PC.
 *
 * El .env de un desarrollador (contacto de la plataforma, clave de OpenAI,
 * límites de IA y chatbot) no debe cambiar el resultado de ninguna prueba ni
 * permitir una llamada real. Por eso phpunit.xml fija esas variables con
 * force="true". Esta prueba falla si alguien quita una de ellas o le cambia el
 * valor, y confirma que en el entorno de pruebas la configuración resultante es
 * la fijada.
 *
 * Toda variable de entorno nueva que afecte una prueba se agrega a phpunit.xml
 * Y a la lista de abajo.
 *
 * ================= MUTACIÓN =================
 * Se quitó PLATAFORMA_CONTACTO_WHATSAPP de phpunit.xml y se corrió con esa
 * variable definida en el entorno del proceso (=111): 135 pruebas, 131 pasan,
 * 4 FALLAN — dos de este archivo (la que lee phpunit.xml y la del contacto) y
 * las dos de "módulo no activo" (IaTest e IaPerfilTest) que esperan el mensaje
 * genérico. Restaurado: 135 pasan.
 *
 * Nota de infraestructura: PHPUnit con force="true" no pisa $_SERVER, donde PHP
 * copia el entorno real, y env() de Laravel lo lee primero. Por eso
 * tests/bootstrap.php copia a $_SERVER lo fijado con force; sin él, una variable
 * del entorno del proceso le ganaba a phpunit.xml.
 */
class ConfiguracionDePruebasTest extends TestCase
{
    /** Variable => valor que fija phpunit.xml (todas con force="true"). */
    const FIJADAS = [
        // Contacto de la plataforma: sin contacto = texto genérico.
        'PLATAFORMA_NOMBRE' => 'la plataforma',
        'PLATAFORMA_CONTACTO_WHATSAPP' => '',
        'PLATAFORMA_CONTACTO_CORREO' => '',
        // Nunca una clave real: una prueba sin fake no puede llamar a la API.
        'OPENAI_API_KEY' => '',
        // Chatbot.
        'CHATBOT_HABILITADO' => 'true',
        'CHATBOT_LIMITE_MENSUAL' => '100',
        'CHATBOT_TOPE_GLOBAL_MENSUAL' => '1500',
        'CHATBOT_LONGITUD_MAXIMA_MENSAJE' => '500',
        'CHATBOT_MAX_TURNOS' => '6',
        'CHATBOT_MAX_MENSAJES_CONVERSACION' => '20',
        'CHATBOT_VIDA_CONVERSACION' => '15',
        'CHATBOT_TOKENS_MAX_SALIDA' => '300',
        'CHATBOT_PROVEEDOR' => 'openai',
        'CHATBOT_MODELO' => 'gpt-6-luna',
        'CHATBOT_ESFUERZO_RAZONAMIENTO' => 'none',
        'CHATBOT_ESPERA_BLOQUEO' => '5',
        'CHATBOT_TIMEOUT' => '20',
        'CHATBOT_MENSAJES_MINUTO_IP' => '6',
        'CHATBOT_MENSAJES_HORA_IP' => '40',
        'CHATBOT_TERMINAR_MINUTO_IP' => '20',
        // Asistente creativo (IA).
        'IA_HABILITADO' => 'true',
        'IA_LIMITE_MENSUAL' => '40',
        'IA_TOPE_GLOBAL_MENSUAL' => '1000',
        'IA_TOKENS_MAX_SALIDA' => '400',
        'IA_PROVEEDOR' => 'openai',
        'IA_MODELO' => 'gpt-6-luna',
        'IA_ESFUERZO_RAZONAMIENTO' => 'none',
        'IA_TIMEOUT' => '30',
        'IA_ESPERA_BLOQUEO' => '5',
        'IA_POR_MINUTO_USUARIO' => '10',
        'IA_POR_HORA_USUARIO' => '100',
    ];

    /** Variables de entorno de phpunit.xml: nombre => [valor, ¿force?]. */
    private function variablesDePhpunit(): array
    {
        $xml = simplexml_load_file(base_path('phpunit.xml'));
        $this->assertNotFalse($xml, 'phpunit.xml debe poder leerse');

        $variables = [];

        foreach ($xml->php->env as $env) {
            $variables[(string) $env['name']] = [
                (string) $env['value'],
                (string) $env['force'] === 'true',
            ];
        }

        return $variables;
    }

    public function test_phpunit_xml_fija_con_force_todas_las_variables_que_pueden_alterar_resultados(): void
    {
        $variables = $this->variablesDePhpunit();

        foreach (self::FIJADAS as $nombre => $valor) {
            $this->assertArrayHasKey($nombre, $variables, "phpunit.xml debe fijar $nombre (si se quita, el .env de cada PC vuelve a afectar las pruebas)");
            $this->assertTrue($variables[$nombre][1], "$nombre debe llevar force=\"true\" en phpunit.xml");
            $this->assertSame($valor, $variables[$nombre][0], "$nombre debe valer '$valor' en phpunit.xml");
        }
    }

    public function test_cada_variable_fijada_en_phpunit_xml_esta_en_la_lista_del_guardian(): void
    {
        // Si alguien agrega una variable con force a phpunit.xml sin ponerla
        // aquí, esta prueba lo avisa: la lista del guardián y el XML van juntos.
        foreach ($this->variablesDePhpunit() as $nombre => [$valor, $force]) {
            if ($force) {
                $this->assertArrayHasKey($nombre, self::FIJADAS, "$nombre está fijada con force en phpunit.xml pero falta en ConfiguracionDePruebasTest::FIJADAS");
            }
        }
    }

    public function test_en_el_entorno_de_pruebas_el_contacto_de_la_plataforma_vale_lo_fijado(): void
    {
        $this->assertSame('la plataforma', config('plataforma.nombre'));
        $this->assertSame('', config('plataforma.contacto_whatsapp'));
        $this->assertSame('', config('plataforma.contacto_correo'));
    }

    public function test_en_el_entorno_de_pruebas_no_hay_clave_de_ia_ni_limites_del_env(): void
    {
        // Sin clave: una llamada real a OpenAI es imposible aunque falte un fake.
        $this->assertSame('', (string) config('ai.providers.openai.key'));

        $this->assertTrue(config('chatbot.habilitado'));
        $this->assertSame(100, config('chatbot.limite_mensual'));
        $this->assertSame(1500, config('chatbot.tope_global_mensual'));
        $this->assertSame(500, config('chatbot.longitud_maxima_mensaje'));
        $this->assertSame(6, config('chatbot.mensajes_por_minuto_ip'));

        $this->assertTrue(config('ia.habilitado'));
        $this->assertSame(40, config('ia.limite_mensual'));
        $this->assertSame(1000, config('ia.tope_global_mensual'));
        $this->assertSame(10, config('ia.generaciones_por_minuto_usuario'));
        $this->assertSame(100, config('ia.generaciones_por_hora_usuario'));
    }
}
