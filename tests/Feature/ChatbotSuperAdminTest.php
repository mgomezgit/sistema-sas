<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Service\SvcChatbot;
use App\Service\SvcModulo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Uso del chatbot y límite mensual por negocio, en el panel del super admin.
 *
 * HTTP real con withSession(), reloj congelado y SQLite en memoria. Ninguna
 * prueba llama a la IA: solo se leen y escriben contadores y límites.
 *
 * Reloj congelado: miércoles 2026-06-10 a las 12:00 (mes 2026-06).
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * Las pruebas pasaron a la primera; se mutó el código una mutación a la vez,
 * corriendo ChatbotSuperAdminTest, SuperAdminTest, SuperAdminPantallasTest y
 * ChatbotTest, y restaurando cada vez (después: todo en verde).
 *
 * S1 — una ruta nueva (chatbot/limite) con ->withoutMiddleware('solo.superadmin'):
 *      5 FAILED — la matriz de permisos de esta clase, "el admin no puede
 *      fijarse su límite", el guardián de esta clase, y la matriz y el
 *      guardián de SuperAdminTest. HALLAZGO: el guardián no cayó la primera
 *      vez (solo las pruebas de comportamiento), porque gatherMiddleware()
 *      sigue listando un middleware excluido con withoutMiddleware(). Se
 *      corrigió descontando excludedMiddleware(), también en el guardián que
 *      ya existía en SuperAdminTest (tenía el mismo hueco).
 * S2 — el uso devuelve un campo fuera de la lista blanca: 1 FAILED —
 *      test_el_uso_devuelve_solo_los_campos_de_la_lista_blanca.
 * S3 — sin la validación del máximo (10000) del límite: 1 FAILED —
 *      test_un_limite_invalido_se_rechaza_sin_cambiar_nada (10001, "10001",
 *      "99999999999"...).
 * S4 — sin rechazar un mes futuro: 1 FAILED — test_un_mes_invalido_se_rechaza.
 * S5 — el UPDATE del límite sin el WHERE del negocio (se aplicaba a TODOS):
 *      2 FAILED — "cambiar el límite de A no cambia nada de B" y "un
 *      tenant_id sobrante no cambia el negocio objetivo".
 * S6 — el límite vacío se guardaba como 0 en vez de null: 1 FAILED — la del
 *      log (de "por defecto" a 5 / de 5 a "por defecto"); y de paso cambiaba
 *      el comportamiento real: vacío debe volver al valor por defecto.
 * S7 — el límite propio 0 dejaba de bloquear (0 tratado como "sin límite"):
 *      3 FAILED — "el cero bloquea y vacío vuelve al defecto", "cambiar el
 *      límite de A no cambia B" y "una sola regla". Las 52 pruebas que ya
 *      tenía ChatbotTest NO lo detectaban: este caso no estaba cubierto.
 * S8 — el modal pintaba con innerHTML: 1 FAILED —
 *      test_el_codigo_de_las_pantallas_nuevas_pinta_todo_como_texto.
 *
 * Además: ChatbotTest::test_guardian_de_rutas_del_chatbot buscaba cualquier
 * ruta con "chatbot" y falló al aparecer las del panel; se acotó para excluir
 * request/superadmin/chatbot/* (que tienen su guardián aquí) sin aflojar nada
 * de las rutas públicas.
 */
class ChatbotSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    const PAYLOAD = '<img src=x onerror=alert(1)>';

    const CLAVES_FILA = ['id_negocio', 'nombre_negocio', 'modulo_activo', 'mensajes', 'tokens_entrada', 'tokens_salida', 'limite_efectivo', 'limite_propio'];

    private int $negocioA;

    private int $negocioB;

    private int $negocioC;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));

        config(['chatbot.habilitado' => true, 'chatbot.limite_mensual' => 100, 'chatbot.tope_global_mensual' => 1500]);

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = $this->crearNegocio('Negocio A', 'negocio-a');
        $this->negocioB = $this->crearNegocio('Negocio B', 'negocio-b');
        $this->negocioC = $this->crearNegocio('Negocio C', 'negocio-c');

        (new SvcModulo)->activarModulo($this->negocioA, 'chatbot', 'test');
        (new SvcModulo)->activarModulo($this->negocioB, 'chatbot', 'test');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre, string $slug): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre, 'slug' => $slug, 'rubro' => 'spa',
            'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01 08:00:00', 'estado' => 1,
        ]);
    }

    private function sesion(?int $tenantId, int $idRol, bool $conClave = true): array
    {
        $sesion = ['id_usuario' => 99, 'usuario' => 'prueba', 'nombre_usuario' => 'Prueba', 'tenant_id' => $tenantId, 'id_rol' => $idRol];

        if ($conClave) {
            $sesion['app_sesion'] = VerificarSesion::CLAVE_SESION;
        }

        return $sesion;
    }

    private function superAdmin(): array
    {
        return $this->sesion(null, 3);
    }

    private function llamar(string $metodo, string $url, array $cuerpo = [], ?array $sesion = null)
    {
        $this->flushSession();
        $prueba = $sesion === null ? $this : $this->withSession($sesion);

        return $metodo === 'GET'
            ? $prueba->getJson($url.($cuerpo ? '?'.http_build_query($cuerpo) : ''))
            : $prueba->postJson($url, $cuerpo);
    }

    private function uso(array $consulta = [], ?array $sesion = null)
    {
        return $this->llamar('GET', 'request/superadmin/chatbot/uso', $consulta, $sesion ?? $this->superAdmin());
    }

    private function filaUso(int $idNegocio, array $consulta = []): array
    {
        $respuesta = $this->uso($consulta + ['id_negocio' => $idNegocio])->assertJsonPath('error', 0);

        return $respuesta->json('data.negocios.0');
    }

    private function fijarLimite(int|string $idNegocio, $limite, ?array $sesion = null)
    {
        return $this->llamar('POST', 'request/superadmin/chatbot/limite', ['id_negocio' => $idNegocio, 'limite' => $limite], $sesion ?? $this->superAdmin());
    }

    private function limiteEnBase(int $idNegocio): ?int
    {
        $valor = DB::table('negocios')->where('id_negocio', $idNegocio)->value('chatbot_limite_mensual');

        return $valor === null ? null : (int) $valor;
    }

    private function sembrarUso(int $idNegocio, int $mensajes, int $entrada = 0, int $salida = 0, string $mes = '2026-06'): void
    {
        DB::table('chatbot_uso')->updateOrInsert(
            ['tenant_id' => $idNegocio, 'mes' => $mes],
            ['mensajes' => $mensajes, 'tokens_entrada' => $entrada, 'tokens_salida' => $salida, 'fecha_registro' => '2026-06-01 08:00:00']
        );
    }

    private function fotoDelUso(): array
    {
        return DB::table('chatbot_uso')->orderBy('id_chatbot_uso')->get()->map(fn ($f) => (array) $f)->all();
    }

    /* ================= PERMISOS ================= */

    private function endpointsNuevos(): array
    {
        return [
            ['GET', 'request/superadmin/chatbot/uso', []],
            ['GET', 'request/superadmin/chatbot/resumen', []],
            ['POST', 'request/superadmin/chatbot/limite', ['id_negocio' => $this->negocioA, 'limite' => 3]],
        ];
    }

    public function test_anonimo_empleado_y_admin_de_negocio_son_rechazados_y_nada_cambia(): void
    {
        $this->sembrarUso($this->negocioA, 4, 10, 2);

        $rechazadas = [
            'anónimo' => null,
            'empleado' => $this->sesion($this->negocioA, 2),
            'admin de negocio' => $this->sesion($this->negocioA, 1),
            'tenant null sin app_sesion' => $this->sesion(null, 3, false),
            'app_sesion + tenant null + rol admin' => $this->sesion(null, 1),
        ];

        foreach ($this->endpointsNuevos() as [$metodo, $url, $cuerpo]) {
            foreach ($rechazadas as $quien => $sesion) {
                $limitesAntes = DB::table('negocios')->orderBy('id_negocio')->pluck('chatbot_limite_mensual', 'id_negocio')->all();
                $usoAntes = $this->fotoDelUso();

                $respuesta = $this->llamar($metodo, $url, $cuerpo, $sesion);

                $this->assertSame(1, $respuesta->json('error'), "$quien NO debe poder usar $metodo $url");
                $this->assertSame([], (array) $respuesta->json('data'), "$quien no debe recibir datos de $url");
                $this->assertSame($limitesAntes, DB::table('negocios')->orderBy('id_negocio')->pluck('chatbot_limite_mensual', 'id_negocio')->all(), "$quien cambió un límite con $url");
                $this->assertSame($usoAntes, $this->fotoDelUso());
            }
        }

        foreach ($this->endpointsNuevos() as [$metodo, $url, $cuerpo]) {
            $this->assertSame(0, $this->llamar($metodo, $url, $cuerpo, $this->superAdmin())->json('error'), "El super admin debe poder usar $metodo $url");
        }
    }

    /** Un admin de negocio no puede fijarse su propio límite, ni el de otro, ni siquiera con su id. */
    public function test_el_admin_de_un_negocio_no_puede_fijarse_su_propio_limite(): void
    {
        $this->fijarLimite($this->negocioA, 1, $this->sesion($this->negocioA, 1))->assertJsonPath('error', 1);
        $this->fijarLimite($this->negocioB, 1, $this->sesion($this->negocioA, 1))->assertJsonPath('error', 1);
        $this->fijarLimite($this->negocioA, 99999, $this->sesion($this->negocioA, 1))->assertJsonPath('error', 1);

        $this->assertNull($this->limiteEnBase($this->negocioA));
        $this->assertNull($this->limiteEnBase($this->negocioB));
    }

    public function test_toda_ruta_nueva_del_panel_lleva_sesion_y_solo_superadmin(): void
    {
        $encontradas = [];

        foreach (Route::getRoutes() as $ruta) {
            if (str_starts_with($ruta->uri(), 'request/superadmin/chatbot')) {
                // Se descuentan los excluidos: una ruta con ->withoutMiddleware('solo.superadmin')
                // sigue LISTÁNDOLO en gatherMiddleware() pero ya no lo ejecuta.
                $encontradas[$ruta->uri()] = array_diff($ruta->gatherMiddleware(), $ruta->excludedMiddleware());
            }
        }

        $this->assertEqualsCanonicalizing(
            ['request/superadmin/chatbot/uso', 'request/superadmin/chatbot/resumen', 'request/superadmin/chatbot/limite'],
            array_keys($encontradas)
        );

        foreach ($encontradas as $uri => $middlewares) {
            $this->assertContains('solo.superadmin', $middlewares, "$uri sin solo.superadmin");
            $this->assertContains('sesion.activa', $middlewares, "$uri sin sesion.activa");
        }
    }

    /* ================= USO ================= */

    public function test_el_uso_devuelve_solo_los_campos_de_la_lista_blanca(): void
    {
        $this->sembrarUso($this->negocioA, 7, 1500, 300);
        $usuario = DB::table('usuarios')->insertGetId([
            'tenant_id' => $this->negocioA, 'id_rol' => 1, 'usuario' => 'admin.a', 'nombre' => 'Admin A', 'email' => 'secreto@negocio-a.test',
            'clave' => bcrypt('x'), 'usuario_registra' => 'test', 'fecha_registro' => '2026-06-01', 'estado' => 1,
        ]);
        Cache::put('chatbot:conversacion:'.$this->negocioA.':'.str_repeat('a', 40), ['turnos' => [['pregunta' => 'pregunta confidencial', 'respuesta' => 'respuesta confidencial']]], 600);

        $respuesta = $this->uso()->assertOk()->assertJsonPath('error', 0);
        $datos = $respuesta->json('data');

        $this->assertEqualsCanonicalizing(['mes', 'limite_por_defecto', 'negocios'], array_keys($datos));
        $this->assertCount(3, $datos['negocios'], 'Todos los negocios, con o sin uso');

        foreach ($datos['negocios'] as $fila) {
            $this->assertEqualsCanonicalizing(self::CLAVES_FILA, array_keys($fila), 'Campo fuera de la lista blanca');
            $this->assertIsInt($fila['id_negocio']);
            $this->assertIsBool($fila['modulo_activo']);
        }

        $contenido = $respuesta->getContent();
        foreach (['conversacion', 'confidencial', 'respuesta', 'pregunta', 'id_usuario', 'secreto@negocio-a.test', 'clave', 'email', 'turnos'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $contenido, "El uso expone algo que no es un número: $prohibido");
        }
        $this->assertStringContainsString('application/json', $respuesta->headers->get('Content-Type'));
        $this->assertGreaterThan(0, $usuario);
    }

    public function test_los_valores_del_uso_y_del_limite_son_los_del_negocio(): void
    {
        $this->sembrarUso($this->negocioA, 7, 1500, 300);
        $this->sembrarUso($this->negocioB, 2, 50, 10);
        DB::table('negocios')->where('id_negocio', $this->negocioB)->update(['chatbot_limite_mensual' => 30]);

        $filas = collect($this->uso()->json('data.negocios'))->keyBy('id_negocio');

        $this->assertEquals(['mensajes' => 7, 'tokens_entrada' => 1500, 'tokens_salida' => 300, 'limite_efectivo' => 100, 'limite_propio' => null, 'modulo_activo' => true], collect($filas[$this->negocioA])->only(['mensajes', 'tokens_entrada', 'tokens_salida', 'limite_efectivo', 'limite_propio', 'modulo_activo'])->all());
        $this->assertEquals(['mensajes' => 2, 'tokens_entrada' => 50, 'tokens_salida' => 10, 'limite_efectivo' => 30, 'limite_propio' => 30, 'modulo_activo' => true], collect($filas[$this->negocioB])->only(['mensajes', 'tokens_entrada', 'tokens_salida', 'limite_efectivo', 'limite_propio', 'modulo_activo'])->all());
    }

    public function test_un_negocio_sin_fila_de_uso_devuelve_ceros_no_un_error(): void
    {
        $fila = $this->filaUso($this->negocioC);

        $this->assertEquals(['mensajes' => 0, 'tokens_entrada' => 0, 'tokens_salida' => 0, 'modulo_activo' => false, 'limite_efectivo' => 100, 'limite_propio' => null], collect($fila)->only(['mensajes', 'tokens_entrada', 'tokens_salida', 'modulo_activo', 'limite_efectivo', 'limite_propio'])->all());
    }

    public function test_el_cambio_de_mes_abre_un_contador_nuevo_y_un_mes_anterior_muestra_sus_datos(): void
    {
        $this->sembrarUso($this->negocioA, 7, 1500, 300, '2026-06');
        $this->sembrarUso($this->negocioA, 40, 9000, 800, '2026-05');

        $this->assertSame(7, $this->filaUso($this->negocioA)['mensajes']);
        $this->assertSame(40, $this->filaUso($this->negocioA, ['mes' => '2026-05'])['mensajes'], 'Un mes anterior válido muestra los datos de ese mes');
        $this->assertSame('2026-05', $this->uso(['mes' => '2026-05'])->json('data.mes'));

        Carbon::setTestNow(Carbon::parse('2026-07-01 09:00:00'));

        $julio = $this->filaUso($this->negocioA);
        $this->assertSame(0, $julio['mensajes'], 'El mes nuevo arranca en cero');
        $this->assertSame(0, $julio['tokens_entrada']);
        $this->assertSame(7, $this->filaUso($this->negocioA, ['mes' => '2026-06'])['mensajes'], 'El mes anterior sigue intacto');
        $this->assertSame('2026-07', $this->uso()->json('data.mes'));
    }

    public static function mesesInvalidos(): array
    {
        return [
            'mes 13' => ['2026-13'],
            'mes 00' => ['2026-00'],
            'texto' => ['junio'],
            'un dígito' => ['2026-6'],
            'con día' => ['2026-06-01'],
            'futuro' => ['2026-07'],
            'año futuro' => ['2030-01'],
            'inyección' => ["2026-06' OR '1'='1"],
            'arreglo' => [['2026-06']],
        ];
    }

    #[DataProvider('mesesInvalidos')]
    public function test_un_mes_invalido_se_rechaza(mixed $mes): void
    {
        $this->sembrarUso($this->negocioA, 7);

        $this->uso(['mes' => $mes])->assertJsonPath('error', 1)->assertJsonPath('data', []);
    }

    public function test_el_filtro_por_negocio_trae_uno_y_un_negocio_inexistente_se_rechaza(): void
    {
        $this->assertCount(1, $this->uso(['id_negocio' => $this->negocioA])->json('data.negocios'));
        $this->uso(['id_negocio' => 999999])->assertJsonPath('error', 1);
        $this->uso(['id_negocio' => 'abc'])->assertJsonPath('error', 1);
    }

    /* ================= RESUMEN ================= */

    public function test_el_resumen_trae_solo_numeros_agregados(): void
    {
        $this->sembrarUso($this->negocioA, 7, 1500, 300);
        $this->sembrarUso($this->negocioB, 3, 500, 100);
        $this->sembrarUso($this->negocioA, 99, 99999, 99999, '2026-05');
        (new SvcModulo)->activarModulo($this->negocioC, 'chatbot', 'test');
        (new SvcModulo)->desactivarModulo($this->negocioC, 'chatbot');

        $respuesta = $this->llamar('GET', 'request/superadmin/chatbot/resumen', [], $this->superAdmin())->assertJsonPath('error', 0);

        $this->assertSame([
            'mes' => '2026-06', 'habilitado' => true, 'mensajes_del_mes' => 10, 'tope_global_mensual' => 1500,
            'tokens_entrada' => 2000, 'tokens_salida' => 400, 'negocios_con_modulo' => 2,
        ], $respuesta->json('data.resumen'));

        foreach (['Negocio A', 'Negocio B', 'nombre_negocio', 'id_negocio', 'slug'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $respuesta->getContent(), 'El resumen debe ser solo agregados');
        }

        Carbon::setTestNow(Carbon::parse('2026-07-02 10:00:00'));
        $this->assertSame(0, $this->llamar('GET', 'request/superadmin/chatbot/resumen', [], $this->superAdmin())->json('data.resumen.mensajes_del_mes'));
    }

    /* ================= LÍMITE: VALIDACIÓN ================= */

    public static function limitesValidos(): array
    {
        return [
            'null (por defecto)' => [null, null],
            'vacío (por defecto)' => ['', null],
            'cero (bloqueado)' => [0, 0],
            'cero como texto' => ['0', 0],
            'un entero' => [5, 5],
            'entero como texto' => ['25', 25],
            'el máximo' => [10000, 10000],
            'el máximo como texto' => ['10000', 10000],
        ];
    }

    #[DataProvider('limitesValidos')]
    public function test_un_limite_valido_se_guarda(mixed $enviado, ?int $esperado): void
    {
        DB::table('negocios')->where('id_negocio', $this->negocioA)->update(['chatbot_limite_mensual' => 42]);

        $this->fijarLimite($this->negocioA, $enviado)->assertJsonPath('error', 0);

        $this->assertSame($esperado, $this->limiteEnBase($this->negocioA));
    }

    public static function limitesInvalidos(): array
    {
        return [
            'negativo' => [-1],
            'negativo como texto' => ['-1'],
            'decimal' => [1.5],
            'decimal como texto' => ['1.5'],
            'texto' => ['abc'],
            'notación científica' => ['1e3'],
            'más del máximo' => [10001],
            'más del máximo como texto' => ['10001'],
            'enorme' => ['99999999999'],
            'booleano verdadero' => [true],
            'booleano falso' => [false],
            'arreglo' => [[5]],
            'objeto' => [['a' => 1]],
            'con espacios internos' => ['1 0'],
            'con signo' => ['+5'],
            'con coma' => ['1,5'],
        ];
    }

    #[DataProvider('limitesInvalidos')]
    public function test_un_limite_invalido_se_rechaza_sin_cambiar_nada(mixed $enviado): void
    {
        DB::table('negocios')->where('id_negocio', $this->negocioA)->update(['chatbot_limite_mensual' => 42]);
        $this->sembrarUso($this->negocioA, 5, 10, 2);
        $usoAntes = $this->fotoDelUso();
        $limitesAntes = DB::table('negocios')->orderBy('id_negocio')->pluck('chatbot_limite_mensual', 'id_negocio')->all();

        $this->fijarLimite($this->negocioA, $enviado)->assertJsonPath('error', 1);

        $this->assertSame($limitesAntes, DB::table('negocios')->orderBy('id_negocio')->pluck('chatbot_limite_mensual', 'id_negocio')->all());
        $this->assertSame($usoAntes, $this->fotoDelUso());
    }

    public function test_sin_la_clave_limite_o_con_un_negocio_invalido_se_rechaza_sin_cambiar_nada(): void
    {
        DB::table('negocios')->where('id_negocio', $this->negocioA)->update(['chatbot_limite_mensual' => 42]);

        $this->llamar('POST', 'request/superadmin/chatbot/limite', ['id_negocio' => $this->negocioA], $this->superAdmin())->assertJsonPath('error', 1);
        $this->fijarLimite(999999, 5)->assertJsonPath('error', 1);
        $this->llamar('POST', 'request/superadmin/chatbot/limite', ['limite' => 5], $this->superAdmin())->assertJsonPath('error', 1);
        $this->fijarLimite('abc', 5)->assertJsonPath('error', 1);
        $this->llamar('POST', 'request/superadmin/chatbot/limite', ['id_negocio' => [$this->negocioA], 'limite' => 5], $this->superAdmin())->assertJsonPath('error', 1);

        $this->assertSame(42, $this->limiteEnBase($this->negocioA));
        $this->assertNull($this->limiteEnBase($this->negocioB));
        $this->assertNull($this->limiteEnBase($this->negocioC));
    }

    /* ================= LÍMITE: EFECTO EN EL CHATBOT ================= */

    private function disponiblePorHttp(string $slug): bool
    {
        $this->flushSession();

        return $this->getJson('publico/'.$slug.'/chatbot/estado')->json('data.disponible');
    }

    public function test_el_limite_propio_manda_sobre_el_valor_por_defecto_el_cero_bloquea_y_vacio_vuelve_al_defecto(): void
    {
        $this->sembrarUso($this->negocioA, 5);
        $this->assertTrue($this->disponiblePorHttp('negocio-a'), '5 de 100 por defecto');

        $this->fijarLimite($this->negocioA, 5)->assertJsonPath('error', 0);
        $this->assertFalse($this->disponiblePorHttp('negocio-a'), '5 de 5: agotado');
        $this->assertFalse((new SvcChatbot)->disponible($this->negocioA));

        $this->fijarLimite($this->negocioA, 6);
        $this->assertTrue($this->disponiblePorHttp('negocio-a'), '5 de 6');

        $this->fijarLimite($this->negocioA, 0)->assertJsonPath('error', 0);
        $this->assertFalse($this->disponiblePorHttp('negocio-a'), 'Con 0 queda bloqueado aunque no haya usado nada del mes');
        $this->assertSame(0, $this->filaUso($this->negocioA)['limite_efectivo']);

        $this->fijarLimite($this->negocioA, null);
        $this->assertNull($this->limiteEnBase($this->negocioA));
        $this->assertTrue($this->disponiblePorHttp('negocio-a'), 'Vacío vuelve al valor por defecto');
        $this->assertSame(100, $this->filaUso($this->negocioA)['limite_efectivo']);

        // Un límite propio mayor que el de config también manda.
        config(['chatbot.limite_mensual' => 3]);
        $this->assertFalse($this->disponiblePorHttp('negocio-a'), '5 de 3 por defecto');
        $this->fijarLimite($this->negocioA, 50);
        $this->assertTrue($this->disponiblePorHttp('negocio-a'), '5 de 50 propio');
    }

    public function test_cambiar_el_limite_de_A_no_cambia_nada_de_B(): void
    {
        $this->sembrarUso($this->negocioB, 9, 90, 9);
        $antesB = collect($this->filaUso($this->negocioB))->all();
        $usoAntes = $this->fotoDelUso();

        $this->fijarLimite($this->negocioA, 0)->assertJsonPath('error', 0);

        $this->assertSame($antesB, $this->filaUso($this->negocioB));
        $this->assertNull($this->limiteEnBase($this->negocioB));
        $this->assertNull($this->limiteEnBase($this->negocioC));
        $this->assertTrue($this->disponiblePorHttp('negocio-b'));
        $this->assertFalse($this->disponiblePorHttp('negocio-a'));
        $this->assertSame($usoAntes, $this->fotoDelUso());
    }

    public function test_editar_el_limite_no_cambia_contadores_ni_historial(): void
    {
        $this->sembrarUso($this->negocioA, 7, 1500, 300, '2026-06');
        $this->sembrarUso($this->negocioA, 40, 9000, 800, '2026-05');
        $antes = $this->fotoDelUso();

        foreach ([5, 0, null, 10000] as $limite) {
            $this->fijarLimite($this->negocioA, $limite)->assertJsonPath('error', 0);
            $this->assertSame($antes, $this->fotoDelUso(), "Cambió un contador al fijar el límite en ".var_export($limite, true));
        }
    }

    public function test_el_limite_efectivo_sale_de_una_sola_regla(): void
    {
        $this->assertSame(100, SvcChatbot::limiteEfectivo(null));
        $this->assertSame(0, SvcChatbot::limiteEfectivo(0));
        $this->assertSame(8, SvcChatbot::limiteEfectivo('8'));

        // La regla está escrita una sola vez en todo app/: nadie más lee el valor por defecto.
        $lecturas = [];
        foreach (File::allFiles(app_path()) as $archivo) {
            if (str_contains(File::get($archivo->getPathname()), "config('chatbot.limite_mensual')")) {
                $lecturas[] = $archivo->getFilename();
            }
        }
        $this->assertSame(['SvcChatbot.php'], $lecturas);
        $this->assertStringContainsString('SvcChatbot::limiteEfectivo(', File::get(app_path('Service/SvcSuperAdmin.php')));
    }

    public function test_cada_cambio_de_limite_queda_en_el_log_con_quien_negocio_y_valores(): void
    {
        $canal = Mockery::spy();
        Log::shouldReceive('channel')->with('database')->andReturn($canal);

        $this->fijarLimite($this->negocioA, 5)->assertJsonPath('error', 0);
        $this->fijarLimite($this->negocioA, null)->assertJsonPath('error', 0);

        foreach ([['por defecto', '5'], ['5', 'por defecto']] as [$de, $a]) {
            $canal->shouldHaveReceived('info')->withArgs(function ($mensaje) use ($de, $a) {
                return is_string($mensaje)
                    && str_contains($mensaje, 'limite mensual del chatbot del negocio '.$this->negocioA.' cambiado de '.$de.' a '.$a)
                    && str_contains($mensaje, 'prueba (id 99)');
            })->once();
        }
    }

    /* ================= ESCAPE EN LAS PANTALLAS ================= */

    public function test_un_nombre_con_html_viaja_como_dato_y_las_pantallas_lo_pintan_como_texto(): void
    {
        // La marca final distingue este nombre de los comentarios del código que ya citan el payload.
        $nombre = self::PAYLOAD.' "comillas" y \'simples\' MARCAXSS';
        $id = $this->crearNegocio($nombre, 'negocio-xss');

        // El endpoint devuelve el nombre tal cual (JSON): el escape es de quien lo pinta.
        $fila = collect($this->uso()->json('data.negocios'))->firstWhere('id_negocio', $id);
        $this->assertSame($nombre, $fila['nombre_negocio']);

        foreach (['backoffice/superadmin/negocios', 'backoffice/superadmin/dashboard'] as $pantalla) {
            $html = $this->withSession($this->superAdmin())->get($pantalla)->assertOk()->getContent();
            $this->assertStringNotContainsString('MARCAXSS', $html, "$pantalla trae el nombre del negocio dentro del HTML servido: se pinta por JS con escape");
        }
    }

    public function test_el_codigo_de_las_pantallas_nuevas_pinta_todo_como_texto(): void
    {
        $negocios = File::get(resource_path('views/app/superadmin/negocios.blade.php'));
        $dashboard = File::get(resource_path('views/app/superadmin/dashboard.blade.php'));

        // Bloque del modal: desde cargarBloqueChatbot hasta el manejador de Guardar.
        $inicio = strpos($negocios, 'function crearElemento');
        $fin = strpos($negocios, "jQuery('#lista-modulos-negocio').on('click', '#btn-guardar-limite-chatbot'");
        $bloqueModal = substr($negocios, $inicio, $fin - $inicio);
        $this->assertNotEmpty($bloqueModal);

        // Tarjeta del dashboard: de crearNodo a cargarResumenChatbot.
        $bloqueTarjeta = substr($dashboard, strpos($dashboard, 'function crearNodo'), strpos($dashboard, 'jQuery(document).ready') - strpos($dashboard, 'function crearNodo'));

        foreach (['modal' => $bloqueModal, 'tarjeta' => $bloqueTarjeta] as $donde => $codigo) {
            foreach (['innerHTML', '.html(', 'insertAdjacentHTML', 'document.write', 'eval('] as $prohibido) {
                $this->assertStringNotContainsString($prohibido, $codigo, "El bloque $donde usa $prohibido");
            }
            $this->assertStringContainsString('textContent = texto', $codigo);
        }

        // El bloque de uso del chatbot se arma con createElement/textContent
        // (antes era una cadena HTML; se refactorizó al construir el modal de
        // vigencia de módulos, que pinta todo por DOM). Sigue sin HTML crudo.
        $this->assertStringContainsString("crearElemento('div', 'bloque-chatbot-uso')", $negocios);
        $this->assertStringNotContainsString('fila.nombre_negocio', $bloqueModal);
    }

    public function test_las_pantallas_traen_los_contenedores_y_las_rutas_del_chatbot(): void
    {
        $negocios = $this->withSession($this->superAdmin())->get('backoffice/superadmin/negocios')->assertOk()->getContent();
        $dashboard = $this->withSession($this->superAdmin())->get('backoffice/superadmin/dashboard')->assertOk()->getContent();

        foreach (['request/superadmin/chatbot/uso', 'request/superadmin/chatbot/limite', 'btn-guardar-limite-chatbot', 'limite-chatbot', "'Límite mensual'"] as $pieza) {
            $this->assertStringContainsString($pieza, $negocios);
        }
        foreach (['contenedor-chatbot-plataforma', 'request/superadmin/chatbot/resumen'] as $pieza) {
            $this->assertStringContainsString($pieza, $dashboard);
        }
    }

    public function test_las_pantallas_nuevas_no_tienen_hexadecimales(): void
    {
        foreach (['negocios', 'dashboard'] as $vista) {
            $contenido = File::get(resource_path("views/app/superadmin/$vista.blade.php"));
            $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b(?![\w-])/', preg_replace('/\{\{--.*?--\}\}/s', '', $contenido)), "Hexadecimal suelto en superadmin/$vista");
        }
    }

    /* ================= TENANT_ID DEL CUERPO ================= */

    /** El id_negocio del cuerpo es legítimo aquí (el super admin elige), pero un tenant_id suelto no cambia el objetivo. */
    public function test_un_tenant_id_sobrante_no_cambia_el_negocio_objetivo(): void
    {
        $this->llamar('POST', 'request/superadmin/chatbot/limite', ['id_negocio' => $this->negocioA, 'limite' => 4, 'tenant_id' => $this->negocioB], $this->superAdmin())->assertJsonPath('error', 0);

        $this->assertSame(4, $this->limiteEnBase($this->negocioA));
        $this->assertNull($this->limiteEnBase($this->negocioB));
    }
}
