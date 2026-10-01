<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Comando auth:limpiar-pendientes-vencidos: borra solo lo vencido y nunca
 * usado de registros_pendientes y codigos_recuperacion_clave.
 *
 * Son tablas de plataforma, sin tenant: el "aislamiento" que se prueba aquí
 * es que el comando no toque ninguna otra tabla.
 *
 * ================= PRUEBAS DE MUTACIÓN (resultados reales) =================
 *
 * ML1 — Borrando registros vencidos aunque estén confirmados: 4 tests,
 *       3 passed, 1 FAILED (borró 3 en vez de 2).
 * ML2 — Borrando códigos vencidos aunque estén usados: 1 FAILED (borró 2
 *       en vez de 1).
 * ML3 — Borrando registros sin confirmar aunque estén vigentes: 2 FAILED.
 * ML4 — El comando borrando además una fila de migrations: 1 FAILED — la
 *       comparación de conteos del resto de la base.
 * Todas restauradas: 4 passed.
 */
class LimpiezaPendientesVencidosTest extends TestCase
{
    use RefreshDatabase;

    private int $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert(['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1]);

        $negocio = DB::table('negocios')->insertGetId([
            'nombre_negocio' => 'Spa Limpieza',
            'slug' => 'spa-limpieza',
            'rubro' => 'spa',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $this->usuario = DB::table('usuarios')->insertGetId([
            'tenant_id' => $negocio,
            'id_rol' => 1,
            'usuario' => 'dueno@limpieza.test',
            'nombre' => 'Dueño',
            'email' => 'dueno@limpieza.test',
            'clave' => bcrypt('ClaveLimpieza2026'),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function pendiente(string $correo, $expiracion, $confirmadoEn = null): void
    {
        DB::table('registros_pendientes')->insert([
            'nombre_negocio' => 'Spa '.$correo,
            'rubro' => 'spa',
            'telefono_contacto' => '3000000000',
            'nombre_admin' => 'Alguien',
            'correo' => $correo,
            'clave_hash' => bcrypt('x'),
            'token_hash' => hash('sha256', $correo),
            'fecha_expiracion' => $expiracion,
            'confirmado_en' => $confirmadoEn,
            'fecha_registro' => now(),
        ]);
    }

    private function codigo($expiracion, $usadoEn = null): int
    {
        return DB::table('codigos_recuperacion_clave')->insertGetId([
            'id_usuario' => $this->usuario,
            'codigo_hash' => bcrypt('123456'),
            'fecha_expiracion' => $expiracion,
            'usado_en' => $usadoEn,
            'fecha_registro' => now(),
        ]);
    }

    /** Filas por tabla de TODA la base, salvo las dos que limpia el comando. */
    private function conteoDelResto(): array
    {
        $conteo = [];

        foreach (Schema::getTableListing() as $tabla) {
            $tabla = str_contains($tabla, '.') ? substr($tabla, strrpos($tabla, '.') + 1) : $tabla;

            if (in_array($tabla, ['registros_pendientes', 'codigos_recuperacion_clave'], true)) {
                continue;
            }

            $conteo[$tabla] = DB::table($tabla)->count();
        }

        ksort($conteo);

        return $conteo;
    }

    public function test_borra_solo_lo_vencido_sin_confirmar_ni_usar_y_reporta_cuanto(): void
    {
        $this->pendiente('vencido@x.test', now()->subHour());
        $this->pendiente('vencido2@x.test', now()->subDays(3));
        $this->pendiente('vigente@x.test', now()->addHour());
        $this->pendiente('confirmado-vencido@x.test', now()->subDay(), now()->subDays(2));

        $vencido = $this->codigo(now()->subMinute());
        $vigente = $this->codigo(now()->addMinutes(30));
        $usadoVencido = $this->codigo(now()->subDay(), now()->subDays(2));

        $this->artisan('auth:limpiar-pendientes-vencidos')
            ->expectsOutput('Registros pendientes vencidos borrados: 2')
            ->expectsOutput('Códigos de recuperación vencidos borrados: 1')
            ->assertExitCode(0);

        $this->assertEqualsCanonicalizing(
            ['vigente@x.test', 'confirmado-vencido@x.test'],
            DB::table('registros_pendientes')->pluck('correo')->all()
        );

        $this->assertEqualsCanonicalizing(
            [$vigente, $usadoVencido],
            DB::table('codigos_recuperacion_clave')->pluck('id_codigo')->all()
        );
        $this->assertNotContains($vencido, DB::table('codigos_recuperacion_clave')->pluck('id_codigo')->all());
    }

    public function test_sin_nada_vencido_no_borra_nada(): void
    {
        $this->pendiente('vigente@x.test', now()->addHour());
        $this->codigo(now()->addMinutes(30));

        $this->artisan('auth:limpiar-pendientes-vencidos')
            ->expectsOutput('Registros pendientes vencidos borrados: 0')
            ->expectsOutput('Códigos de recuperación vencidos borrados: 0')
            ->assertExitCode(0);

        $this->assertSame(1, DB::table('registros_pendientes')->count());
        $this->assertSame(1, DB::table('codigos_recuperacion_clave')->count());
    }

    public function test_no_toca_ninguna_otra_tabla(): void
    {
        $this->pendiente('vencido@x.test', now()->subHour());
        $this->codigo(now()->subMinute());

        $antes = $this->conteoDelResto();

        $this->artisan('auth:limpiar-pendientes-vencidos')->assertExitCode(0);

        $this->assertSame($antes, $this->conteoDelResto());
        $this->assertTrue(DB::table('usuarios')->where('id_usuario', $this->usuario)->exists());
    }

    public function test_esta_programado_una_vez_al_dia_a_las_3(): void
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'auth:limpiar-pendientes-vencidos'));

        $this->assertNotNull($evento, 'El comando no está en el Scheduler');
        $this->assertSame('0 3 * * *', $evento->expression);
    }
}
