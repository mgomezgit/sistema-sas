<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Service\SvcChatbot;
use App\Service\SvcModulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Frase destacada (una por negocio, franja de la página pública) y el
 * componente "Generar con IA" de la pestaña Banners.
 *
 * Todo recorre el camino real (HTTP con withSession). El reloj va congelado
 * (Carbon::setTestNow) en un día conocido: la vigencia depende de "hoy".
 *
 * ================= PRUEBAS DE MUTACIÓN =================
 *
 * Cada mutación se aplicó con un script que reemplaza el texto exacto, corre
 * "phpunit --filter=FraseDestacadaTest" (o la suite indicada), anota el
 * resultado y restaura el archivo; después se confirmó el verde.
 *
 *   1. guardar-frase toma el tenant del cuerpo ($datos['tenant_id'] ??
 *      session('tenant_id')). Resultado: 46 tests, 45 passed, 1 FAILED —
 *      test_el_admin_de_a_no_puede_guardar_la_frase_de_b_ni_mandando_su_id.
 *      1b. Lo mismo en quitar-frase: 1 FAILED —
 *      test_el_admin_de_a_no_puede_quitar_la_frase_de_b_ni_mandando_su_id.
 *   2. Sin chequeo de vigencia en SvcPaginaPublica (devuelve la frase aunque
 *      haya vencido). Resultado: 44 passed, 2 FAILED —
 *      test_el_dia_de_fecha_hasta_se_ve_y_el_siguiente_no y
 *      test_sin_fecha_siempre_se_ve_y_sin_frase_nunca.
 *      2b. Vigencia exclusiva (> en vez de >=): 44 passed, 2 FAILED — la del
 *      día de fecha_hasta y test_el_panel_dice_si_la_frase_se_ve_hoy_....
 *   3. La frase se pinta con {!! !!} en publico/pagina.blade.php (suite
 *      FraseDestacadaTest + XssGuardianTest). Resultado: 56 tests, 53
 *      passed, 3 FAILED — test_una_frase_con_payload_sale_escapada_en_la_pagina_publica,
 *      test_la_vista_publica_pinta_la_frase_solo_con_llaves_dobles y
 *      XssGuardianTest::test_no_hay_salida_blade_sin_escapar_fuera_de_la_lista_blanca.
 *   4. innerHTML en el componente de IA. Resultado: 45 passed, 1 FAILED —
 *      test_el_js_del_componente_no_pinta_html_ni_evalua_codigo.
 *   5. El componente se renderiza siempre (iaActiva = true ||...).
 *      Resultado: 45 passed, 1 FAILED —
 *      test_el_componente_de_ia_solo_se_renderiza_con_el_modulo_activo.
 *   6. La franja usa un hexadecimal suelto. Resultado: 45 passed, 1 FAILED —
 *      test_el_componente_no_tiene_hexadecimales_ni_colores_sueltos.
 *   7. La frase entra al contexto del chatbot (FraseDestacadaTest +
 *      ChatbotTest). Resultado: 98 tests, 96 passed, 2 FAILED —
 *      ChatbotTest::test_el_contexto_solo_lleva_campos_de_la_lista_blanca y
 *      test_la_frase_no_entra_al_contexto_del_chatbot.
 *
 * Tras restaurar cada una: 46 passed.
 */
class FraseDestacadaTest extends TestCase
{
    use RefreshDatabase;

    const PAYLOAD = '<img src=x onerror=alert(1)> "comillas" y \'apostrofes\' & más';

    private int $negocioA;

    private int $negocioB;

    protected function setUp(): void
    {
        parent::setUp();

        // Miércoles 14 de octubre de 2026, 10:00.
        Carbon::setTestNow(Carbon::create(2026, 10, 14, 10, 0, 0));

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = $this->crearNegocio('Spa Aurora', 'spa-aurora');
        $this->negocioB = $this->crearNegocio('Casa Brisa', 'casa-brisa');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre, string $slug): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'slug' => $slug,
            'rubro' => 'spa',
            'telefono_contacto' => '6011234567',
            'dias_atencion' => '1,2,3,4,5,6',
            'hora_apertura' => '09:00:00',
            'hora_cierre' => '18:00:00',
            'usuario_registra' => 'test',
            'fecha_registro' => Carbon::now()->format('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function sesionAdmin(int $tenantId): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 1,
            'usuario' => 'admin.test',
            'nombre_usuario' => 'Admin Test',
            'tenant_id' => $tenantId,
            'id_rol' => 1,
        ];
    }

    private function sesionEmpleado(): array
    {
        return ['id_usuario' => 2, 'id_rol' => 2] + $this->sesionAdmin($this->negocioA);
    }

    private function sesionSuperAdmin(): array
    {
        return ['id_usuario' => 3, 'tenant_id' => null, 'id_rol' => 3] + $this->sesionAdmin($this->negocioA);
    }

    private function guardar(array $cuerpo, ?array $sesion = null)
    {
        return $this->withSession($sesion ?? $this->sesionAdmin($this->negocioA))->postJson('request/negocio/guardar-frase', $cuerpo);
    }

    private function quitar(array $cuerpo = [], ?array $sesion = null)
    {
        return $this->withSession($sesion ?? $this->sesionAdmin($this->negocioA))->postJson('request/negocio/quitar-frase', $cuerpo);
    }

    private function frase(int $tenantId): array
    {
        $f = DB::table('negocios')->where('id_negocio', $tenantId)->first();

        return ['frase_destacada' => $f->frase_destacada, 'frase_hasta' => $f->frase_hasta];
    }

    private function fijarFrase(int $tenantId, ?string $texto, ?string $hasta = null): void
    {
        DB::table('negocios')->where('id_negocio', $tenantId)->update(['frase_destacada' => $texto, 'frase_hasta' => $hasta]);
    }

    private function paginaPublica(string $slug = 'spa-aurora'): string
    {
        return $this->get('reservar/'.$slug)->assertOk()->getContent();
    }

    /* ================= GUARDAR Y LEER ================= */

    public function test_guardar_y_leer_la_frase_con_su_fecha(): void
    {
        $this->guardar(['frase' => '  Hoy   2x1 en masajes  ', 'fecha_hasta' => '2026-10-20'])
            ->assertOk()
            ->assertJsonPath('error', 0)
            ->assertJsonPath('data.negocio.frase_destacada', 'Hoy 2x1 en masajes')
            ->assertJsonPath('data.negocio.frase_hasta', '2026-10-20')
            ->assertJsonPath('data.negocio.frase_vigente', true);

        $this->assertSame(['frase_destacada' => 'Hoy 2x1 en masajes', 'frase_hasta' => '2026-10-20'], $this->frase($this->negocioA));

        $this->withSession($this->sesionAdmin($this->negocioA))->getJson('request/negocio/configuracion')
            ->assertJsonPath('data.negocio.frase_destacada', 'Hoy 2x1 en masajes')
            ->assertJsonPath('data.negocio.frase_hasta', '2026-10-20');
    }

    public function test_los_negocios_existentes_quedan_sin_frase(): void
    {
        $this->assertSame(['frase_destacada' => null, 'frase_hasta' => null], $this->frase($this->negocioA));
        $this->assertStringNotContainsString('id="franja-frase-destacada"', $this->paginaPublica());
    }

    public function test_el_maximo_es_140_y_141_se_rechaza(): void
    {
        $this->guardar(['frase' => str_repeat('a', 140)])->assertJsonPath('error', 0);
        $this->assertSame(140, mb_strlen($this->frase($this->negocioA)['frase_destacada']));

        $this->guardar(['frase' => str_repeat('b', 141)])->assertJsonPath('error', 1);
        $this->assertSame(str_repeat('a', 140), $this->frase($this->negocioA)['frase_destacada'], 'La rechazada no pisa la guardada');
    }

    public function test_el_largo_se_mide_tras_recortar_y_colapsar_espacios(): void
    {
        // 138 letras + espacios repetidos que, colapsados, no pasan de 140.
        $texto = '   '.str_repeat('a', 69).'     '.str_repeat('b', 68).'   ';
        $this->guardar(['frase' => $texto])->assertJsonPath('error', 0);

        $this->assertSame(str_repeat('a', 69).' '.str_repeat('b', 68), $this->frase($this->negocioA)['frase_destacada']);
    }

    public static function frasesInvalidas(): array
    {
        return [
            'vacia' => [''],
            'solo espacios' => ["   \t\n  "],
            'con byte nulo' => ["Hola\x00mundo"],
            'con caracter de control' => ["Hola\x07mundo"],
            'con caracter invisible' => ["Hola\u{200B}mundo"],
            'no es texto' => [['a']],
            'nula' => [null],
        ];
    }

    #[DataProvider('frasesInvalidas')]
    public function test_frases_invalidas_se_rechazan_sin_tocar_la_guardada(mixed $frase): void
    {
        $this->fijarFrase($this->negocioA, 'La de siempre', '2026-12-01');

        $this->guardar(['frase' => $frase])->assertOk()->assertJsonPath('error', 1);

        $this->assertSame(['frase_destacada' => 'La de siempre', 'frase_hasta' => '2026-12-01'], $this->frase($this->negocioA));
    }

    public function test_sin_el_campo_frase_se_rechaza(): void
    {
        $this->guardar([])->assertJsonPath('error', 1);
        $this->assertSame(['frase_destacada' => null, 'frase_hasta' => null], $this->frase($this->negocioA));
    }

    public static function fechasInvalidas(): array
    {
        return [
            'ayer' => ['2026-10-13'],
            'pasado lejano' => ['2020-01-01'],
            'dia inexistente' => ['2026-02-30'],
            'mes 13' => ['2026-13-01'],
            'texto' => ['pronto'],
            'otro formato' => ['14/10/2026'],
            'con hora' => ['2026-10-20 10:00:00'],
            'no es texto' => [['2026-10-20']],
        ];
    }

    #[DataProvider('fechasInvalidas')]
    public function test_fechas_invalidas_o_pasadas_se_rechazan(mixed $fecha): void
    {
        $this->guardar(['frase' => 'Hola', 'fecha_hasta' => $fecha])->assertOk()->assertJsonPath('error', 1);

        $this->assertSame(['frase_destacada' => null, 'frase_hasta' => null], $this->frase($this->negocioA));
    }

    public function test_la_fecha_de_hoy_es_valida_y_la_vacia_significa_sin_vencimiento(): void
    {
        $this->guardar(['frase' => 'Solo hoy', 'fecha_hasta' => '2026-10-14'])->assertJsonPath('error', 0);
        $this->assertSame('2026-10-14', $this->frase($this->negocioA)['frase_hasta']);

        $this->guardar(['frase' => 'Sin fin', 'fecha_hasta' => ''])->assertJsonPath('error', 0)->assertJsonPath('data.negocio.frase_hasta', null);
        $this->guardar(['frase' => 'Sin fin otra vez'])->assertJsonPath('error', 0);
        $this->assertNull($this->frase($this->negocioA)['frase_hasta']);
    }

    public function test_quitar_borra_el_texto_y_la_fecha(): void
    {
        $this->fijarFrase($this->negocioA, 'Borrame', '2026-12-31');

        $this->quitar()->assertOk()->assertJsonPath('error', 0)->assertJsonPath('data.negocio.frase_destacada', null);

        $this->assertSame(['frase_destacada' => null, 'frase_hasta' => null], $this->frase($this->negocioA));
        $this->assertStringNotContainsString('id="franja-frase-destacada"', $this->paginaPublica());
    }

    public function test_el_texto_se_guarda_tal_cual_sin_escapar(): void
    {
        $this->guardar(['frase' => self::PAYLOAD])->assertJsonPath('error', 0);

        $this->assertSame(self::PAYLOAD, $this->frase($this->negocioA)['frase_destacada'], 'El escape se hace al pintar, nunca al guardar');
    }

    /* ================= VIGENCIA (reloj congelado) ================= */

    public function test_el_dia_de_fecha_hasta_se_ve_y_el_siguiente_no(): void
    {
        $this->fijarFrase($this->negocioA, 'Hasta el 14', '2026-10-14');

        // El mismo día (inclusive), a cualquier hora.
        Carbon::setTestNow(Carbon::create(2026, 10, 14, 23, 59, 0));
        $this->assertStringContainsString('Hasta el 14', $this->paginaPublica());
        $this->getJson('publico/spa-aurora/informacion')->assertJsonPath('data.negocio.frase_destacada', 'Hasta el 14');

        // Al día siguiente desaparece sola.
        Carbon::setTestNow(Carbon::create(2026, 10, 15, 0, 0, 0));
        $html = $this->paginaPublica();
        $this->assertStringNotContainsString('Hasta el 14', $html);
        $this->assertStringNotContainsString('id="franja-frase-destacada"', $html);
        $this->getJson('publico/spa-aurora/informacion')->assertJsonPath('data.negocio.frase_destacada', null);
    }

    public function test_sin_fecha_siempre_se_ve_y_sin_frase_nunca(): void
    {
        $this->fijarFrase($this->negocioA, 'Siempre');

        foreach ([Carbon::create(2026, 10, 14), Carbon::create(2030, 1, 1), Carbon::create(2040, 6, 6)] as $dia) {
            Carbon::setTestNow($dia);
            $this->assertStringContainsString('Siempre', $this->paginaPublica());
        }

        $this->fijarFrase($this->negocioA, null);
        $this->assertStringNotContainsString('id="franja-frase-destacada"', $this->paginaPublica());

        // Una fecha suelta sin texto tampoco pinta nada.
        $this->fijarFrase($this->negocioA, null, '2099-01-01');
        $this->assertStringNotContainsString('id="franja-frase-destacada"', $this->paginaPublica());
        $this->fijarFrase($this->negocioA, '   ', null);
        $this->assertStringNotContainsString('id="franja-frase-destacada"', $this->paginaPublica());
    }

    public function test_el_panel_dice_si_la_frase_se_ve_hoy_con_la_misma_regla_que_la_pagina(): void
    {
        $this->fijarFrase($this->negocioA, 'Vigente hoy', '2026-10-14');
        $this->withSession($this->sesionAdmin($this->negocioA))->getJson('request/negocio/configuracion')
            ->assertJsonPath('data.negocio.frase_vigente', true);

        Carbon::setTestNow(Carbon::create(2026, 10, 15, 9, 0, 0));
        $this->withSession($this->sesionAdmin($this->negocioA))->getJson('request/negocio/configuracion')
            ->assertJsonPath('data.negocio.frase_vigente', false)
            ->assertJsonPath('data.negocio.frase_hasta', '2026-10-14');
    }

    public function test_la_franja_va_entre_el_encabezado_y_el_hero_y_sin_frase_no_deja_hueco(): void
    {
        $this->fijarFrase($this->negocioA, 'Una frase');
        $html = $this->paginaPublica();

        $posNav = strpos($html, '</nav>');
        $posFranja = strpos($html, 'franja-frase-destacada');
        $posHero = strpos($html, 'class="hero"');

        $this->assertNotFalse($posFranja);
        $this->assertTrue($posNav < $posFranja && $posFranja < $posHero, 'La franja va entre la barra superior y el hero');

        $this->fijarFrase($this->negocioA, null);
        $sin = $this->paginaPublica();
        $this->assertStringNotContainsString('franja-frase-destacada', $sin);
        $this->assertStringNotContainsString('<div class="franja-frase"', $sin);
    }

    /* ================= PERMISOS ================= */

    public function test_anonimo_empleado_y_super_admin_son_rechazados_en_guardar_y_quitar(): void
    {
        $this->fijarFrase($this->negocioA, 'Intocable', '2026-12-31');
        $antes = $this->frase($this->negocioA);

        $this->postJson('request/negocio/guardar-frase', ['frase' => 'Hackeada'])->assertJsonPath('error', 1);
        $this->postJson('request/negocio/quitar-frase')->assertJsonPath('error', 1);

        foreach ([$this->sesionEmpleado(), $this->sesionSuperAdmin()] as $sesion) {
            $this->guardar(['frase' => 'Hackeada', 'tenant_id' => $this->negocioA], $sesion)->assertJsonPath('error', 1);
            $this->quitar(['tenant_id' => $this->negocioA], $sesion)->assertJsonPath('error', 1);
        }

        $this->assertSame($antes, $this->frase($this->negocioA));
    }

    public function test_el_admin_la_gestiona_aunque_el_modulo_ia_este_apagado(): void
    {
        $this->assertFalse((new SvcModulo)->estaActivo($this->negocioA, 'ia'));

        $this->guardar(['frase' => 'Sin modulo ia'])->assertJsonPath('error', 0);
        $this->assertSame('Sin modulo ia', $this->frase($this->negocioA)['frase_destacada']);
        $this->quitar()->assertJsonPath('error', 0);
        $this->assertNull($this->frase($this->negocioA)['frase_destacada']);
    }

    public function test_guardian_de_rutas_de_la_frase(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_contains($r->uri(), 'negocio/guardar-frase') || str_contains($r->uri(), 'negocio/quitar-frase'))
            ->mapWithKeys(fn ($r) => [implode('|', $r->methods()).' '.$r->uri() => array_diff($r->gatherMiddleware(), $r->excludedMiddleware())]);

        $this->assertEqualsCanonicalizing(['POST request/negocio/guardar-frase', 'POST request/negocio/quitar-frase'], $rutas->keys()->all());

        foreach ($rutas as $ruta => $middleware) {
            $this->assertContains('sesion.activa', $middleware, $ruta);
            $this->assertContains('restringir.empleado', $middleware, $ruta);
        }
    }

    /* ================= AISLAMIENTO ENTRE NEGOCIOS ================= */

    /**
     * El admin de A manda el tenant_id y el id_negocio de B en el cuerpo (y en
     * la query): se ignoran, manda la sesión. Se leen los dos negocios antes y
     * después.
     */
    public function test_el_admin_de_a_no_puede_guardar_la_frase_de_b_ni_mandando_su_id(): void
    {
        $this->fijarFrase($this->negocioB, 'Frase de B', '2026-12-31');
        $antesB = $this->frase($this->negocioB);

        $this->guardar([
            'frase' => 'Frase de A',
            'tenant_id' => $this->negocioB,
            'id_negocio' => $this->negocioB,
        ])->assertJsonPath('error', 0);

        $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/negocio/guardar-frase?tenant_id='.$this->negocioB.'&id_negocio='.$this->negocioB, ['frase' => 'Otra de A'])
            ->assertJsonPath('error', 0);

        $this->assertSame($antesB, $this->frase($this->negocioB), 'B quedó intacto');
        $this->assertSame('Otra de A', $this->frase($this->negocioA)['frase_destacada']);
    }

    public function test_el_admin_de_a_no_puede_quitar_la_frase_de_b_ni_mandando_su_id(): void
    {
        $this->fijarFrase($this->negocioA, 'Frase de A', '2026-12-31');
        $this->fijarFrase($this->negocioB, 'Frase de B', '2026-12-31');
        $antesB = $this->frase($this->negocioB);

        $this->quitar(['tenant_id' => $this->negocioB, 'id_negocio' => $this->negocioB])->assertJsonPath('error', 0);

        $this->assertSame($antesB, $this->frase($this->negocioB), 'B quedó intacto');
        $this->assertSame(['frase_destacada' => null, 'frase_hasta' => null], $this->frase($this->negocioA));
    }

    public function test_la_respuesta_solo_trae_la_frase_del_propio_negocio(): void
    {
        $this->fijarFrase($this->negocioB, 'Frase de B');

        $crudo = $this->guardar(['frase' => 'Frase de A'])->getContent();
        $this->assertStringNotContainsString('Frase de B', $crudo);
        $this->assertStringNotContainsString('Frase de B', $this->withSession($this->sesionAdmin($this->negocioA))->getJson('request/negocio/configuracion')->getContent());
    }

    public function test_la_pagina_publica_de_a_nunca_muestra_la_frase_de_b(): void
    {
        $this->fijarFrase($this->negocioB, 'Frase exclusiva de B');

        $this->assertStringNotContainsString('Frase exclusiva de B', $this->paginaPublica('spa-aurora'));
        $this->getJson('publico/spa-aurora/informacion')->assertJsonPath('data.negocio.frase_destacada', null);

        $this->fijarFrase($this->negocioA, 'Frase de A');
        $this->assertStringContainsString('Frase de A', $this->paginaPublica('spa-aurora'));
        $this->assertStringNotContainsString('Frase exclusiva de B', $this->paginaPublica('spa-aurora'));
        $this->assertStringContainsString('Frase exclusiva de B', $this->paginaPublica('casa-brisa'));
    }

    public function test_la_fecha_de_fin_nunca_sale_en_la_informacion_publica(): void
    {
        $this->fijarFrase($this->negocioA, 'Con fecha', '2026-12-31');

        $crudo = $this->getJson('publico/spa-aurora/informacion')->getContent();

        $this->assertStringNotContainsString('frase_hasta', $crudo);
        $this->assertStringNotContainsString('2026-12-31', $crudo);
    }

    /* ================= EL CHATBOT NO CAMBIA ================= */

    public function test_la_frase_no_entra_al_contexto_del_chatbot(): void
    {
        $this->fijarFrase($this->negocioA, 'FRASE-NO-DEBE-ENTRAR-AL-CHATBOT', '2026-12-31');

        $contexto = (new SvcChatbot)->construirContexto($this->negocioA);

        $this->assertSame(['nombre_negocio', 'horario', 'servicios', 'equipo', 'politica_cancelacion', 'whatsapp'], array_keys($contexto));
        $this->assertStringNotContainsString('FRASE-NO-DEBE', json_encode($contexto));
    }

    /* ================= XSS ================= */

    public function test_una_frase_con_payload_sale_escapada_en_la_pagina_publica(): void
    {
        $this->guardar(['frase' => self::PAYLOAD])->assertJsonPath('error', 0);

        $html = $this->paginaPublica();

        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringContainsString('&quot;comillas&quot;', $html);
    }

    public function test_la_vista_publica_pinta_la_frase_solo_con_llaves_dobles(): void
    {
        $vista = File::get(resource_path('views/publico/pagina.blade.php'));

        $this->assertStringContainsString("{{ \$negocio['frase_destacada'] }}", $vista);
        $this->assertStringNotContainsString("{!! \$negocio['frase_destacada']", $vista);
    }

    public function test_la_frase_viaja_al_panel_como_json_y_se_pinta_con_val_y_text(): void
    {
        $vista = File::get(resource_path('views/app/configuracion/index.blade.php'));

        preg_match('/function pintarFrase\(.*?\n        \}\n/s', $vista, $m);
        $this->assertNotEmpty($m, 'No se encontró pintarFrase()');
        $this->assertStringNotContainsString('.html(', $m[0]);
        $this->assertStringNotContainsString('innerHTML', $m[0]);
        $this->assertStringContainsString(".val(texto)", $m[0]);
    }

    /* ================= COMPONENTE "GENERAR CON IA" ================= */

    private function vistaConfiguracion(int $tenantId): string
    {
        return $this->withSession($this->sesionAdmin($tenantId))->get('backoffice/configuracion')->assertOk()->getContent();
    }

    public function test_el_componente_de_ia_solo_se_renderiza_con_el_modulo_activo(): void
    {
        $sin = $this->vistaConfiguracion($this->negocioA);

        foreach (['ia-generar', 'IaGenerar', 'request/ia/generar', 'Generar con IA', 'ia-btn-abrir'] as $rastro) {
            $this->assertStringNotContainsString($rastro, $sin, 'Sin el módulo no puede haber rastro de "'.$rastro.'"');
        }

        (new SvcModulo)->activarModulo($this->negocioA, 'ia', 'test');

        $con = $this->vistaConfiguracion($this->negocioA);
        $this->assertSame(2, substr_count($con, 'class="ia-generar"'), 'Dos ubicaciones: el banner y la frase');
        $this->assertSame(1, substr_count($con, 'var IaGenerar'), 'El código del componente va una sola vez');
        $this->assertStringContainsString('data-ia-tipo="banner"', $con);
        $this->assertStringContainsString('data-ia-tipo="frase"', $con);

        // Con el módulo activo en A, B (sin módulo) sigue sin nada.
        $this->assertStringNotContainsString('ia-generar', $this->vistaConfiguracion($this->negocioB));
    }

    public function test_la_tarjeta_de_la_frase_existe_con_y_sin_el_modulo(): void
    {
        $this->assertStringContainsString('tarjeta-frase-destacada', $this->vistaConfiguracion($this->negocioA));
        $this->assertStringContainsString('btn-guardar-frase', $this->vistaConfiguracion($this->negocioA));
    }

    public function test_los_ids_del_tour_de_onboarding_siguen_en_su_sitio(): void
    {
        $html = $this->vistaConfiguracion($this->negocioA);

        foreach (['id="tour-dias-atencion"', 'id="hora_apertura"', 'id="hora_cierre"', 'id="panel-datos"', 'id="tab-banners"'] as $id) {
            $this->assertStringContainsString($id, $html);
        }
    }

    private const PARTIALS_IA = [
        'app/configuracion/partials/ia-generar.blade.php',
        'app/configuracion/partials/ia-generar-js.blade.php',
        'app/configuracion/partials/ia-generar-estilos.blade.php',
    ];

    public function test_el_js_del_componente_no_pinta_html_ni_evalua_codigo(): void
    {
        $js = File::get(resource_path('views/app/configuracion/partials/ia-generar-js.blade.php'));
        // Sin los comentarios de Blade y de JS, que sí pueden nombrar lo prohibido.
        $codigo = preg_replace(['/\{\{--.*?--\}\}/s', '~/\*.*?\*/~s', '~^\s*//.*$~m'], '', $js);

        foreach (['innerHTML', 'outerHTML', '.html(', 'insertAdjacentHTML', 'eval(', 'document.write', 'new Function', '$.ajax', 'fetch('] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $codigo, 'El componente usa "'.$prohibido.'"');
        }

        // Lo que llega de la IA se pinta como texto.
        $this->assertStringContainsString('.text(opcion.texto)', $codigo);
        $this->assertStringContainsString('.text(opcion.advertencia)', $codigo);
        $this->assertStringContainsString('axiosSipleInterno', $codigo);
    }

    public function test_el_componente_no_tiene_hexadecimales_ni_colores_sueltos(): void
    {
        foreach (self::PARTIALS_IA as $ruta) {
            $codigo = preg_replace('/\{\{--.*?--\}\}/s', '', File::get(resource_path('views/'.$ruta)));

            $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b/', $codigo), $ruta.' tiene un hexadecimal');
            $this->assertSame(0, preg_match('/\b(rgb|rgba|hsl|hsla)\s*\(/i', $codigo), $ruta.' tiene un color suelto');
        }

        // La franja de la página pública tampoco: solo variables de la paleta fija.
        $layout = File::get(resource_path('views/layout/publico.blade.php'));
        preg_match_all('/^\s*\.franja-frase.*$/m', $layout, $lineas);
        $this->assertNotEmpty($lineas[0]);
        foreach ($lineas[0] as $linea) {
            $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b|\brgba?\s*\(/', $linea), 'Color suelto en la franja: '.$linea);
        }
    }

    public function test_el_codigo_del_componente_existe_una_sola_vez_en_las_vistas(): void
    {
        $conLaLogica = [];
        $conElMarcado = [];

        foreach (File::allFiles(resource_path('views')) as $archivo) {
            $contenido = $archivo->getContents();
            $ruta = str_replace('\\', '/', $archivo->getRelativePathname());

            if (str_contains($contenido, 'request/ia/generar') || str_contains($contenido, 'var IaGenerar')) {
                $conLaLogica[] = $ruta;
            }

            if (str_contains($contenido, 'class="ia-generar"')) {
                $conElMarcado[] = $ruta;
            }
        }

        $this->assertSame(['app/configuracion/partials/ia-generar-js.blade.php'], $conLaLogica);
        $this->assertSame(['app/configuracion/partials/ia-generar.blade.php'], $conElMarcado);
    }

    public function test_los_botones_del_componente_son_type_button_y_sin_name(): void
    {
        $marcado = File::get(resource_path('views/app/configuracion/partials/ia-generar.blade.php'));

        preg_match_all('/<button\b[^>]*>/', $marcado, $botones);
        $this->assertNotEmpty($botones[0]);
        foreach ($botones[0] as $boton) {
            $this->assertStringContainsString('type="button"', $boton);
        }

        $this->assertStringNotContainsString(' name="', $marcado, 'Los campos del componente no viajan con el formulario');
        $this->assertStringContainsString('aria-live="polite"', $marcado);
    }
}
