<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Mail\ConfirmarRegistroPublico;
use App\Mail\NuevaSolicitudPublica;
use App\Mail\ReservaConfirmada;
use App\Mail\ReservaRecordatorio;
use App\Service\SvcModulo;
use App\Service\SvcPaginaPublica;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\ActivaModulos;
use Tests\TestCase;

/**
 * Módulo de pago "pagina_publica" (Página pública completa).
 *
 * CON el módulo, reservar/{slug} y publico/{slug}/* son la página completa de
 * siempre. SIN él (apagado, vencido o nunca activado):
 * - reservar/{slug} es la página BÁSICA: nombre, horario y WhatsApp, con su
 *   propia lista blanca (SvcPaginaPublica::informacionBasica).
 * - servicios, equipo, banners, agendar y chatbot/* responden el MISMO 404 que
 *   un slug inexistente, sin crear nada; informacion devuelve solo 5 campos.
 * - banners, frase y solicitudes pendientes del panel dan el error del módulo.
 * - descripcion_publica se ignora al crear y editar (la guardada se conserva).
 * - los correos a la clienta no llevan el botón a reservar/{slug}.
 * Los datos nunca se borran y reaparecen al reactivar.
 *
 * Reloj congelado el miércoles 2026-11-18 a las 09:00; los negocios atienden
 * todos los días de 06:00 a 22:00. Mail::fake en todas las pruebas.
 *
 * Negocio A ("spa-completo"): CON el módulo. Negocio B ("spa-basico"): SIN él.
 *
 * ================= MUTACIONES (resultado en el reporte de la tarea) =================
 * M1 la básica pinta la completa (sin el if de paginaCompleta): 5 fallos.
 * M2 agendar sin exigir el módulo: 2 fallos (mismo 404 y cuerpo manipulado).
 * M3 banner/listar sin verificar.modulo: falla la prueba del error del módulo.
 * M4 descripcion_publica se sobrescribe sin módulo: 1 fallo.
 * M5 el correo muestra el botón sin módulo: 1 fallo.
 * M6 un negocio nuevo nace con el módulo: 1 fallo.
 * M7 la migración no activa a los existentes: 1 fallo.
 * M8 informacion básica con un campo de más (teléfono): 1 fallo.
 * Todas restauradas (cmp igual) y la clase vuelve a pasar completa.
 */
class ModuloPaginaPublicaTest extends TestCase
{
    use ActivaModulos;
    use RefreshDatabase;

    const HOY = '2026-11-18';

    /** Marcas únicas de A: nunca pueden aparecer en la página de B. */
    const SERVICIO_A = 'Masaje Exclusivo Del Completo';

    const EMPLEADA_A = 'Empleada Visible Del Completo';

    /** Datos de B que la página básica NO puede mostrar. */
    const SERVICIO_B = 'Facial Oculto Del Basico';

    const EMPLEADA_B = 'Empleada Oculta Del Basico';

    const BANNER_B = 'Promo Oculta Del Basico';

    const FRASE_B = 'Frase Oculta Del Basico';

    const POLITICA_B = 'Politica Oculta Del Basico';

    const TELEFONO_B = '3007776655';

    const DESCRIPCION_B = 'Descripcion publica guardada de B';

    private int $negocioA;

    private int $negocioB;

    private int $recursoA;

    private int $recursoB;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Carbon::setTestNow(Carbon::parse(self::HOY.' 09:00:00'));

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = $this->crearNegocio('Spa Completo', 'spa-completo', ['whatsapp_numero' => '+57 300 111 2233']);
        $this->negocioB = $this->crearNegocio('Spa Basico', 'spa-basico', [
            'whatsapp_numero' => '+57 300 444 5566',
            'telefono_contacto' => self::TELEFONO_B,
            'politica_cancelacion' => self::POLITICA_B,
            'frase_destacada' => self::FRASE_B,
        ]);

        foreach ([$this->negocioA => 'a', $this->negocioB => 'b'] as $negocio => $k) {
            DB::table('usuarios')->insert([
                'tenant_id' => $negocio, 'id_rol' => 1, 'usuario' => 'admin.'.$k, 'nombre' => 'Admin '.$k,
                'email' => 'admin.'.$k.'@negocio.test', 'clave' => bcrypt('ClaveSegura2026'),
                'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
            ]);
        }

        $this->recursoA = $this->crearRecurso($this->negocioA, self::SERVICIO_A);
        $this->recursoB = $this->crearRecurso($this->negocioB, self::SERVICIO_B, self::DESCRIPCION_B);
        $this->crearEmpleado($this->negocioA, self::EMPLEADA_A);
        $this->crearEmpleado($this->negocioB, self::EMPLEADA_B);
        $this->crearBanner($this->negocioB, self::BANNER_B);

        // Solo A tiene la página completa. Los dos tienen "correos" para poder
        // ver el botón (o su ausencia) en los correos a la clienta.
        $this->activarModulo($this->negocioA, 'pagina_publica');
        $this->activarModulo($this->negocioA, 'correos');
        $this->activarModulo($this->negocioB, 'correos');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre, string $slug, array $extra = [], int $estado = 1): int
    {
        return DB::table('negocios')->insertGetId(array_merge([
            'nombre_negocio' => $nombre, 'slug' => $slug, 'rubro' => 'spa',
            'dias_atencion' => '1,2,3,4,5,6,7', 'hora_apertura' => '06:00:00', 'hora_cierre' => '22:00:00',
            'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => $estado,
        ], $extra));
    }

    private function crearRecurso(int $tenantId, string $nombre, ?string $descripcionPublica = null): int
    {
        return DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $tenantId, 'nombre' => $nombre, 'duracion_minutos' => 60, 'precio' => 50000,
            'descripcion_publica' => $descripcionPublica,
            'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ]);
    }

    private function crearEmpleado(int $tenantId, string $nombre): int
    {
        return DB::table('empleados')->insertGetId([
            'tenant_id' => $tenantId, 'nombre' => $nombre, 'telefono' => '3000000000', 'porcentaje_comision' => 10,
            'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ]);
    }

    private function crearBanner(int $tenantId, string $titulo): int
    {
        return DB::table('banners_promocionales')->insertGetId([
            'tenant_id' => $tenantId, 'imagen_path' => 'banners/prueba.jpg', 'titulo' => $titulo, 'texto' => 'Texto',
            'orden' => 0, 'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ]);
    }

    private function sesionAdmin(int $tenantId): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 999999, 'usuario' => 'admin.test', 'nombre_usuario' => 'Admin Test',
            'tenant_id' => $tenantId, 'id_rol' => 1, 'nombre_negocio_sesion' => 'Spa',
        ];
    }

    private function pagina(string $slug): string
    {
        return $this->get('reservar/'.$slug)->assertOk()->getContent();
    }

    /** Las 7 rutas públicas que solo existen con la página completa. */
    private function endpointsCompletos(string $slug): array
    {
        return [
            ['GET', "publico/$slug/servicios", []],
            ['GET', "publico/$slug/equipo", []],
            ['GET', "publico/$slug/banners", []],
            ['POST', "publico/$slug/agendar", $this->datosAgendar()],
            ['GET', "publico/$slug/chatbot/estado", []],
            ['POST', "publico/$slug/chatbot/mensaje", ['mensaje' => 'Hola']],
            ['POST', "publico/$slug/chatbot/terminar", []],
        ];
    }

    private function datosAgendar(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Visitante Nueva', 'telefono' => '3001112233', 'email' => 'visitante@correo.test',
            'id_recurso' => $this->recursoB, 'fecha_reserva' => '2026-11-20', 'hora_inicio' => '10:00',
        ], $extra);
    }

    private function assertPaginaBasicaSinNadaMas(string $html): void
    {
        $this->assertStringContainsString('id="pagina-basica"', $html);

        foreach ([self::SERVICIO_B, self::EMPLEADA_B, self::BANNER_B, self::FRASE_B, self::POLITICA_B, self::TELEFONO_B, self::DESCRIPCION_B,
            // Elementos (por id o atributo: los nombres de clase existen en el CSS del layout).
            'id="modal-agendar"', 'data-abrir-agendar', 'id="form-agendar"', 'id="chat-raiz"', 'id="seccion-servicios"',
            'id="seccion-equipo"', 'id="seccion-promos"', 'id="franja-frase-destacada"', 'class="pie-politica"',
            'class="nav-cta"', '/agendar', '/chatbot'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $html, "La página básica no puede mostrar: $prohibido");
        }
    }

    /* ================= 1) CON EL MÓDULO: LA PÁGINA COMPLETA DE SIEMPRE ================= */

    public function test_con_el_modulo_la_pagina_y_sus_endpoints_son_los_completos(): void
    {
        $html = $this->pagina('spa-completo');

        $this->assertStringContainsString('seccion-servicios', $html);
        $this->assertStringContainsString(self::SERVICIO_A, $html);
        $this->assertStringContainsString(self::EMPLEADA_A, $html);
        $this->assertStringContainsString('id="modal-agendar"', $html);
        $this->assertStringContainsString('id="chat-raiz"', $html);
        $this->assertStringNotContainsString('id="pagina-basica"', $html);

        $this->getJson('publico/spa-completo/servicios')->assertOk()->assertJsonPath('data.servicios.0.nombre', self::SERVICIO_A);
        $this->getJson('publico/spa-completo/equipo')->assertOk()->assertJsonPath('data.equipo.0.nombre', self::EMPLEADA_A);
        $this->getJson('publico/spa-completo/banners')->assertOk()->assertJsonPath('error', 0);
        $this->getJson('publico/spa-completo/chatbot/estado')->assertOk()->assertJsonPath('error', 0);
        $this->assertArrayHasKey('telefono_contacto', $this->getJson('publico/spa-completo/informacion')->json('data.negocio'));

        $this->postJson('publico/spa-completo/agendar', $this->datosAgendar(['id_recurso' => $this->recursoA]))->assertJsonPath('error', 0);
        $this->assertSame(1, DB::table('reservas')->where('tenant_id', $this->negocioA)->where('origen', 'publico')->count());
        Mail::assertQueued(NuevaSolicitudPublica::class, fn ($m) => $m->hasTo('admin.a@negocio.test'));
    }

    /* ================= 2) SIN EL MÓDULO: PÁGINA BÁSICA ================= */

    public function test_sin_el_modulo_la_pagina_es_basica_con_nombre_horario_y_whatsapp(): void
    {
        $html = $this->pagina('spa-basico');

        $this->assertStringContainsString('Spa Basico', $html);
        $this->assertStringContainsString('Horario de atención', $html);
        $this->assertStringContainsString('Lunes a Domingo, de 6:00 AM a 10:00 PM', $html);
        $this->assertStringContainsString('href="https://wa.me/573004445566?text='.rawurlencode('Hola, quiero hacerles una consulta').'"', $html);
        $this->assertStringContainsString('Escríbenos por WhatsApp', $html);
        $this->assertSame(1, substr_count($html, 'wa.me/'), 'Un único botón de WhatsApp');

        $this->assertPaginaBasicaSinNadaMas($html);
    }

    public function test_la_lista_blanca_basica_tiene_solo_cinco_campos(): void
    {
        $negocio = app(SvcPaginaPublica::class)->resolverPorSlug('spa-basico');

        $this->assertSame(
            ['nombre_negocio', 'dias_atencion', 'hora_apertura', 'hora_cierre', 'whatsapp_numero'],
            array_keys(app(SvcPaginaPublica::class)->informacionBasica($negocio))
        );

        $informacion = $this->getJson('publico/spa-basico/informacion')->assertOk()->json('data.negocio');
        $this->assertSame(['nombre_negocio', 'dias_atencion', 'hora_apertura', 'hora_cierre', 'whatsapp_numero'], array_keys($informacion));
        $this->assertStringNotContainsString(self::TELEFONO_B, json_encode($informacion));
        $this->assertStringNotContainsString(self::FRASE_B, json_encode($informacion));
    }

    public function test_sin_numero_de_whatsapp_no_hay_boton_y_sin_horario_queda_solo_el_nombre(): void
    {
        DB::table('negocios')->where('id_negocio', $this->negocioB)->update(['whatsapp_numero' => null]);
        $html = $this->pagina('spa-basico');
        $this->assertStringNotContainsString('wa.me', $html);
        $this->assertStringNotContainsString('id="whatsapp-basica"', $html);
        $this->assertStringContainsString('id="horario-basica"', $html);

        DB::table('negocios')->where('id_negocio', $this->negocioB)->update(['dias_atencion' => null, 'hora_apertura' => null, 'hora_cierre' => null]);
        $html = $this->pagina('spa-basico');
        $this->assertStringContainsString('Spa Basico', $html);
        $this->assertStringNotContainsString('id="horario-basica"', $html);
        $this->assertStringNotContainsString('Horario de atención', $html);
    }

    /* ================= 3) SIN EL MÓDULO: EL MISMO 404 ================= */

    public function test_sin_el_modulo_los_endpoints_publicos_dan_el_mismo_404_que_un_slug_inexistente(): void
    {
        $inexistentes = $this->endpointsCompletos('slug-que-no-existe');

        // Con y sin depuración. Las dos peticiones salen de la MISMA línea: con
        // APP_DEBUG la respuesta trae la traza, y así ni siquiera ella puede
        // distinguir un caso del otro; sin depuración es lo que ve producción.
        foreach ([true, false] as $depuracion) {
            config(['app.debug' => $depuracion]);

            foreach ($this->endpointsCompletos('spa-basico') as $i => $peticion) {
                $url = $peticion[1];
                $respuestas = [];

                foreach ([$inexistentes[$i], $peticion] as $p) {
                    $respuestas[] = $this->json($p[0], $p[1], $p[2]);
                }

                [$esperado, $real] = $respuestas;

                $this->assertSame(404, $real->getStatusCode(), "$url debía dar 404");
                $this->assertSame($esperado->getStatusCode(), $real->getStatusCode());
                $this->assertSame($esperado->getContent(), $real->getContent(), "$url no responde byte a byte como un slug inexistente");
            }
        }

        // agendar no creó nada ni avisó a nadie.
        $this->assertSame(0, DB::table('reservas')->count());
        $this->assertSame(0, DB::table('clientes')->count());
        Mail::assertNothingQueued();
    }

    public function test_un_slug_o_tenant_id_manipulado_en_el_cuerpo_se_ignora(): void
    {
        // B (sin módulo) mandando el slug y el tenant de A: sigue en 404 y no crea nada en A.
        $this->postJson('publico/spa-basico/agendar', $this->datosAgendar([
            'slug' => 'spa-completo', 'tenant_id' => $this->negocioA, 'id_recurso' => $this->recursoA,
        ]))->assertNotFound();

        // A (con módulo) mandando el tenant de B: la solicitud queda en A, nunca en B.
        $this->postJson('publico/spa-completo/agendar', $this->datosAgendar([
            'slug' => 'spa-basico', 'tenant_id' => $this->negocioB, 'id_recurso' => $this->recursoA,
        ]))->assertJsonPath('error', 0);

        $this->assertSame(0, DB::table('reservas')->where('tenant_id', $this->negocioB)->count());
        $this->assertSame(1, DB::table('reservas')->where('tenant_id', $this->negocioA)->count());
    }

    /* ================= 4) PANEL: BANNERS, FRASE Y SOLICITUDES ================= */

    public function test_sin_el_modulo_banners_frase_y_solicitudes_dan_el_error_del_modulo(): void
    {
        $sesion = $this->sesionAdmin($this->negocioB);

        $respuestas = [
            $this->withSession($sesion)->getJson('request/banner/listar'),
            $this->withSession($sesion)->postJson('request/banner/crear', ['titulo' => 'X', 'tenant_id' => $this->negocioA]),
            $this->withSession($sesion)->postJson('request/banner/editar', ['id_banner' => 1, 'titulo' => 'X']),
            $this->withSession($sesion)->postJson('request/banner/eliminar', ['id_banner' => 1]),
            $this->withSession($sesion)->postJson('request/negocio/guardar-frase', ['frase' => 'Nueva frase']),
            $this->withSession($sesion)->postJson('request/negocio/quitar-frase'),
            $this->withSession($sesion)->getJson('request/reserva/solicitudes-pendientes'),
        ];

        foreach ($respuestas as $respuesta) {
            $respuesta->assertOk()->assertJsonPath('error', 1);
            $this->assertStringContainsString('Este módulo no está activo para tu negocio', $respuesta->json('mensaje'));
        }

        // Nada cambió: la frase y el banner de B siguen guardados.
        $this->assertSame(self::FRASE_B, DB::table('negocios')->where('id_negocio', $this->negocioB)->value('frase_destacada'));
        $this->assertSame(1, DB::table('banners_promocionales')->where('tenant_id', $this->negocioB)->count());
        $this->assertSame(0, DB::table('banners_promocionales')->where('tenant_id', $this->negocioA)->count());

        // Con el módulo (A) los mismos endpoints responden normal.
        $this->withSession($this->sesionAdmin($this->negocioA))->getJson('request/banner/listar')->assertJsonPath('error', 0);
        $this->withSession($this->sesionAdmin($this->negocioA))->getJson('request/reserva/solicitudes-pendientes')->assertJsonPath('error', 0);
    }

    public function test_las_solicitudes_pendientes_que_ya_existian_siguen_en_la_agenda(): void
    {
        $idCliente = DB::table('clientes')->insertGetId([
            'tenant_id' => $this->negocioB, 'nombre' => 'Clienta Previa', 'telefono' => '3000000000',
            'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ]);
        $idReserva = DB::table('reservas')->insertGetId([
            'tenant_id' => $this->negocioB, 'id_cliente' => $idCliente, 'id_recurso' => $this->recursoB, 'id_empleado' => null,
            'fecha_reserva' => '2026-11-20', 'hora_inicio' => '10:00:00', 'hora_fin' => '11:00:00', 'estado_reserva' => 'pendiente',
            'origen' => 'publico', 'usuario_registra' => 'publico', 'fecha_registro' => now(), 'estado' => 1,
        ]);

        $sesion = $this->sesionAdmin($this->negocioB);

        $listado = $this->withSession($sesion)->getJson('request/reserva/listar')->assertJsonPath('error', 0)->getContent();
        $this->assertStringContainsString('Clienta Previa', $listado);

        $this->withSession($sesion)->getJson('request/reserva/obtener?id_reserva='.$idReserva)
            ->assertJsonPath('error', 0)
            ->assertJsonPath('data.reserva.estado_reserva', 'pendiente');
    }

    /* ================= 5) DESCRIPCIÓN PÚBLICA ================= */

    public function test_sin_el_modulo_la_descripcion_publica_guardada_se_conserva_al_editar(): void
    {
        $this->withSession($this->sesionAdmin($this->negocioB))->postJson('request/recurso/editar', [
            'id_recurso' => $this->recursoB, 'nombre' => 'Facial Renombrado', 'duracion_minutos' => 45, 'precio' => 60000,
            'estado' => 1, 'descripcion_publica' => 'Texto nuevo que no debe guardarse',
        ])->assertJsonPath('error', 0);

        $fila = DB::table('recursos_reservables')->where('id_recurso', $this->recursoB)->first();
        $this->assertSame('Facial Renombrado', $fila->nombre, 'El resto del servicio sí se guarda');
        $this->assertSame(self::DESCRIPCION_B, $fila->descripcion_publica, 'La descripción guardada no se toca');

        // Vacía en el cuerpo tampoco la borra.
        $this->withSession($this->sesionAdmin($this->negocioB))->postJson('request/recurso/editar', [
            'id_recurso' => $this->recursoB, 'nombre' => 'Facial Renombrado', 'duracion_minutos' => 45, 'precio' => 60000,
            'estado' => 1, 'descripcion_publica' => '',
        ])->assertJsonPath('error', 0);
        $this->assertSame(self::DESCRIPCION_B, DB::table('recursos_reservables')->where('id_recurso', $this->recursoB)->value('descripcion_publica'));

        // Al crear sin el módulo, queda vacía.
        $id = $this->withSession($this->sesionAdmin($this->negocioB))->postJson('request/recurso/crear', [
            'nombre' => 'Servicio Nuevo', 'duracion_minutos' => 30, 'precio' => 20000, 'descripcion_publica' => 'No debe guardarse',
        ])->assertJsonPath('error', 0)->json('data.id_recurso');
        $this->assertNull(DB::table('recursos_reservables')->where('id_recurso', $id)->value('descripcion_publica'));

        // Con el módulo (A) sí se guarda.
        $this->withSession($this->sesionAdmin($this->negocioA))->postJson('request/recurso/editar', [
            'id_recurso' => $this->recursoA, 'nombre' => self::SERVICIO_A, 'duracion_minutos' => 60, 'precio' => 50000,
            'estado' => 1, 'descripcion_publica' => 'Descripcion de A',
        ])->assertJsonPath('error', 0);
        $this->assertSame('Descripcion de A', DB::table('recursos_reservables')->where('id_recurso', $this->recursoA)->value('descripcion_publica'));
    }

    /* ================= 6) CORREOS A LA CLIENTA ================= */

    private function clienteConCorreo(int $negocio, string $correo): int
    {
        return DB::table('clientes')->insertGetId([
            'tenant_id' => $negocio, 'nombre' => 'Clienta', 'telefono' => '3000000000', 'email' => $correo,
            'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
        ]);
    }

    public function test_el_boton_del_correo_solo_va_con_la_pagina_completa(): void
    {
        foreach ([[$this->negocioA, $this->recursoA, 'clienta.a@correo.test'], [$this->negocioB, $this->recursoB, 'clienta.b@correo.test']] as [$negocio, $recurso, $correo]) {
            $this->withSession($this->sesionAdmin($negocio))->postJson('request/reserva/crear', [
                'id_cliente' => $this->clienteConCorreo($negocio, $correo), 'id_recurso' => $recurso,
                'fecha_reserva' => '2026-11-20', 'hora_inicio' => '10:00',
            ])->assertJsonPath('error', 0);
        }

        $correoA = Mail::queued(ReservaConfirmada::class, fn ($m) => $m->hasTo('clienta.a@correo.test'))->first();
        $correoB = Mail::queued(ReservaConfirmada::class, fn ($m) => $m->hasTo('clienta.b@correo.test'))->first();

        $this->assertTrue($correoA->negocio['pagina_completa']);
        $this->assertFalse($correoB->negocio['pagina_completa']);
        $this->assertStringContainsString(url('reservar/spa-completo'), $correoA->render());
        $this->assertStringNotContainsString('reservar/spa-basico', $correoB->render());
        $this->assertStringNotContainsString('<a href=', $correoB->render());
    }

    public function test_el_recordatorio_tambien_omite_el_boton_sin_el_modulo(): void
    {
        foreach ([[$this->negocioA, $this->recursoA, 'clienta.a@correo.test'], [$this->negocioB, $this->recursoB, 'clienta.b@correo.test']] as [$negocio, $recurso, $correo]) {
            DB::table('reservas')->insert([
                'tenant_id' => $negocio, 'id_cliente' => $this->clienteConCorreo($negocio, $correo), 'id_recurso' => $recurso,
                'fecha_reserva' => '2026-11-19', 'hora_inicio' => '10:00:00', 'hora_fin' => '11:00:00', 'estado_reserva' => 'confirmada',
                'usuario_registra' => 'test', 'fecha_registro' => now(), 'estado' => 1,
            ]);
        }

        Artisan::call('reservas:enviar-recordatorios');

        $this->assertStringContainsString(url('reservar/spa-completo'), Mail::queued(ReservaRecordatorio::class, fn ($m) => $m->hasTo('clienta.a@correo.test'))->first()->render());
        $this->assertStringNotContainsString('reservar/spa-basico', Mail::queued(ReservaRecordatorio::class, fn ($m) => $m->hasTo('clienta.b@correo.test'))->first()->render());
    }

    /* ================= 7) VIGENCIA, REACTIVACIÓN Y SUSPENSIÓN ================= */

    public function test_vence_hoy_sigue_completa_y_vencio_ayer_es_basica(): void
    {
        $this->activarModulo($this->negocioB, 'pagina_publica', self::HOY);
        $this->assertStringNotContainsString('id="pagina-basica"', $this->pagina('spa-basico'));
        $this->getJson('publico/spa-basico/servicios')->assertOk();

        $this->activarModulo($this->negocioB, 'pagina_publica', '2026-11-17');
        $this->assertPaginaBasicaSinNadaMas($this->pagina('spa-basico'));
        $this->getJson('publico/spa-basico/servicios')->assertNotFound();

        // Apagado = vencido.
        $this->activarModulo($this->negocioB, 'pagina_publica');
        $this->desactivarModulo($this->negocioB, 'pagina_publica');
        $this->assertPaginaBasicaSinNadaMas($this->pagina('spa-basico'));
    }

    public function test_los_datos_se_conservan_y_reaparecen_al_reactivar(): void
    {
        $this->assertPaginaBasicaSinNadaMas($this->pagina('spa-basico'));

        $this->activarModulo($this->negocioB, 'pagina_publica');
        $html = $this->pagina('spa-basico');

        foreach ([self::SERVICIO_B, self::EMPLEADA_B, self::BANNER_B, self::FRASE_B, self::POLITICA_B, self::DESCRIPCION_B] as $dato) {
            $this->assertStringContainsString($dato, $html, "Al reactivar debía reaparecer: $dato");
        }
    }

    public function test_un_negocio_suspendido_sigue_en_404_con_y_sin_el_modulo(): void
    {
        $suspendidoCon = $this->crearNegocio('Suspendido Con', 'suspendido-con', [], 0);
        $this->crearNegocio('Suspendido Sin', 'suspendido-sin', [], 0);
        $this->activarModulo($suspendidoCon, 'pagina_publica');

        $inexistente = $this->get('reservar/no-existe-nunca');

        foreach (['suspendido-con', 'suspendido-sin'] as $slug) {
            $respuesta = $this->get('reservar/'.$slug)->assertNotFound();
            $this->assertSame($inexistente->getContent(), $respuesta->getContent());
            $this->getJson("publico/$slug/informacion")->assertNotFound();
        }
    }

    /* ================= 8) NEGOCIOS NUEVOS Y MIGRACIÓN ================= */

    public function test_un_negocio_nuevo_por_registro_publico_nace_sin_el_modulo(): void
    {
        $this->postJson('request/registro-publico/crear', [
            'nombre_negocio' => 'Spa Recien Llegado', 'telefono_contacto' => '3001234567', 'rubro' => 'spa',
            'nombre' => 'Duena Nueva', 'email' => 'duena.nueva@correo.test', 'clave' => 'ClaveElegida2026', 'confirmar_clave' => 'ClaveElegida2026',
        ])->assertJsonPath('error', 0);

        $this->get(Mail::queued(ConfirmarRegistroPublico::class)->first()->urlConfirmacion)->assertRedirect(url('backoffice/dashboard'));

        $nuevo = DB::table('negocios')->where('nombre_negocio', 'Spa Recien Llegado')->first();
        $this->assertNotNull($nuevo);
        $this->assertFalse(app(SvcModulo::class)->estaActivo($nuevo->id_negocio, 'pagina_publica'));
        $this->assertPaginaBasicaSinNadaMas($this->pagina($nuevo->slug));
    }

    public function test_la_migracion_activa_el_modulo_en_los_negocios_existentes(): void
    {
        $idModulo = DB::table('modulos_plataforma')->where('clave', 'pagina_publica')->value('id_modulo');
        $this->assertNotNull($idModulo);
        $this->assertSame('Página pública completa', DB::table('modulos_plataforma')->where('id_modulo', $idModulo)->value('nombre'));

        $apagado = $this->crearNegocio('Spa Apagado', 'spa-apagado');
        $this->activarModulo($apagado, 'pagina_publica');
        $this->desactivarModulo($apagado, 'pagina_publica');

        $migracion = require database_path('migrations/2026_11_18_100000_crear_modulo_pagina_publica.php');
        $migracion->up();
        $migracion->up();

        $filas = DB::table('negocio_modulos')->where('tenant_id', $this->negocioB)->where('id_modulo', $idModulo)->get();
        $this->assertCount(1, $filas);
        $this->assertSame(1, (int) $filas[0]->activo);
        $this->assertNull($filas[0]->vigente_hasta);
        $this->assertSame('cortesia', $filas[0]->origen);
        $this->assertSame('Incluido al crear el modulo', $filas[0]->nota);
        $this->assertSame('migracion', $filas[0]->usuario_registra);
        $this->assertSame(0, (int) DB::table('negocio_modulos')->where('tenant_id', $apagado)->where('id_modulo', $idModulo)->value('activo'));
    }

    /* ================= 9) AISLAMIENTO ================= */

    public function test_aislamiento_a_completo_b_basico_y_nada_de_a_en_b(): void
    {
        $htmlA = $this->pagina('spa-completo');
        $htmlB = $this->pagina('spa-basico');

        $this->assertStringContainsString(self::SERVICIO_A, $htmlA);
        $this->assertStringNotContainsString(self::SERVICIO_B, $htmlA, 'La página de A no muestra datos de B');

        foreach ([self::SERVICIO_A, self::EMPLEADA_A, 'Spa Completo', '573001112233'] as $datoDeA) {
            $this->assertStringNotContainsString($datoDeA, $htmlB, "La página de B no puede exponer datos de A: $datoDeA");
        }

        $this->getJson('publico/spa-completo/servicios')->assertOk();
        $this->getJson('publico/spa-basico/servicios')->assertNotFound();
        $this->assertStringNotContainsString(self::SERVICIO_A, $this->getJson('publico/spa-basico/informacion')->getContent());
    }

    /* ================= 10) GUARDIÁN DE RUTAS ================= */

    public function test_toda_ruta_de_banners_frase_y_solicitudes_lleva_el_modulo(): void
    {
        $revisadas = 0;

        foreach (Route::getRoutes() as $ruta) {
            $uri = $ruta->uri();

            if (str_starts_with($uri, 'request/banner/')
                || in_array($uri, ['request/negocio/guardar-frase', 'request/negocio/quitar-frase', 'request/reserva/solicitudes-pendientes'], true)) {
                $this->assertContains('verificar.modulo:pagina_publica', $ruta->gatherMiddleware(), "La ruta $uri no lleva verificar.modulo:pagina_publica");
                $revisadas++;
            }
        }

        $this->assertSame(7, $revisadas, 'Se esperaban 4 rutas de banners, 2 de frase y 1 de solicitudes');
    }
}
