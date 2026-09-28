<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Pantallas del panel del super admin: backoffice/superadmin/dashboard y
 * backoffice/superadmin/negocios, más el tema fijo y el sidebar.
 *
 * ================= PRUEBA DE MUTACIÓN =================
 *
 * 1. En routes/web.php se sacó backoffice/superadmin/dashboard del grupo
 *    protegido con ['sesion.activa', 'solo.superadmin'] y se le dejó SOLO
 *    'sesion.activa', en un grupo aparte (negocios se quedó protegida).
 * 2. Se corrieron juntas esta clase y SuperAdminTest (el guardián original
 *    vive ahí, y ambas comparten la misma suite):
 *      php artisan test --filter="SuperAdminPantallasTest|SuperAdminTest"
 *    Resultado: 24 tests, 21 passed, 3 FAILED:
 *      - test_matriz_de_permisos_por_pantalla: "Expected response status
 *        code [302] but received 200" (el admin de negocio entró al
 *        dashboard de plataforma sin ser super admin).
 *      - test_el_guardian_de_rutas_del_panel_incluye_las_pantallas_nuevas
 *        (el guardián de ESTA clase): nombró exactamente la ruta desprotegida.
 *      - SuperAdminTest::test_toda_ruta_del_panel_lleva_solo_superadmin (el
 *        guardián original, de la tarea anterior): también la detectó, sin
 *        que hiciera falta tocarlo — ya cubría backoffice/superadmin/*.
 * 3. Se restauró la ruta tal cual estaba.
 * 4. Se volvió a ejecutar: 24 passed.
 */
class SuperAdminPantallasTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = DB::table('negocios')->insertGetId([
            'nombre_negocio' => 'Negocio A',
            'slug' => 'negocio-a',
            'rubro' => 'spa',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    /* ================= AYUDANTES ================= */

    private function sesion(?int $tenantId, int $idRol): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 1,
            'usuario' => 'prueba',
            'nombre_usuario' => 'Prueba',
            'tenant_id' => $tenantId,
            'id_rol' => $idRol,
        ];
    }

    private function sesionSuperAdmin(): array
    {
        return $this->sesion(null, 3);
    }

    private function sesionAdmin(): array
    {
        return $this->sesion($this->negocioA, 1);
    }

    private function sesionEmpleado(): array
    {
        return $this->sesion($this->negocioA, 2);
    }

    private function pantallas(): array
    {
        return [
            'backoffice/superadmin/dashboard',
            'backoffice/superadmin/negocios',
        ];
    }

    /* ================= 1) MATRIZ DE PERMISOS POR PANTALLA ================= */

    /** Ver la nota de MUTACIÓN en el encabezado de la clase. */
    public function test_matriz_de_permisos_por_pantalla(): void
    {
        foreach ($this->pantallas() as $pantalla) {
            // withSession() MEZCLA con la sesión de la llamada anterior si no
            // se vacía antes: sin esto, el caso "anónimo" heredaría la
            // sesión que dejó la última llamada del ciclo previo (mismo
            // defecto ya corregido antes en SuperAdminTest).
            $this->flushSession();
            $this->get($pantalla)->assertStatus(302);

            $this->flushSession();
            $this->withSession($this->sesionEmpleado())->get($pantalla)->assertStatus(302);

            $this->flushSession();
            $this->withSession($this->sesionAdmin())->get($pantalla)->assertStatus(302);

            $this->flushSession();
            $this->withSession($this->sesionSuperAdmin())->get($pantalla)->assertStatus(200);
        }
    }

    /* ================= 2) DASHBOARD: REDIRECCIÓN DESDE backoffice/dashboard ================= */

    public function test_backoffice_dashboard_redirige_al_super_admin_a_su_propio_dashboard(): void
    {
        $this->withSession($this->sesionSuperAdmin())
            ->get('backoffice/dashboard')
            ->assertRedirect(url('backoffice/superadmin/dashboard'));
    }

    public function test_backoffice_dashboard_no_redirige_a_admin_ni_empleado(): void
    {
        $this->withSession($this->sesionAdmin())->get('backoffice/dashboard')->assertStatus(200);
        $this->withSession($this->sesionEmpleado())->get('backoffice/mis-citas')->assertStatus(200);
    }

    /* ================= 3) SIN MARCADORES DEL DASHBOARD DE SPA ================= */

    public function test_las_pantallas_del_panel_no_contienen_marcadores_del_dashboard_de_spa(): void
    {
        $marcadoresDeSpa = ['Reservas de hoy', 'Ocupación', 'Ingresos del mes'];

        foreach ($this->pantallas() as $pantalla) {
            $html = $this->withSession($this->sesionSuperAdmin())->get($pantalla)->getContent();

            foreach ($marcadoresDeSpa as $marcador) {
                $this->assertStringNotContainsString($marcador, $html, "$pantalla no puede mostrar \"$marcador\"");
            }
        }
    }

    /* ================= 4) TEMA FIJO DE PLATAFORMA ================= */

    public function test_el_body_del_super_admin_lleva_la_clase_de_tema_de_plataforma(): void
    {
        $html = $this->withSession($this->sesionSuperAdmin())->get('backoffice/superadmin/dashboard')->getContent();

        $this->assertMatchesRegularExpression('/<body class="[^"]*tema-plataforma[^"]*"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<body class="[^"]*modo-(oscuro|claro)[^"]*"/', $html);
    }

    /**
     * Guardián de una vulnerabilidad "silenciosa" real que se encontró con un
     * navegador real: el comentario que documenta la regla body.tema-plataforma
     * escribía, como ejemplo, las clases modo- y acento- separadas por un
     * asterisco y una barra sin espacio — esos dos caracteres juntos CIERRAN
     * un comentario CSS antes de tiempo.
     * El navegador no lanza ningún error: descarta en silencio la regla que
     * quedaba corrompida y sigue con el resto de la hoja de estilos, así que
     * el super admin cargaba con --bg-body y --accent vacíos (fondo blanco,
     * sin el tema fijo) sin que ninguna petición HTTP lo delatara. PHPUnit no
     * ejecuta CSS, así que esto verifica la causa raíz por texto: que el
     * bloque de comentario que antecede a la regla no contenga un cierre de
     * comentario antes del que le corresponde de verdad.
     *
     * MUTACIÓN ejecutada: se reintrodujo el mismo cierre prematuro dentro
     * del comentario. Resultado real: 13 tests, 12 passed, 1 FAILED — esta
     * prueba, exactamente. Restaurado: 13 passed.
     */
    public function test_el_comentario_sobre_tema_de_plataforma_no_corrompe_su_propia_regla(): void
    {
        $html = $this->withSession($this->sesionSuperAdmin())->get('backoffice/superadmin/dashboard')->getContent();

        // Arranca DESPUÉS del primer comentario de una sola línea (el título
        // "---------- Tema fijo de plataforma ---------- "), que trae su
        // propio cierre legítimo: lo que importa es el bloque explicativo de
        // varias líneas que le sigue justo antes de la regla.
        $inicio = strpos($html, 'El super admin no elige tema');
        $finSelector = strpos($html, 'body.tema-plataforma {', $inicio);

        $this->assertNotFalse($inicio, 'No se encontró el comentario del tema de plataforma');
        $this->assertNotFalse($finSelector, 'No se encontró la regla body.tema-plataforma');

        $comentarios = substr($html, $inicio, $finSelector - $inicio);

        // Debe haber EXACTAMENTE un cierre de comentario ("*/") entre el
        // inicio del bloque y la regla: el real. Un segundo (o ningún) cierre
        // significa que algo dentro del texto explicativo volvió a cerrar el
        // comentario antes de tiempo, como ya pasó una vez.
        $this->assertSame(1, substr_count($comentarios, '*/'), 'El comentario debe cerrarse una sola vez antes de la regla real');

        // Y la regla misma tiene que traer sus variables, prueba de que el
        // parser del navegador la ve completa y no como fragmento huérfano.
        $reglaCompleta = substr($html, $finSelector, 200);
        $this->assertStringContainsString('--bg-body: #0a0a0d', $reglaCompleta);
    }

    public function test_el_body_de_un_admin_no_lleva_tema_de_plataforma_y_conserva_su_modo_y_acento(): void
    {
        DB::table('negocios')->where('id_negocio', $this->negocioA)->update([
            'modo_tema' => 'claro',
            'color_acento' => 'azul',
        ]);

        $sesion = $this->sesionAdmin();
        $sesion['modo_tema'] = 'claro';
        $sesion['color_acento'] = 'azul';

        $html = $this->withSession($sesion)->get('backoffice/dashboard')->getContent();

        $this->assertMatchesRegularExpression('/<body class="modo-claro acento-azul"/', $html);

        // No basta con buscar "tema-plataforma" en todo el documento: el
        // bloque <style> SIEMPRE define el selector body.tema-plataforma,
        // para cualquier sesión — es la etiqueta <body> la que no debe
        // llevar la clase, no el documento entero.
        preg_match('/<body class="([^"]*)"/', $html, $coincidencia);
        $this->assertStringNotContainsString('tema-plataforma', $coincidencia[1] ?? '');
    }

    /* ================= 5) SIDEBAR ================= */

    /**
     * El layout imprime un objeto JS (PASOS_ONBOARDING) con las mismas rutas
     * operativas como "destino" de cada paso, para CUALQUIER sesión — ese
     * bloque es inerte para el super admin, pero buscar la ruta en el HTML
     * completo la encontraría igual aunque el sidebar estuviera bien. Por
     * eso estas pruebas se acotan al propio <nav id="menu-lateral">.
     */
    private function extraerSidebar(string $html): string
    {
        $inicio = strpos($html, '<nav id="menu-lateral">');
        $fin = strpos($html, '</nav>', $inicio);

        $this->assertNotFalse($inicio, 'No se encontró <nav id="menu-lateral"> en el HTML');

        return substr($html, $inicio, $fin - $inicio);
    }

    public function test_el_super_admin_ve_solo_dashboard_negocios_y_usuarios(): void
    {
        $sidebar = $this->extraerSidebar(
            $this->withSession($this->sesionSuperAdmin())->get('backoffice/superadmin/dashboard')->getContent()
        );

        $this->assertStringContainsString('backoffice/dashboard', $sidebar);
        $this->assertStringContainsString('backoffice/superadmin/negocios', $sidebar);
        $this->assertStringContainsString('backoffice/usuarios', $sidebar);

        // Nada operativo de un negocio concreto.
        foreach (['backoffice/clientes', 'backoffice/recursos', 'backoffice/empleados', 'backoffice/reservas"', 'backoffice/productos', 'backoffice/comisiones'] as $rutaOperativa) {
            $this->assertStringNotContainsString($rutaOperativa, $sidebar, "El sidebar del super admin no puede ofrecer $rutaOperativa");
        }
    }

    public function test_el_admin_de_negocio_conserva_su_sidebar_completo(): void
    {
        $sidebar = $this->extraerSidebar(
            $this->withSession($this->sesionAdmin())->get('backoffice/dashboard')->getContent()
        );

        foreach (['backoffice/clientes', 'backoffice/recursos', 'backoffice/empleados', 'backoffice/reservas', 'backoffice/productos'] as $rutaOperativa) {
            $this->assertStringContainsString($rutaOperativa, $sidebar, "El sidebar del admin debe seguir ofreciendo $rutaOperativa");
        }

        // El ítem "Negocios" es exclusivo del super admin.
        $this->assertStringNotContainsString('backoffice/superadmin/negocios', $sidebar);
    }

    public function test_el_empleado_conserva_su_sidebar_de_solo_mis_citas(): void
    {
        $html = $this->extraerSidebar(
            $this->withSession($this->sesionEmpleado())->get('backoffice/mis-citas')->getContent()
        );

        $this->assertStringContainsString('Mis Citas', $html);
        $this->assertStringNotContainsString('backoffice/superadmin/negocios', $html);
        $this->assertStringNotContainsString('backoffice/clientes', $html);
    }

    /* ================= 6) ESCAPE EN LA PANTALLA NEGOCIOS ================= */

    /**
     * PHPUnit no ejecuta el JS del navegador (el proyecto no trae Playwright
     * ni Dusk en su propio toolchain), así que aquí se comprueba lo que SÍ es
     * verificable por HTTP: que el HTML servido usa la función de escape
     * (escaparTexto) en cada punto donde texto del cliente entra a un sink
     * que interpreta HTML, y no lo concatena crudo. La verificación con un
     * navegador real (para confirmar que el payload no se ejecuta de
     * verdad) se hizo aparte, con Playwright instalado ad-hoc solo para esa
     * comprobación, y se reporta en la tarea.
     */
    public function test_el_js_de_negocios_usa_la_funcion_de_escape_en_las_columnas_con_texto_del_cliente(): void
    {
        $html = $this->withSession($this->sesionSuperAdmin())->get('backoffice/superadmin/negocios')->getContent();

        // escaparTexto es la función ÚNICA del proyecto, en public/js/utilidades.js
        // (la carga el layout). La pantalla no debe traer una copia propia.
        $this->assertStringContainsString('js/utilidades.js', $html);
        $this->assertStringContainsString('function escaparTexto', file_get_contents(public_path('js/utilidades.js')));
        $this->assertStringNotContainsString('function escaparTexto', $html, 'La pantalla no debe redefinir la función de escape');

        foreach (['fila.nombre_negocio', 'fila.slug', 'fila.nombre_admin', 'fila.email_admin', 'modulo.nombre', 'modulo.descripcion', 'modulo.clave', 'clave)'] as $campo) {
            $this->assertStringContainsString(
                'escaparTexto(' . $campo,
                $html,
                "El render de \"$campo\" debe pasar por escaparTexto()"
            );
        }

        // Ninguna concatenación cruda de esos campos fuera de escaparTexto():
        // si aparecen sin el envoltorio, es que algún render se saltó el escape.
        $this->assertStringNotContainsString("'>' + fila.nombre_negocio +", $html);
        $this->assertStringNotContainsString("'>' + fila.slug +", $html);
    }

    /**
     * Guardián de una vulnerabilidad real que se encontró y corrigió durante
     * esta misma tarea, con un navegador real: el diálogo de confirmación de
     * Suspender/Reactivar arma su "title" de SweetAlert2 concatenando el
     * nombre del negocio SIN volver a escaparlo. jQuery.data() decodifica de
     * vuelta las entidades HTML del atributo data-nombre al leerlo, así que
     * para cuando el JS lo usa, el nombre vuelve a ser la cadena cruda — y
     * "title" de SweetAlert2, a diferencia de "text", SÍ interpreta HTML. Con
     * un negocio llamado "<img src=x onerror=alert(1)>" el alert() se
     * disparaba de verdad al abrir el modal de confirmación. Corregido
     * escapando explícitamente justo antes de usarlo en "title".
     */
    public function test_el_dialogo_de_confirmacion_escapa_el_nombre_antes_de_usarlo_en_el_titulo(): void
    {
        $html = $this->withSession($this->sesionSuperAdmin())->get('backoffice/superadmin/negocios')->getContent();

        $this->assertStringContainsString(
            'var nombreEscapadoParaTitulo = escaparTexto(nombreNegocio)',
            $html,
            'El nombre debe re-escaparse antes de entrar al "title" de SweetAlert2'
        );

        // La variable escapada, no la cruda, es la que debe entrar al title.
        $this->assertStringContainsString("nombreEscapadoParaTitulo + '\"?'", $html);
        $this->assertStringNotContainsString("'?' : '¿Reactivar \"' + nombreNegocio +", $html);
        $this->assertStringNotContainsString("Suspender \"' + nombreNegocio +", $html);
    }

    /* ================= 7) GUARDIÁN DE RUTAS SIGUE PASANDO ================= */

    public function test_el_guardian_de_rutas_del_panel_incluye_las_pantallas_nuevas(): void
    {
        $rutasDelPanel = [];

        foreach (Route::getRoutes() as $ruta) {
            $uri = $ruta->uri();

            if (str_starts_with($uri, 'backoffice/superadmin')) {
                $rutasDelPanel[] = $uri;
            }
        }

        sort($rutasDelPanel);
        $this->assertSame(['backoffice/superadmin/dashboard', 'backoffice/superadmin/negocios'], $rutasDelPanel);

        foreach (Route::getRoutes() as $ruta) {
            if (str_starts_with($ruta->uri(), 'backoffice/superadmin')) {
                $this->assertContains('solo.superadmin', $ruta->gatherMiddleware(), $ruta->uri().' debe llevar solo.superadmin');
            }
        }
    }
}
