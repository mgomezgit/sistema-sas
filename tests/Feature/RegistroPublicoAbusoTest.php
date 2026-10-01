<?php

namespace Tests\Feature;

use App\Mail\ConfirmarRegistroPublico;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Registro público de negocios: frenos contra la creación masiva (throttle
 * por IP y campo trampa), y la garantía de que un alta normal sigue
 * funcionando.
 *
 * Desde el registro en dos pasos, el paso 1 ya no crea negocios: deja un
 * registro PENDIENTE y manda el correo de confirmación. Por eso estas pruebas
 * miden registros_pendientes y correos encolados, no negocios. (La prueba del
 * campo trampa, sin ese ajuste, habría seguido pasando sin probar nada.)
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * M5 — Sin ->middleware('throttle:registro-publico') en la ruta: 6 tests,
 *      5 passed, 1 FAILED — el sexto registro desde la misma IP se creó
 *      ("0 is identical to 1"). Restaurado: 6 passed.
 * M6 — Sin el chequeo de "sitio_web" en el controller: 6 tests, 5 passed,
 *      1 FAILED — con la trampa llena se creó el negocio ("1 is identical to
 *      0"). La prueba de "respuesta idéntica" sigue pasando en esa mutación,
 *      como es de esperar: sin la trampa las dos respuestas son un alta real.
 *      Restaurado: 6 passed.
 * (M5 y M6 se repitieron tras pasar al registro en dos pasos: ver el reporte
 * de esa tarea y la sección de mutaciones de RegistroPublicoConfirmacionTest.)
 */
class RegistroPublicoAbusoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);
    }

    private function datos(int $n, array $sobreescribir = []): array
    {
        return array_merge([
            'nombre_negocio' => 'Spa Numero '.$n,
            'telefono_contacto' => '300000000'.$n,
            'rubro' => 'spa',
            'nombre' => 'Dueña '.$n,
            'email' => 'duena'.$n.'@registro.test',
            'clave' => 'ClaveSegura'.$n,
            'confirmar_clave' => 'ClaveSegura'.$n,
        ], $sobreescribir);
    }

    private function registrar(array $datos)
    {
        return $this->postJson('request/registro-publico/crear', $datos);
    }

    /** El link real del correo de confirmación que se le encoló a ese correo. */
    private function linkDeConfirmacion(string $correo): string
    {
        $link = null;

        Mail::assertQueued(ConfirmarRegistroPublico::class, function ($correoEncolado) use ($correo, &$link) {
            if ($correoEncolado->hasTo($correo)) {
                $link = $correoEncolado->urlConfirmacion;

                return true;
            }

            return false;
        });

        return $link;
    }

    /* ================= 1) EL ALTA NORMAL SIGUE FUNCIONANDO ================= */

    public function test_un_registro_normal_termina_creando_el_negocio_al_confirmar(): void
    {
        $this->registrar($this->datos(1))
            ->assertJsonPath('error', 0)
            ->assertJsonPath('data.mensaje', 'Revisa tu correo para continuar.');

        $this->get($this->linkDeConfirmacion('duena1@registro.test'))
            ->assertRedirect(url('backoffice/dashboard'));

        $negocio = DB::table('negocios')->where('nombre_negocio', 'Spa Numero 1')->first();
        $this->assertNotNull($negocio);
        $this->assertSame('spa-numero-1', $negocio->slug);
        $this->assertSame(1, (int) $negocio->estado);

        $usuario = DB::table('usuarios')->where('email', 'duena1@registro.test')->first();
        $this->assertNotNull($usuario);
        $this->assertSame((int) $negocio->id_negocio, (int) $usuario->tenant_id);
        $this->assertTrue(Hash::check('ClaveSegura1', $usuario->clave));

        // Y la cuenta puede entrar después por el login normal.
        $this->flushSession();
        $this->postJson('request/autenticacion/login', [
            'email' => 'duena1@registro.test',
            'clave' => 'ClaveSegura1',
        ])->assertJsonPath('error', 0);
    }

    /* ================= 2) THROTTLE POR IP ================= */

    public function test_el_sexto_registro_en_el_mismo_minuto_desde_la_misma_ip_es_rechazado(): void
    {
        for ($n = 1; $n <= AppServiceProvider::REGISTROS_POR_MINUTO; $n++) {
            $this->registrar($this->datos($n))->assertJsonPath('error', 0);
        }

        $sexto = $this->registrar($this->datos(6));

        $sexto->assertJsonPath('error', 1);
        $this->assertStringContainsString('demasiados intentos de registro', $sexto->json('mensaje'));

        $this->assertSame(5, DB::table('registros_pendientes')->count());
        $this->assertFalse(DB::table('registros_pendientes')->where('correo', 'duena6@registro.test')->exists());
        Mail::assertNotQueued(ConfirmarRegistroPublico::class, fn ($correo) => $correo->hasTo('duena6@registro.test'));
    }

    public function test_otra_ip_no_comparte_el_limite(): void
    {
        for ($n = 1; $n <= AppServiceProvider::REGISTROS_POR_MINUTO; $n++) {
            $this->registrar($this->datos($n))->assertJsonPath('error', 0);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
            ->registrar($this->datos(6))
            ->assertJsonPath('error', 0);

        $this->assertTrue(DB::table('registros_pendientes')->where('correo', 'duena6@registro.test')->exists());
    }

    /* ================= 3) CAMPO TRAMPA ================= */

    public function test_el_campo_trampa_lleno_no_crea_nada_ni_manda_correo(): void
    {
        $respuesta = $this->registrar($this->datos(1, ['sitio_web' => 'http://spam.example.com']));

        // Responde como si todo hubiera salido bien...
        $respuesta->assertJsonPath('error', 0);

        // ...pero no dejó nada pendiente, ni creó nada, ni mandó correos.
        $this->assertSame(0, DB::table('registros_pendientes')->count());
        $this->assertSame(0, DB::table('negocios')->count());
        $this->assertSame(0, DB::table('usuarios')->count());
        Mail::assertNothingQueued();
    }

    public function test_la_respuesta_de_la_trampa_es_identica_a_la_de_un_registro_bueno(): void
    {
        $conTrampa = $this->registrar($this->datos(1, ['sitio_web' => 'http://spam.example.com']));
        $bueno = $this->registrar($this->datos(2));

        $this->assertSame($bueno->getStatusCode(), $conTrampa->getStatusCode());
        $this->assertSame($bueno->getContent(), $conTrampa->getContent());
    }

    public function test_el_campo_trampa_vacio_no_estorba(): void
    {
        $this->registrar($this->datos(1, ['sitio_web' => '']))->assertJsonPath('error', 0);

        $this->assertSame(1, DB::table('registros_pendientes')->count());
        Mail::assertQueued(ConfirmarRegistroPublico::class);
    }
}
