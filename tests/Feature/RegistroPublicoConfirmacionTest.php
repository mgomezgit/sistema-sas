<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Mail\ConfirmarRegistroPublico;
use App\Mail\IntentoRegistroCorreoExistente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Registro público en dos pasos: nada se crea en negocios ni en usuarios hasta
 * que se abre el link del correo de confirmación.
 *
 * El throttle y el campo trampa de esta misma ruta se prueban en
 * RegistroPublicoAbusoTest (adaptada al flujo nuevo).
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * MA — El camino "correo ya activo" respondiendo "Ya existe una cuenta con ese
 *      correo" (como antes): 13 tests, 10 passed, 3 FAILED — la comparación
 *      byte a byte y las dos del aviso al dueño. Restaurado: 13 passed.
 * MB — Sin borrar el pendiente anterior del mismo correo: 13 tests, 12 passed,
 *      1 FAILED — quedaron 2 pendientes y el link viejo seguía vivo.
 * MC — Sin el chequeo de vencimiento: 1 FAILED — el token vencido creó la
 *      cuenta (302 al dashboard en vez de la página de aviso).
 * MD — Sin el chequeo de "ya confirmado" NI el de "correo ocupado": 2 FAILED.
 *      Aun así el segundo clic NO duplicó el negocio: el índice único de
 *      correo activo (usuarios.email_activo_unico) hizo fallar el alta y la
 *      transacción la revirtió. Es una tercera barrera; la prueba detectó
 *      el cambio porque mostró "No pudimos crear tu cuenta" en vez de "Tu
 *      cuenta ya está activa".
 * ME — confirmar() sin transacción: 1 FAILED — "Quedó un negocio sin su
 *      admin".
 * MF — Guardando la clave en claro en vez del hash: 5 FAILED — la prueba de
 *      hash ("This password does not use the Bcrypt algorithm") y las 4 que
 *      completan el alta: crearConClaveHasheada() se negó a guardar una
 *      clave en claro, así que la cuenta no llegó a crearse.
 * Todas restauradas: 13 passed. M5 y M6 (throttle y campo trampa) se
 * repitieron sobre RegistroPublicoAbusoTest ya adaptada: 1 FAILED cada una.
 * MB2 — (al conectar la recuperación de clave) El Controller sin pasar la URL
 *       de recuperación al aviso: 14 tests, 12 passed, 2 FAILED — las dos
 *       pruebas del link real. Restaurado: 14 passed.
 */
class RegistroPublicoConfirmacionTest extends TestCase
{
    use RefreshDatabase;

    const PAYLOAD = '<img src=x onerror=alert(1)>';

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

    /* ================= AYUDANTES ================= */

    private function datos(string $correo, array $sobreescribir = []): array
    {
        return array_merge([
            'nombre_negocio' => 'Spa Aurora',
            'telefono_contacto' => '3001234567',
            'rubro' => 'spa',
            'nombre' => 'Aurora Dueña',
            'email' => $correo,
            'clave' => 'ClaveElegida2026',
            'confirmar_clave' => 'ClaveElegida2026',
        ], $sobreescribir);
    }

    private function registrar(array $datos)
    {
        return $this->postJson('request/registro-publico/crear', $datos);
    }

    /**
     * Links de confirmación encolados para ese correo, del más viejo al más
     * nuevo (sacados del correo real, igual que los abriría la persona).
     */
    private function linksDeConfirmacion(string $correo): array
    {
        return Mail::queued(ConfirmarRegistroPublico::class, fn ($c) => $c->hasTo($correo))
            ->map(fn ($c) => $c->urlConfirmacion)
            ->values()
            ->all();
    }

    private function ultimoLink(string $correo): string
    {
        $links = $this->linksDeConfirmacion($correo);
        $this->assertNotEmpty($links, "No se encoló ningún correo de confirmación para $correo");

        return end($links);
    }

    private function crearCuentaActiva(string $correo): void
    {
        $idNegocio = DB::table('negocios')->insertGetId([
            'nombre_negocio' => 'Negocio Existente',
            'slug' => 'negocio-existente',
            'rubro' => 'spa',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        DB::table('usuarios')->insert([
            'tenant_id' => $idNegocio,
            'id_rol' => 1,
            'usuario' => $correo,
            'nombre' => 'Dueño Real',
            'email' => $correo,
            'clave' => Hash::make('ClaveDelDueño2026'),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    /* ================= 1) EL FLUJO COMPLETO ================= */

    public function test_el_paso_1_no_crea_nada_y_el_link_crea_negocio_usuario_y_abre_sesion(): void
    {
        $this->registrar($this->datos('aurora@registro.test'))
            ->assertJsonPath('error', 0)
            ->assertJsonPath('data.mensaje', 'Revisa tu correo para continuar.');

        // Paso 1: solo el registro pendiente. Ni negocio ni usuario.
        $this->assertSame(0, DB::table('negocios')->count());
        $this->assertSame(0, DB::table('usuarios')->count());
        $this->assertSame(1, DB::table('registros_pendientes')->count());

        // Paso 2: el link del correo.
        $this->get($this->ultimoLink('aurora@registro.test'))
            ->assertRedirect(url('backoffice/dashboard'));

        $negocio = DB::table('negocios')->first();
        $this->assertSame('Spa Aurora', $negocio->nombre_negocio);
        $this->assertSame('spa-aurora', $negocio->slug);
        $this->assertSame('3001234567', $negocio->telefono_contacto);

        $usuario = DB::table('usuarios')->first();
        $this->assertSame('aurora@registro.test', $usuario->email);
        $this->assertSame('Aurora Dueña', $usuario->nombre);
        $this->assertSame((int) $negocio->id_negocio, (int) $usuario->tenant_id);
        $this->assertSame(1, (int) $usuario->id_rol);

        $this->assertNotNull(DB::table('registros_pendientes')->value('confirmado_en'));

        // Quedó con la sesión abierta, como tras un login: entra al dashboard.
        $this->assertSame(VerificarSesion::CLAVE_SESION, session('app_sesion'));
        $this->assertSame((int) $negocio->id_negocio, (int) session('tenant_id'));
        $this->get('backoffice/dashboard')->assertOk();

        // Y la clave que eligió en el formulario sirve para un login normal.
        $this->flushSession();
        $this->postJson('request/autenticacion/login', [
            'email' => 'aurora@registro.test',
            'clave' => 'ClaveElegida2026',
        ])->assertJsonPath('error', 0);
    }

    /** La clave nunca queda en texto plano, ni el token en claro. */
    public function test_la_clave_y_el_token_solo_se_guardan_hasheados(): void
    {
        $this->registrar($this->datos('aurora@registro.test'));

        $fila = (array) DB::table('registros_pendientes')->first();
        $token = basename($this->ultimoLink('aurora@registro.test'));

        $this->assertTrue(Hash::check('ClaveElegida2026', $fila['clave_hash']));
        $this->assertSame(hash('sha256', $token), $fila['token_hash']);

        foreach ($fila as $columna => $valor) {
            $this->assertStringNotContainsString('ClaveElegida2026', (string) $valor, "La clave aparece en claro en $columna");
            $this->assertStringNotContainsString($token, (string) $valor, "El token aparece en claro en $columna");
        }
    }

    /* ================= 2) MISMA RESPUESTA, EXISTA O NO EL CORREO =================
     * (mutación: ver la cabecera del archivo)
     */

    public function test_la_respuesta_es_identica_byte_a_byte_exista_o_no_el_correo(): void
    {
        $this->crearCuentaActiva('existe@registro.test');

        $nuevo = $this->registrar($this->datos('nuevo@registro.test'));
        $existente = $this->registrar($this->datos('existe@registro.test'));

        $this->assertSame($nuevo->getStatusCode(), $existente->getStatusCode());
        $this->assertSame($nuevo->getContent(), $existente->getContent());
    }

    /* ================= 3) CORREO CON CUENTA ACTIVA ================= */

    public function test_con_un_correo_ya_activo_no_se_crea_nada_y_se_avisa_a_su_dueño(): void
    {
        $this->crearCuentaActiva('existe@registro.test');

        $this->registrar($this->datos('existe@registro.test', ['nombre_negocio' => 'Intento Ajeno']))
            ->assertJsonPath('error', 0);

        $this->assertSame(0, DB::table('registros_pendientes')->count());
        $this->assertSame(1, DB::table('negocios')->count(), 'Solo el negocio que ya existía');

        Mail::assertQueued(IntentoRegistroCorreoExistente::class, fn ($c) => $c->hasTo('existe@registro.test'));
        Mail::assertNotQueued(ConfirmarRegistroPublico::class);
    }

    /**
     * El aviso lleva el link para entrar, y NO repite nada de lo que escribió
     * quien llenó el formulario (que puede no ser el dueño del correo).
     */
    public function test_el_aviso_al_dueño_lleva_el_login_y_no_repite_datos_del_formulario(): void
    {
        $this->crearCuentaActiva('existe@registro.test');
        $this->registrar($this->datos('existe@registro.test', ['nombre_negocio' => 'Texto Del Intruso', 'nombre' => self::PAYLOAD]));

        $correo = Mail::queued(IntentoRegistroCorreoExistente::class)->first();
        $html = $correo->render();

        $this->assertStringContainsString(url('/login'), $html);
        $this->assertStringNotContainsString('Texto Del Intruso', $html);
        $this->assertStringNotContainsString('img src=x', $html);

        // Desde que existe la recuperación de clave, el aviso trae el link real
        // (con el correo del dueño precargado) en vez de "comunícate con soporte".
        $urlRecuperacion = url('recuperar-clave').'?correo='.urlencode('existe@registro.test');
        $this->assertSame($urlRecuperacion, $correo->urlRecuperarClave);
        $this->assertStringContainsString(e($urlRecuperacion), $html);
        $this->assertStringNotContainsString('Comunícate con soporte', $html);
    }

    /** El link del aviso abre la pantalla de recuperación con el correo ya escrito. */
    public function test_el_link_del_aviso_abre_la_recuperacion_con_el_correo_precargado(): void
    {
        $this->crearCuentaActiva('existe@registro.test');
        $this->registrar($this->datos('existe@registro.test'));

        $url = Mail::queued(IntentoRegistroCorreoExistente::class)->first()->urlRecuperarClave;

        $this->get($url)->assertOk()->assertSee('value="existe@registro.test"', false);
    }

    /* ================= 4) REENVIAR EL FORMULARIO =================
     * (mutación: ver la cabecera del archivo)
     */

    public function test_reenviar_con_el_mismo_correo_invalida_el_link_viejo_y_el_nuevo_si_sirve(): void
    {
        $this->registrar($this->datos('aurora@registro.test', ['nombre_negocio' => 'Spa Con Error']));
        $this->registrar($this->datos('aurora@registro.test', ['nombre_negocio' => 'Spa Corregido', 'clave' => 'ClaveNueva2026', 'confirmar_clave' => 'ClaveNueva2026']));

        [$linkViejo, $linkNuevo] = $this->linksDeConfirmacion('aurora@registro.test');

        // No se acumulan pendientes.
        $this->assertSame(1, DB::table('registros_pendientes')->where('correo', 'aurora@registro.test')->count());

        // El link viejo ya no sirve y no crea nada.
        $this->get($linkViejo)->assertOk()->assertSee('Este enlace ya no es válido');
        $this->assertSame(0, DB::table('negocios')->count());

        // El nuevo sí, con los datos y la clave corregidos.
        $this->get($linkNuevo)->assertRedirect(url('backoffice/dashboard'));
        $this->assertSame('Spa Corregido', DB::table('negocios')->value('nombre_negocio'));
        $this->assertTrue(Hash::check('ClaveNueva2026', DB::table('usuarios')->value('clave')));
    }

    /* ================= 5) TOKEN VENCIDO, USADO O INVENTADO =================
     * (mutación: ver la cabecera del archivo)
     */

    public function test_un_token_vencido_es_rechazado_y_no_crea_nada(): void
    {
        $this->registrar($this->datos('aurora@registro.test'));
        DB::table('registros_pendientes')->update(['fecha_expiracion' => now()->subMinute()]);

        $this->get($this->ultimoLink('aurora@registro.test'))
            ->assertOk()
            ->assertSee('Este enlace ya no es válido')
            ->assertSee(url('registro'));

        $this->assertSame(0, DB::table('negocios')->count());
        $this->assertSame(0, DB::table('usuarios')->count());
        $this->assertNull(session('app_sesion'));
    }

    public function test_un_token_ya_usado_no_crea_un_segundo_negocio(): void
    {
        $this->registrar($this->datos('aurora@registro.test'));
        $link = $this->ultimoLink('aurora@registro.test');

        $this->get($link)->assertRedirect(url('backoffice/dashboard'));

        $this->flushSession();
        $this->get($link)->assertOk()->assertSee('Tu cuenta ya está activa');

        $this->assertSame(1, DB::table('negocios')->count());
        $this->assertSame(1, DB::table('usuarios')->count());
        $this->assertNull(session('app_sesion'), 'Re-visitar el link no debe abrir otra sesión');
    }

    public function test_un_token_inventado_muestra_el_aviso_y_no_crea_nada(): void
    {
        $this->get('request/registro-publico/confirmar/'.str_repeat('x', 64))
            ->assertOk()
            ->assertSee('Este enlace ya no es válido');

        $this->assertSame(0, DB::table('negocios')->count());
    }

    /** Entre el paso 1 y el 2 alguien activó una cuenta con ese correo. */
    public function test_si_el_correo_se_ocupo_antes_de_confirmar_no_se_crea_nada(): void
    {
        $this->registrar($this->datos('aurora@registro.test'));
        $link = $this->ultimoLink('aurora@registro.test');

        $this->crearCuentaActiva('aurora@registro.test');

        $this->get($link)->assertOk()->assertSee('Ya existe una cuenta con este correo');
        $this->assertSame(1, DB::table('negocios')->count(), 'Solo el que ya existía');
        $this->assertNull(DB::table('registros_pendientes')->value('confirmado_en'));
    }

    /* ================= 6) LA TRANSACCIÓN DEL PASO 2 =================
     * (mutación: ver la cabecera del archivo)
     */

    public function test_si_falla_la_creacion_del_usuario_no_queda_un_negocio_huerfano(): void
    {
        $this->registrar($this->datos('aurora@registro.test'));
        $link = $this->ultimoLink('aurora@registro.test');

        Usuario::creating(function () {
            throw new \RuntimeException('Fallo forzado al crear el usuario');
        });

        $this->get($link)->assertOk()->assertSee('No pudimos crear tu cuenta');

        $this->assertSame(0, DB::table('negocios')->count(), 'Quedó un negocio sin su admin');
        $this->assertSame(0, DB::table('usuarios')->count());
        $this->assertNull(DB::table('registros_pendientes')->value('confirmado_en'), 'El link debe poder reintentarse');
    }

    /* ================= 7) AISLAMIENTO ENTRE REGISTROS =================
     * (mutación: ver la cabecera del archivo)
     */

    public function test_dos_registros_pendientes_nunca_se_cruzan_datos(): void
    {
        $this->registrar($this->datos('ana@registro.test', ['nombre_negocio' => 'Spa De Ana', 'nombre' => 'Ana', 'clave' => 'ClaveDeAna2026', 'confirmar_clave' => 'ClaveDeAna2026']));
        $this->registrar($this->datos('beto@registro.test', ['nombre_negocio' => 'Spa De Beto', 'nombre' => 'Beto', 'clave' => 'ClaveDeBeto2026', 'confirmar_clave' => 'ClaveDeBeto2026']));

        // Confirmar el de Ana no toca el de Beto.
        $this->get($this->ultimoLink('ana@registro.test'))->assertRedirect(url('backoffice/dashboard'));

        $this->assertSame(['Spa De Ana'], DB::table('negocios')->pluck('nombre_negocio')->all());
        $this->assertNull(DB::table('registros_pendientes')->where('correo', 'beto@registro.test')->value('confirmado_en'));

        $ana = DB::table('usuarios')->where('email', 'ana@registro.test')->first();
        $this->assertSame('Ana', $ana->nombre);
        $this->assertTrue(Hash::check('ClaveDeAna2026', $ana->clave));
        $this->assertFalse(Hash::check('ClaveDeBeto2026', $ana->clave));
        $this->assertSame(session('tenant_id'), (int) $ana->tenant_id);

        // El de Beto crea SU negocio, con SUS datos, en otro tenant.
        $this->flushSession();
        $this->get($this->ultimoLink('beto@registro.test'))->assertRedirect(url('backoffice/dashboard'));

        $beto = DB::table('usuarios')->where('email', 'beto@registro.test')->first();
        $negocioBeto = DB::table('negocios')->where('id_negocio', $beto->tenant_id)->first();

        $this->assertSame('Spa De Beto', $negocioBeto->nombre_negocio);
        $this->assertNotSame((int) $ana->tenant_id, (int) $beto->tenant_id);
        $this->assertTrue(Hash::check('ClaveDeBeto2026', $beto->clave));
    }

    /* ================= 8) XSS EN EL CORREO DE CONFIRMACIÓN ================= */

    public function test_el_correo_de_confirmacion_escapa_nombre_y_negocio(): void
    {
        $this->registrar($this->datos('aurora@registro.test', ['nombre' => self::PAYLOAD, 'nombre_negocio' => self::PAYLOAD]));

        $html = Mail::queued(ConfirmarRegistroPublico::class)->first()->render();

        $this->assertStringNotContainsString(self::PAYLOAD, $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }
}
