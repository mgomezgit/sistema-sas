<?php

namespace Tests\Feature;

use App\Mail\ModuloPorVencer;
use App\Mail\ModuloVencido;
use App\Service\SvcModulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Proceso diario modulos:procesar-vencimientos: apagado, avisos (7/1/vencido),
 * idempotencia, negocios suspendidos y destinatarios. Correos con Mail::fake
 * (nunca salen de verdad). Reloj congelado.
 */
class ModuloVencimientoCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $negocio;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 06:30:00');
        Mail::fake();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocio = $this->crearNegocio('Spa Central');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre, int $estado = 1, ?string $acento = 'azul'): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'rubro' => 'spa',
            'color_acento' => $acento,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => $estado,
        ]);
    }

    private function crearAdmin(int $tenantId, string $email, int $estado = 1, int $idRol = 1): int
    {
        return DB::table('usuarios')->insertGetId([
            'tenant_id' => $tenantId,
            'id_rol' => $idRol,
            'usuario' => 'u'.uniqid(),
            'nombre' => 'Admin',
            'email' => $email,
            'clave' => bcrypt('Clave-2026'),
            'usuario_registra' => 'test',
            'fecha_registro' => now(),
            'estado' => $estado,
        ]);
    }

    private function idModulo(string $clave): int
    {
        return (int) DB::table('modulos_plataforma')->where('clave', $clave)->value('id_modulo');
    }

    private function fijarModulo(int $tenantId, string $clave, array $campos): void
    {
        DB::table('negocio_modulos')->updateOrInsert(
            ['tenant_id' => $tenantId, 'id_modulo' => $this->idModulo($clave)],
            $campos + ['fecha_registro' => now()]
        );
    }

    private function correr(): void
    {
        $this->artisan('modulos:procesar-vencimientos')->assertSuccessful();
    }

    /* ================= APAGADO ================= */

    public function test_apaga_solo_lo_vencido_y_no_lo_vigente_ni_lo_que_vence_hoy(): void
    {
        $this->fijarModulo($this->negocio, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::yesterday()->toDateString()]); // vencido
        $this->fijarModulo($this->negocio, 'ia', ['activo' => 1, 'vigente_hasta' => Carbon::today()->toDateString()]);           // vence hoy
        $this->fijarModulo($this->negocio, 'chatbot', ['activo' => 1, 'vigente_hasta' => Carbon::tomorrow()->toDateString()]);   // vigente

        $this->correr();

        $this->assertSame(0, (int) DB::table('negocio_modulos')->where('tenant_id', $this->negocio)->where('id_modulo', $this->idModulo('comisiones'))->value('activo'), 'El vencido se apaga');
        $this->assertSame(1, (int) DB::table('negocio_modulos')->where('tenant_id', $this->negocio)->where('id_modulo', $this->idModulo('ia'))->value('activo'), 'El que vence hoy NO se apaga');
        $this->assertSame(1, (int) DB::table('negocio_modulos')->where('tenant_id', $this->negocio)->where('id_modulo', $this->idModulo('chatbot'))->value('activo'), 'El vigente NO se apaga');
    }

    public function test_el_apagado_es_idempotente_un_solo_correo_de_vencido(): void
    {
        $this->crearAdmin($this->negocio, 'admin@spa.test');
        $this->fijarModulo($this->negocio, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::yesterday()->toDateString()]);

        $this->correr();
        $this->correr(); // segunda corrida el mismo día

        Mail::assertQueued(ModuloVencido::class, 1);
    }

    /* ================= AVISOS 7 Y 1 ================= */

    public function test_aviso_de_7_dias_exacto_una_sola_vez(): void
    {
        $this->crearAdmin($this->negocio, 'admin@spa.test');
        $this->fijarModulo($this->negocio, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::today()->copy()->addDays(7)->toDateString()]);

        $this->correr();
        $this->correr();

        Mail::assertQueued(ModuloPorVencer::class, 1);
        $this->assertNotNull(DB::table('negocio_modulos')->where('tenant_id', $this->negocio)->value('aviso_7_enviado_en'));
    }

    public function test_aviso_de_1_dia_exacto(): void
    {
        $this->crearAdmin($this->negocio, 'admin@spa.test');
        $this->fijarModulo($this->negocio, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::tomorrow()->toDateString()]);

        $this->correr();

        Mail::assertQueued(ModuloPorVencer::class, 1);
        $this->assertNotNull(DB::table('negocio_modulos')->where('tenant_id', $this->negocio)->value('aviso_1_enviado_en'));
    }

    public function test_no_avisa_a_6_ni_a_8_dias(): void
    {
        $this->crearAdmin($this->negocio, 'admin@spa.test');
        $this->fijarModulo($this->negocio, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::today()->copy()->addDays(6)->toDateString()]);
        $this->fijarModulo($this->negocio, 'ia', ['activo' => 1, 'vigente_hasta' => Carbon::today()->copy()->addDays(8)->toDateString()]);

        $this->correr();

        Mail::assertNothingQueued();
    }

    public function test_renovar_reinicia_los_avisos(): void
    {
        $this->crearAdmin($this->negocio, 'admin@spa.test');
        // Vigencia a 7 días: se envía el aviso de 7.
        $this->fijarModulo($this->negocio, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::today()->copy()->addDays(7)->toDateString()]);
        $this->correr();
        Mail::assertQueued(ModuloPorVencer::class, 1);

        // Renovar (vía Service) a otra fecha que de nuevo cae a 7 días desde otro hoy:
        // se limpian las banderas, así que al volver a estar a 7 días vuelve a avisar.
        app(SvcModulo::class)->activarModulo($this->negocio, 'comisiones', 'test', Carbon::today()->copy()->addDays(20)->toDateString());
        $this->assertNull(DB::table('negocio_modulos')->where('tenant_id', $this->negocio)->value('aviso_7_enviado_en'));

        // Avanzamos el reloj a 7 días antes de la nueva fecha.
        Carbon::setTestNow('2026-11-23 06:30:00'); // 2026-11-30 - 7
        $this->correr();
        Mail::assertQueued(ModuloPorVencer::class, 2);
    }

    /* ================= SUSPENDIDOS Y DESTINATARIOS ================= */

    public function test_negocio_suspendido_se_apaga_pero_no_recibe_correo(): void
    {
        $suspendido = $this->crearNegocio('Suspendido', 0);
        $this->crearAdmin($suspendido, 'admin@suspendido.test');
        $this->fijarModulo($suspendido, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::yesterday()->toDateString()]);

        $this->correr();

        $this->assertSame(0, (int) DB::table('negocio_modulos')->where('tenant_id', $suspendido)->value('activo'), 'Se apaga igual');
        Mail::assertNothingQueued();
    }

    public function test_el_correo_llega_solo_a_los_admins_activos_de_ese_negocio(): void
    {
        $otroNegocio = $this->crearNegocio('Otro Spa');

        $this->crearAdmin($this->negocio, 'admin.activo@spa.test', 1);
        $this->crearAdmin($this->negocio, 'admin.inactivo@spa.test', 0);     // inactivo: no recibe
        $this->crearAdmin($this->negocio, 'empleado@spa.test', 1, 2);        // empleado: no es admin
        $this->crearAdmin($otroNegocio, 'admin.otro@otrospa.test', 1);       // otro negocio: no recibe

        $this->fijarModulo($this->negocio, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::yesterday()->toDateString()]);

        $this->correr();

        Mail::assertQueued(ModuloVencido::class, function ($mail) {
            return $mail->hasTo('admin.activo@spa.test')
                && ! $mail->hasTo('admin.inactivo@spa.test')
                && ! $mail->hasTo('empleado@spa.test')
                && ! $mail->hasTo('admin.otro@otrospa.test');
        });
    }

    /* ================= CONTENIDO DEL CORREO ================= */

    public function test_el_correo_de_vencido_va_escapado_con_acento_y_sin_precios(): void
    {
        $negocioXss = $this->crearNegocio('<img src=x onerror=alert(1)> "Spa"', 1, 'verde');
        $this->crearAdmin($negocioXss, 'admin@xss.test');
        $this->fijarModulo($negocioXss, 'comisiones', ['activo' => 1, 'vigente_hasta' => Carbon::yesterday()->toDateString(), 'origen' => 'pagado_fuera', 'nota' => 'nota privada secreta']);

        $mail = new ModuloVencido('<img src=x onerror=alert(1)> "Spa"', 'Cálculo de comisiones', Carbon::yesterday()->toDateString(), 'verde');
        $html = $mail->render();

        // Escapado: el payload no aparece como etiqueta viva.
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;img', $html);
        // Acento verde resuelto a hex (ColorAcento).
        $this->assertStringContainsString('#16a34a', $html);
        // Sin precios y sin la nota/origen privados.
        $this->assertStringNotContainsString('$', $html);
        $this->assertStringNotContainsStringIgnoringCase('precio', $html);
        $this->assertStringNotContainsString('nota privada secreta', $html);
        $this->assertStringNotContainsString('pagado_fuera', $html);
    }

    public function test_el_contacto_sale_solo_si_esta_configurado(): void
    {
        // Sin contacto: texto genérico.
        config(['plataforma.contacto_whatsapp' => '', 'plataforma.contacto_correo' => '']);
        $sin = (new ModuloVencido('Spa', 'Cálculo de comisiones', '2026-11-09', 'azul'))->render();
        $this->assertStringContainsString('soporte', $sin);

        // Con contacto: aparece.
        config(['plataforma.contacto_whatsapp' => '300111', 'plataforma.contacto_correo' => 'hola@plat.test', 'plataforma.nombre' => 'MiPlataforma']);
        $con = (new ModuloVencido('Spa', 'Cálculo de comisiones', '2026-11-09', 'azul'))->render();
        $this->assertStringContainsString('300111', $con);
        $this->assertStringContainsString('hola@plat.test', $con);
    }
}
