<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActivaModulos;
use Tests\TestCase;

/**
 * Frontend del módulo "pagina_publica": la página básica y los candados del
 * panel. PHPUnit no ejecuta JS: revisa el HTML que entrega el servidor y el
 * código de los archivos nuevos (el comportamiento en pantalla se verificó con
 * Chromium, ver el reporte del Bloque 2).
 *
 * Negocio A: CON el módulo. Negocio B: SIN él.
 *
 * ================= MUTACIONES (resultado en el reporte de la tarea) =================
 * P1: la campana de solicitudes renderizada siempre (@if del admin en vez del
 *     módulo): falla test_sin_el_modulo_no_hay_campana_de_solicitudes_ni_su_sondeo.
 * P2: sin el candado de Banners: falla
 *     test_sin_el_modulo_la_pestana_banners_y_la_frase_van_con_candado.
 * Restauradas (cmp igual), pasan.
 */
class PanelPaginaPublicaTest extends TestCase
{
    use ActivaModulos;
    use RefreshDatabase;

    const PAYLOAD = '<img src=x onerror=alert(1)>"\'';

    /** Archivos nuevos del frontend de este módulo. */
    const ARCHIVOS_NUEVOS = [
        'resources/views/publico/pagina-basica.blade.php',
        'resources/views/partials/candado-modulo.blade.php',
        'resources/views/partials/candado-modulo-estilos.blade.php',
        'resources/views/app/configuracion/partials/pagina-publica.blade.php',
    ];

    private int $negocioA;

    private int $negocioB;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = $this->crearNegocio('Spa Completo', 'spa-completo');
        $this->negocioB = $this->crearNegocio('Spa Basico', 'spa-basico');

        $this->activarModulo($this->negocioA, 'pagina_publica');

        config(['plataforma.contacto_whatsapp' => '+57 300 123 4567', 'plataforma.contacto_correo' => '']);
    }

    private function crearNegocio(string $nombre, string $slug): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre, 'slug' => $slug, 'rubro' => 'spa', 'whatsapp_numero' => '573001112233',
            'dias_atencion' => '1,2,3,4,5,6', 'hora_apertura' => '09:00:00', 'hora_cierre' => '19:00:00',
            'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ]);
    }

    private function sesionAdmin(int $tenantId, string $nombreNegocio = 'Spa Basico'): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 999999, 'usuario' => 'admin.test', 'nombre_usuario' => 'Admin Test',
            'tenant_id' => $tenantId, 'id_rol' => 1, 'nombre_negocio_sesion' => $nombreNegocio,
        ];
    }

    private function sesionEmpleado(int $tenantId): array
    {
        return ['id_rol' => 2, 'id_empleado' => 1] + $this->sesionAdmin($tenantId);
    }

    private function vista(int $tenantId, string $url, string $nombreNegocio = 'Spa Basico'): string
    {
        return $this->withSession($this->sesionAdmin($tenantId, $nombreNegocio))->get($url)->assertOk()->getContent();
    }

    /* ================= 1) TARJETA "PÁGINA PÚBLICA" EN CONFIGURACIÓN ================= */

    public function test_la_tarjeta_con_el_modulo_dice_que_la_pagina_esta_completa(): void
    {
        $html = $this->vista($this->negocioA, 'backoffice/configuracion');

        $this->assertStringContainsString('id="tarjeta-pagina-publica"', $html);
        $this->assertStringContainsString('id="texto-pagina-publica-completa"', $html);
        $this->assertStringNotContainsString('id="candado-pagina-publica"', $html);
    }

    public function test_la_tarjeta_sin_el_modulo_muestra_el_candado_con_whatsapp_solo_con_digitos(): void
    {
        $html = $this->vista($this->negocioB, 'backoffice/configuracion', 'Spa Basico');

        $this->assertStringContainsString('id="candado-pagina-publica"', $html);
        $this->assertStringContainsString('Hoy tus clientas ven solo tu nombre, tu horario y tu WhatsApp.', $html);
        $this->assertStringContainsString('Disponible en el plan Pro', $html);
        $this->assertStringNotContainsString('id="texto-pagina-publica-completa"', $html);

        $mensaje = rawurlencode('Hola, soy del spa Spa Basico y quiero el plan Pro (Página pública completa).');
        $this->assertStringContainsString('href="https://wa.me/573001234567?text='.$mensaje.'"', $html);
        $this->assertStringContainsString('href="'.url('backoffice/modulo/pagina_publica').'"', $html);
    }

    public function test_sin_contacto_el_candado_usa_el_texto_generico(): void
    {
        config(['plataforma.contacto_whatsapp' => '', 'plataforma.contacto_correo' => '']);

        $html = $this->vista($this->negocioB, 'backoffice/configuracion');

        $this->assertStringContainsString('candado-generico', $html);
        $this->assertStringContainsString('Comunícate con soporte para activarlo.', $html);
        $this->assertStringNotContainsString('wa.me', $html);
    }

    /* ================= 2) BANNERS Y FRASE ================= */

    public function test_sin_el_modulo_la_pestana_banners_y_la_frase_van_con_candado(): void
    {
        $html = $this->vista($this->negocioB, 'backoffice/configuracion');

        $this->assertStringContainsString('id="candado-banners"', $html);
        $this->assertStringContainsString('id="candado-frase"', $html);
        $this->assertStringNotContainsString('id="tarjeta-frase-destacada"', $html);
        $this->assertStringNotContainsString('id="contenedor-banners"', $html);
        $this->assertStringNotContainsString('id="modal-banner"', $html);
        $this->assertStringNotContainsString('id="btn-nuevo-banner"', $html);

        // Lo que sigue editable: el slug, el WhatsApp, el horario y la política.
        foreach (['id="slug"', 'id="whatsapp_numero"', 'id="hora_apertura"', 'id="politica_cancelacion"', 'id="btn-ver-pagina-publica"'] as $campo) {
            $this->assertStringContainsString($campo, $html);
        }

        $conModulo = $this->vista($this->negocioA, 'backoffice/configuracion');
        $this->assertStringContainsString('id="tarjeta-frase-destacada"', $conModulo);
        $this->assertStringContainsString('id="contenedor-banners"', $conModulo);
        $this->assertStringContainsString('id="modal-banner"', $conModulo);
        $this->assertStringNotContainsString('id="candado-banners"', $conModulo);
    }

    /* ================= 3) CAMPANA DE SOLICITUDES ================= */

    public function test_sin_el_modulo_no_hay_campana_de_solicitudes_ni_su_sondeo(): void
    {
        $this->activarModulo($this->negocioB, 'inventario');

        $sin = $this->vista($this->negocioB, 'backoffice/configuracion');
        $this->assertStringNotContainsString('id="btn-campana-solicitudes"', $sin);
        $this->assertStringNotContainsString('request/reserva/solicitudes-pendientes', $sin);
        $this->assertStringNotContainsString('cargarSolicitudesPendientesCampana', $sin);
        // La campana de stock (otro módulo) sigue.
        $this->assertStringContainsString('id="btn-campana-stock"', $sin);

        $con = $this->vista($this->negocioA, 'backoffice/configuracion');
        $this->assertStringContainsString('id="btn-campana-solicitudes"', $con);
        $this->assertStringContainsString('request/reserva/solicitudes-pendientes', $con);
    }

    /* ================= 4) DESCRIPCIÓN PÚBLICA EN RECURSOS ================= */

    public function test_sin_el_modulo_el_campo_de_descripcion_va_deshabilitado_y_sin_name(): void
    {
        $sin = $this->vista($this->negocioB, 'backoffice/recursos');

        $this->assertMatchesRegularExpression('/<textarea id="descripcion_publica"[^>]*\sdisabled[\s>]/', $sin);
        $this->assertStringNotContainsString('name="descripcion_publica"', $sin);
        $this->assertStringContainsString('id="candado-descripcion-publica"', $sin);
        $this->assertStringContainsString('Disponible en el plan Pro', $sin);

        $con = $this->vista($this->negocioA, 'backoffice/recursos');
        $this->assertStringContainsString('name="descripcion_publica"', $con);
        $this->assertDoesNotMatchRegularExpression('/<textarea id="descripcion_publica"[^>]*\sdisabled/', $con);
        $this->assertStringNotContainsString('id="candado-descripcion-publica"', $con);
    }

    /* ================= 5) ESCAPE DEL NOMBRE DEL NEGOCIO ================= */

    public function test_el_nombre_con_html_y_comillas_sale_escapado_en_la_pagina_basica_y_en_el_panel(): void
    {
        DB::table('negocios')->where('id_negocio', $this->negocioB)->update(['nombre_negocio' => self::PAYLOAD]);

        $basica = $this->get('reservar/spa-basico')->assertOk()->getContent();
        $this->assertStringNotContainsString(self::PAYLOAD, $basica);
        $this->assertStringNotContainsString('<img src=x', $basica);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;&quot;&#039;', $basica);

        $panel = $this->vista($this->negocioB, 'backoffice/configuracion', self::PAYLOAD);
        $this->assertStringNotContainsString('<img src=x', $panel);
        $this->assertStringContainsString('?text='.rawurlencode('Hola, soy del spa '.self::PAYLOAD.' y quiero el plan Pro (Página pública completa).'), $panel);
    }

    /* ================= 6) ROLES ================= */

    public function test_empleado_y_anonimo_no_ven_las_pantallas_del_panel(): void
    {
        foreach (['backoffice/configuracion', 'backoffice/recursos', 'backoffice/modulo/pagina_publica'] as $url) {
            $this->withSession($this->sesionEmpleado($this->negocioB))->get($url)->assertRedirect();
            $this->get($url)->assertRedirect();
        }

        // La pantalla "no disponible" del módulo, para el admin sin él; con él, a Configuración.
        $this->withSession($this->sesionAdmin($this->negocioB))->get('backoffice/modulo/pagina_publica')
            ->assertOk()->assertSee('Página pública completa');
        $this->withSession($this->sesionAdmin($this->negocioA))->get('backoffice/modulo/pagina_publica')
            ->assertRedirect(url('backoffice/configuracion'));
    }

    /* ================= 7) PÁGINA BÁSICA: HORARIO EN LENGUAJE CLARO ================= */

    public function test_la_pagina_basica_dice_el_horario_en_una_frase_y_un_solo_boton(): void
    {
        $html = $this->get('reservar/spa-basico')->assertOk()->getContent();

        $this->assertStringContainsString('Lunes a Sábado, de 9:00 AM a 7:00 PM', $html);
        $this->assertSame(1, substr_count($html, 'class="basica-whatsapp"'));
        $this->assertStringContainsString('<h1 class="basica-nombre" id="nombre-negocio-basica">Spa Basico</h1>', $html);
    }

    /* ================= 8) GUARDIANES DEL CÓDIGO NUEVO ================= */

    public function test_lo_nuevo_no_tiene_hexadecimales(): void
    {
        foreach (self::ARCHIVOS_NUEVOS as $archivo) {
            $codigo = file_get_contents(base_path($archivo));

            $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', $codigo, "$archivo tiene un hexadecimal suelto");
        }

        // Y en los bloques agregados a vistas existentes (estilos de las tarjetas de Configuración).
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', file_get_contents(base_path('resources/views/app/configuracion/partials/correos-clientas.blade.php')));
    }

    public function test_lo_nuevo_no_inserta_html_con_datos(): void
    {
        foreach (self::ARCHIVOS_NUEVOS as $archivo) {
            $codigo = file_get_contents(base_path($archivo));

            $this->assertStringNotContainsString('innerHTML', $codigo, "$archivo usa innerHTML");
            $this->assertStringNotContainsString('.html(', $codigo, "$archivo usa .html()");
            $this->assertStringNotContainsString('{!!', $codigo, "$archivo imprime sin escapar");
        }
    }
}
