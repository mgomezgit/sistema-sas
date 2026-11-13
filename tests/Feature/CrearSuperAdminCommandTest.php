<?php

namespace Tests\Feature;

use App\Console\Commands\CrearSuperAdmin;
use Database\Seeders\RolSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

/**
 * Comando plataforma:crear-super-admin (interactivo), sobre la base de pruebas.
 *
 * ================= MUTACIONES (resultados en el reporte de la tarea) =================
 * M1: se quitó la negativa del comando por super admin existente -> falla
 *     test_se_niega_si_ya_existe_un_super_admin. Restaurado, pasa.
 * M2: se imprimió la clave en la salida -> falla
 *     test_la_salida_no_contiene_la_clave_ni_su_hash. Restaurado, pasa.
 */
class CrearSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    const CLAVE = 'Clave-De-Prueba-Segura-2026';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->seed(RolSeeder::class);
    }

    private function negocio(): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => 'Negocio', 'rubro' => 'spa', 'usuario_registra' => 'test',
            'fecha_registro' => now(), 'estado' => 1,
        ]);
    }

    private function usuario(?int $tenantId, int $idRol, string $usuario, string $email, int $estado = 1): int
    {
        return DB::table('usuarios')->insertGetId([
            'tenant_id' => $tenantId, 'id_rol' => $idRol, 'usuario' => $usuario, 'nombre' => 'X',
            'email' => $email, 'clave' => bcrypt('otra-clave-cualquiera'), 'usuario_registra' => 'test',
            'fecha_registro' => now(), 'estado' => $estado,
        ]);
    }

    /** Ejecuta el comando contestando todo; $datos pisa los valores por defecto. */
    private function ejecutar(array $datos = [])
    {
        $d = $datos + [
            'nombre' => 'Mateo Plataforma',
            'usuario' => 'mateo.plataforma',
            'correo' => 'mateo@plataforma.test',
            'clave' => self::CLAVE,
            'confirmacion' => self::CLAVE,
        ];

        return $this->artisan('plataforma:crear-super-admin')
            ->expectsQuestion('Nombre completo', $d['nombre'])
            ->expectsQuestion('Usuario', $d['usuario'])
            ->expectsQuestion('Correo', $d['correo'])
            ->expectsQuestion('Clave (mínimo 12 caracteres, no se muestra)', $d['clave'])
            ->expectsQuestion('Repite la clave', $d['confirmacion']);
    }

    private function cuentasSuperAdmin(): int
    {
        return DB::table('usuarios')->where('id_rol', 3)->count();
    }

    /* ================= CREACIÓN ================= */

    public function test_crea_la_cuenta_con_rol_tenant_nulo_estado_activo_y_clave_hasheada(): void
    {
        $this->ejecutar()->assertSuccessful();

        $fila = DB::table('usuarios')->where('email', 'mateo@plataforma.test')->first();

        $this->assertNotNull($fila);
        $this->assertSame(3, (int) $fila->id_rol);
        $this->assertNull($fila->tenant_id);
        $this->assertSame(1, (int) $fila->estado);
        $this->assertSame('mateo.plataforma', $fila->usuario);
        $this->assertSame('Mateo Plataforma', $fila->nombre);
        $this->assertNotSame(self::CLAVE, $fila->clave, 'La clave nunca se guarda en claro');
        $this->assertTrue(Hash::check(self::CLAVE, $fila->clave));
        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    public function test_la_salida_no_contiene_la_clave_ni_su_hash(): void
    {
        // El hash exacto no se conoce antes de correr, así que se prohíbe
        // cualquier hash de clave por su prefijo (bcrypt y argon).
        $this->ejecutar()
            ->doesntExpectOutputToContain(self::CLAVE)
            ->doesntExpectOutputToContain('$2y$')
            ->doesntExpectOutputToContain('$argon')
            ->assertSuccessful();

        $this->assertStringStartsWith('$2y$', (string) DB::table('usuarios')->where('email', 'mateo@plataforma.test')->value('clave'));
    }

    public function test_imprime_solo_correo_y_usuario_sin_la_clave(): void
    {
        $this->ejecutar()
            ->expectsOutputToContain('mateo@plataforma.test')
            ->expectsOutputToContain('mateo.plataforma')
            ->expectsOutputToContain('Recuperar clave')
            ->doesntExpectOutputToContain(self::CLAVE)
            ->assertSuccessful();
    }

    public function test_el_log_no_contiene_la_clave_ni_su_hash(): void
    {
        $mensajes = [];
        $canal = Mockery::mock();
        $canal->shouldReceive('info')->andReturnUsing(function ($m) use (&$mensajes) {
            $mensajes[] = (string) $m;
        });
        Log::shouldReceive('channel')->with('database')->andReturn($canal);

        $this->ejecutar()->assertSuccessful();

        $hash = (string) DB::table('usuarios')->where('email', 'mateo@plataforma.test')->value('clave');
        $todo = implode("\n", $mensajes);

        $this->assertStringContainsString('mateo.plataforma', $todo, 'Queda la línea con el usuario');
        $this->assertStringNotContainsString(self::CLAVE, $todo);
        $this->assertStringNotContainsString($hash, $todo);
    }

    /* ================= NEGATIVAS ================= */

    public function test_se_niega_si_ya_existe_un_super_admin(): void
    {
        $this->usuario(null, 3, 'existente', 'existente@plataforma.test');

        $this->artisan('plataforma:crear-super-admin')
            ->expectsOutputToContain('Ya existe una cuenta super admin')
            ->assertFailed();

        $this->assertSame(1, $this->cuentasSuperAdmin());
    }

    public function test_se_niega_si_existe_un_super_admin_inactivo(): void
    {
        $this->usuario(null, 3, 'viejo', 'viejo@plataforma.test', 0);

        $this->artisan('plataforma:crear-super-admin')
            ->expectsOutputToContain('Ya existe una cuenta super admin')
            ->assertFailed();

        $this->assertSame(1, $this->cuentasSuperAdmin());
    }

    public function test_se_niega_si_la_tabla_roles_esta_vacia(): void
    {
        DB::table('roles')->delete();

        $this->artisan('plataforma:crear-super-admin')
            ->expectsOutputToContain('ejecuta primero el seeder de roles')
            ->assertFailed();

        $this->assertSame(0, DB::table('usuarios')->count());
    }

    public function test_rechaza_correo_invalido(): void
    {
        $this->artisan('plataforma:crear-super-admin')
            ->expectsQuestion('Nombre completo', 'Mateo')
            ->expectsQuestion('Usuario', 'mateo.plataforma')
            ->expectsQuestion('Correo', 'esto-no-es-un-correo')
            ->expectsOutputToContain('formato válido')
            ->assertFailed();

        $this->assertSame(0, $this->cuentasSuperAdmin());
    }

    public function test_rechaza_correo_usado_por_una_cuenta_activa(): void
    {
        $this->usuario($this->negocio(), 1, 'otro', 'mateo@plataforma.test');

        $this->artisan('plataforma:crear-super-admin')
            ->expectsQuestion('Nombre completo', 'Mateo')
            ->expectsQuestion('Usuario', 'mateo.plataforma')
            ->expectsQuestion('Correo', 'mateo@plataforma.test')
            ->expectsOutputToContain('Ese correo ya lo usa una cuenta activa')
            ->assertFailed();

        $this->assertSame(0, $this->cuentasSuperAdmin());
    }

    public function test_rechaza_usuario_repetido(): void
    {
        $this->usuario($this->negocio(), 1, 'mateo.plataforma', 'otro@negocio.test');

        $this->artisan('plataforma:crear-super-admin')
            ->expectsQuestion('Nombre completo', 'Mateo')
            ->expectsQuestion('Usuario', 'mateo.plataforma')
            ->expectsQuestion('Correo', 'mateo@plataforma.test')
            ->expectsOutputToContain('Ese usuario ya lo usa una cuenta activa')
            ->assertFailed();

        $this->assertSame(0, $this->cuentasSuperAdmin());
    }

    public function test_rechaza_clave_corta(): void
    {
        $this->ejecutar(['clave' => 'corta-11ch', 'confirmacion' => 'corta-11ch'])
            ->expectsOutputToContain('al menos 12 caracteres')
            ->assertFailed();

        $this->assertSame(0, $this->cuentasSuperAdmin());
    }

    public function test_rechaza_confirmacion_distinta(): void
    {
        $this->ejecutar(['confirmacion' => self::CLAVE.'-otra'])
            ->expectsOutputToContain('no coinciden')
            ->assertFailed();

        $this->assertSame(0, $this->cuentasSuperAdmin());
    }

    /* ================= DEFENSA EN EL SERVICE Y EN LA BASE ================= */

    public function test_el_service_tampoco_crea_una_segunda_cuenta(): void
    {
        $this->usuario(null, 3, 'existente', 'existente@plataforma.test', 0);

        $id = app(\App\Service\SvcUsuario::class)->crearCuentaDePlataforma([
            'nombre' => 'Otro', 'usuario' => 'otro.super', 'email' => 'otro@plataforma.test', 'clave' => self::CLAVE,
        ]);

        $this->assertFalse($id);
        $this->assertSame(1, $this->cuentasSuperAdmin());
    }

    public function test_la_base_rechaza_un_segundo_super_admin_insertado_directo(): void
    {
        $this->ejecutar()->assertSuccessful();

        $this->expectException(QueryException::class);

        $this->usuario(null, 3, 'segundo', 'segundo@plataforma.test');
    }

    /* ================= LA CLAVE NUNCA VA POR ARGUMENTO ================= */

    public function test_el_comando_no_define_argumentos_ni_opciones_para_la_clave(): void
    {
        $comando = $this->app->make(CrearSuperAdmin::class);
        $comando->setLaravel($this->app);
        $definicion = $comando->getDefinition();

        $this->assertSame([], array_keys($definicion->getArguments()), 'El comando no acepta argumentos');

        // Solo las opciones genéricas de Symfony/Laravel (help, quiet, env...).
        foreach (array_keys($definicion->getOptions()) as $opcion) {
            $this->assertDoesNotMatchRegularExpression('/clave|pass|secret|contra/i', $opcion, "Opción prohibida: --$opcion");
        }

        $this->assertSame('plataforma:crear-super-admin', $comando->getName());
        $this->assertStringNotContainsString('{', (new \ReflectionProperty($comando, 'signature'))->getValue($comando));
    }
}
