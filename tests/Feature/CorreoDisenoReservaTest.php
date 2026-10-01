<?php

namespace Tests\Feature;

use App\Mail\ReservaConfirmada;
use App\Mail\ReservaEstadoActualizado;
use App\Mail\ReservaRecordatorio;
use App\Service\ColorAcento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rediseño de los 3 correos que le llegan al CLIENTE (ReservaConfirmada,
 * ReservaEstadoActualizado, ReservaRecordatorio): acento por negocio
 * calculado en PHP, fondo siempre claro (nunca según modo_tema), y botón a
 * la página pública que se omite entero si el negocio no tiene slug.
 *
 * El escapado de XSS de estos mismos correos con este diseño se prueba en
 * XssHttpTest::test_cada_correo_con_datos_de_usuario_sale_escapado() (se
 * extendió ahí, no se duplica aquí).
 */
class CorreoDisenoReservaTest extends TestCase
{
    use RefreshDatabase;

    private function reserva(array $sobreescribir = []): array
    {
        return array_merge([
            'id_reserva' => 1,
            'nombre_cliente' => 'Cliente de prueba',
            'telefono_cliente' => '3000000000',
            'nombre_recurso' => 'Masaje',
            'nombre_empleado' => 'Empleada',
            'fecha_reserva' => '2026-10-01',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'notas' => null,
        ], $sobreescribir);
    }

    private function negocio(array $sobreescribir = []): array
    {
        return array_merge([
            'nombre_negocio' => 'Negocio de prueba',
            'color_acento' => 'oro_rosa',
            'slug' => 'negocio-de-prueba',
            'politica_cancelacion' => null,
        ], $sobreescribir);
    }

    /* ================= 1) EL HEX ES EL DEL NEGOCIO DE ESA RESERVA ================= */

    public function test_cada_correo_trae_el_hex_de_su_propio_acento_y_no_el_de_otro(): void
    {
        $azul = (new ReservaConfirmada($this->reserva(), $this->negocio(['color_acento' => 'azul'])))->render();
        $rojo = (new ReservaConfirmada($this->reserva(), $this->negocio(['color_acento' => 'rojo'])))->render();

        $this->assertStringContainsString(ColorAcento::hex('azul'), $azul);
        $this->assertStringNotContainsString(ColorAcento::hex('rojo'), $azul);

        $this->assertStringContainsString(ColorAcento::hex('rojo'), $rojo);
        $this->assertStringNotContainsString(ColorAcento::hex('azul'), $rojo);
    }

    public function test_los_tres_correos_usan_el_hex_del_acento_del_negocio(): void
    {
        $negocio = $this->negocio(['color_acento' => 'verde']);
        $hexVerde = ColorAcento::hex('verde');

        $confirmada = (new ReservaConfirmada($this->reserva(), $negocio))->render();
        $estado = (new ReservaEstadoActualizado($this->reserva(), $negocio, 'confirmada'))->render();
        $recordatorio = (new ReservaRecordatorio($this->reserva(), $negocio))->render();

        $this->assertStringContainsString($hexVerde, $confirmada);
        $this->assertStringContainsString($hexVerde, $estado);
        $this->assertStringContainsString($hexVerde, $recordatorio);
    }

    /* ================= 2) EL FONDO NUNCA CAMBIA ================= */

    public function test_el_fondo_del_correo_nunca_cambia_aunque_el_negocio_tenga_modo_tema_oscuro(): void
    {
        DB::table('negocios')->insertGetId([
            'nombre_negocio' => 'Negocio Oscuro',
            'slug' => 'negocio-oscuro',
            'rubro' => 'spa',
            'modo_tema' => 'oscuro',
            'color_acento' => 'rojo',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $negocio = $this->negocio(['color_acento' => 'rojo', 'slug' => 'negocio-oscuro']);

        $html = (new ReservaConfirmada($this->reserva(), $negocio))->render();

        // El fondo de la página del correo y el de la tarjeta blanca son
        // fijos: modo_tema no llega a estas vistas (ni el Mailable ni el
        // Controller se lo piden a la base), así que no puede tocarlos.
        $this->assertStringContainsString('background-color:#f4f4f5', $html);
        $this->assertStringContainsString('background-color:#ffffff', $html);
    }

    /* ================= 3) EL BOTÓN ================= */

    public function test_el_boton_enlaza_a_la_pagina_publica_del_negocio_correcto(): void
    {
        $htmlA = (new ReservaConfirmada($this->reserva(), $this->negocio(['slug' => 'spa-de-a'])))->render();
        $htmlB = (new ReservaConfirmada($this->reserva(), $this->negocio(['slug' => 'spa-de-b'])))->render();

        $this->assertStringContainsString(url('reservar/spa-de-a'), $htmlA);
        $this->assertStringNotContainsString(url('reservar/spa-de-b'), $htmlA);

        $this->assertStringContainsString(url('reservar/spa-de-b'), $htmlB);
        $this->assertStringNotContainsString(url('reservar/spa-de-a'), $htmlB);
    }

    public function test_los_tres_correos_enlazan_al_slug_del_negocio(): void
    {
        $negocio = $this->negocio(['slug' => 'mi-negocio']);
        $urlEsperada = url('reservar/mi-negocio');

        $confirmada = (new ReservaConfirmada($this->reserva(), $negocio))->render();
        $estado = (new ReservaEstadoActualizado($this->reserva(), $negocio, 'cancelada'))->render();
        $recordatorio = (new ReservaRecordatorio($this->reserva(), $negocio))->render();

        $this->assertStringContainsString($urlEsperada, $confirmada);
        $this->assertStringContainsString($urlEsperada, $estado);
        $this->assertStringContainsString($urlEsperada, $recordatorio);
    }

    /**
     * No debería pasar hoy (todo negocio tiene slug desde que existe la
     * página pública), pero si faltara, el correo no puede romperse ni
     * mandar un link vacío.
     */
    public function test_sin_slug_el_correo_se_renderiza_sin_boton_y_sin_romperse(): void
    {
        $html = (new ReservaConfirmada($this->reserva(), $this->negocio(['slug' => null])))->render();

        $this->assertStringNotContainsString('<a href=', $html);
        $this->assertStringContainsString('Cliente de prueba', $html);
    }

    public function test_sin_slug_tambien_en_los_otros_dos_correos(): void
    {
        $negocio = $this->negocio(['slug' => '']);

        $estado = (new ReservaEstadoActualizado($this->reserva(), $negocio, 'confirmada'))->render();
        $recordatorio = (new ReservaRecordatorio($this->reserva(), $negocio))->render();

        $this->assertStringNotContainsString('<a href=', $estado);
        $this->assertStringNotContainsString('<a href=', $recordatorio);
    }
}
