<?php

namespace Tests\Feature;

use App\Models\Rol;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolSeeder;
use Database\Seeders\UsuarioDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Seeders de roles: RolSeeder (producción) frente a UsuarioDemoSeeder (solo
 * local), y su acople con el id de respaldo de la migración
 * 2026_10_31_100000_blindar_cuenta_super_admin_unica.
 *
 * Esa migración, si la tabla roles está vacía al migrar, liga la defensa de la
 * base al id 3 para super_admin. RolSeeder debe crear super_admin EXACTAMENTE
 * con ese id; si uno cambia sin el otro, la defensa quedaría ligada al rol
 * equivocado. El respaldo se lee de la migración misma, no se copia aquí.
 *
 * ================= MUTACIONES (resultados en el reporte de la tarea) =================
 * M1: en RolSeeder::ROLES, super_admin pasó de 3 a 4 -> falla el acople con la
 *     migración. Restaurado, pasa.
 * M2: se quitó la comprobación de entorno de UsuarioDemoSeeder -> falla la
 *     prueba de la negativa fuera de local. Restaurado, pasa.
 */
class SeederRolesTest extends TestCase
{
    use RefreshDatabase;

    const IDS_ESPERADOS = [
        'admin' => 1,
        'empleado' => 2,
        'super_admin' => 3,
    ];

    protected function tearDown(): void
    {
        $this->app['env'] = 'testing';

        parent::tearDown();
    }

    /** El id de respaldo, calculado por la MIGRACIÓN con la tabla roles vacía. */
    private function idRespaldoDeLaMigracion(): int
    {
        $this->assertSame(0, DB::table('roles')->count(), 'La tabla roles debe estar vacía para leer el respaldo');

        $migracion = require database_path('migrations/2026_10_31_100000_blindar_cuenta_super_admin_unica.php');
        $metodo = new \ReflectionMethod($migracion, 'idSuperAdmin');
        $metodo->setAccessible(true);

        return (int) $metodo->invoke($migracion);
    }

    private function roles(): array
    {
        return DB::table('roles')->orderBy('id_rol')->pluck('nombre_rol', 'id_rol')->map(fn ($n) => (string) $n)->all();
    }

    /* ================= ACOPLE CON LA MIGRACIÓN ================= */

    public function test_rol_seeder_crea_super_admin_con_el_id_de_respaldo_de_la_migracion(): void
    {
        $respaldo = $this->idRespaldoDeLaMigracion();

        $this->seed(RolSeeder::class);

        $this->assertSame(
            $respaldo,
            (int) Rol::where('nombre_rol', 'super_admin')->value('id_rol'),
            'RolSeeder debe crear super_admin con el mismo id que la migración de blindaje usa de respaldo. Si cambias uno, cambia el otro.'
        );
    }

    /* ================= ROLSEEDER ================= */

    public function test_rol_seeder_crea_exactamente_los_tres_roles_con_sus_ids(): void
    {
        $this->seed(RolSeeder::class);

        $this->assertSame([1 => 'admin', 2 => 'empleado', 3 => 'super_admin'], $this->roles());

        foreach (self::IDS_ESPERADOS as $nombre => $id) {
            $this->assertSame($id, (int) Rol::where('nombre_rol', $nombre)->value('id_rol'));
        }
    }

    public function test_rol_seeder_dos_veces_no_duplica_ni_cambia_ids(): void
    {
        $this->seed(RolSeeder::class);
        $antes = DB::table('roles')->orderBy('id_rol')->get()->map(fn ($r) => (array) $r)->all();

        $this->seed(RolSeeder::class);
        $despues = DB::table('roles')->orderBy('id_rol')->get()->map(fn ($r) => (array) $r)->all();

        $this->assertCount(3, $despues);
        $this->assertSame($antes, $despues, 'La segunda corrida no debe tocar nada');
    }

    public function test_rol_seeder_completa_los_que_faltan_sin_tocar_los_existentes(): void
    {
        DB::table('roles')->insert(['id_rol' => 1, 'nombre_rol' => 'admin', 'usuario_registra' => 'previo', 'estado' => 1]);

        $this->seed(RolSeeder::class);

        $this->assertSame([1 => 'admin', 2 => 'empleado', 3 => 'super_admin'], $this->roles());
        $this->assertSame('previo', DB::table('roles')->where('id_rol', 1)->value('usuario_registra'), 'El existente no se reescribe');
    }

    public function test_rol_seeder_se_detiene_si_el_id_3_existe_con_otro_nombre(): void
    {
        DB::table('roles')->insert(['id_rol' => 3, 'nombre_rol' => 'otro_rol', 'estado' => 1]);

        try {
            $this->seed(RolSeeder::class);
            $this->fail('RolSeeder debía detenerse');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString("id 3 ya existe con el nombre 'otro_rol'", $e->getMessage());
        }

        // No escribió nada: ni sobrescribió el 3 ni creó admin/empleado.
        $this->assertSame([3 => 'otro_rol'], $this->roles());
    }

    public function test_rol_seeder_se_detiene_si_un_nombre_existe_con_otro_id(): void
    {
        DB::table('roles')->insert(['id_rol' => 7, 'nombre_rol' => 'super_admin', 'estado' => 1]);

        try {
            $this->seed(RolSeeder::class);
            $this->fail('RolSeeder debía detenerse');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString("'super_admin' ya existe con el id 7", $e->getMessage());
        }

        $this->assertSame([7 => 'super_admin'], $this->roles());
    }

    /* ================= DATABASESEEDER ================= */

    public function test_database_seeder_solo_crea_roles_y_ningun_usuario(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame([1 => 'admin', 2 => 'empleado', 3 => 'super_admin'], $this->roles());
        $this->assertSame(0, DB::table('usuarios')->count(), 'DatabaseSeeder no crea cuentas');
        $this->assertSame(0, DB::table('users')->count(), 'DatabaseSeeder no crea el usuario de prueba del esqueleto');
        $this->assertSame(0, DB::table('negocios')->count(), 'DatabaseSeeder no crea negocios de demostración');
    }

    /* ================= USUARIODEMOSEEDER (SOLO LOCAL) ================= */

    public function test_usuario_demo_seeder_se_niega_fuera_del_entorno_local(): void
    {
        foreach (['testing', 'production', 'staging'] as $entorno) {
            $this->app['env'] = $entorno;

            $this->artisan('db:seed', ['--class' => UsuarioDemoSeeder::class, '--force' => true])
                ->expectsOutputToContain('solo se ejecuta en el entorno local');

            $this->assertSame(0, DB::table('roles')->count(), "En $entorno no debe escribir roles");
            $this->assertSame(0, DB::table('usuarios')->count(), "En $entorno no debe crear cuentas");
            $this->assertSame(0, DB::table('negocios')->count(), "En $entorno no debe crear negocios");
        }
    }

    public function test_usuario_demo_seeder_funciona_en_local_y_usa_rol_seeder(): void
    {
        $this->app['env'] = 'local';

        $this->seed(UsuarioDemoSeeder::class);

        $this->assertSame([1 => 'admin', 2 => 'empleado', 3 => 'super_admin'], $this->roles());
        $this->assertSame(3, (int) DB::table('usuarios')->where('email', 'superadmin@demo.test')->value('id_rol'));
        $this->assertNull(DB::table('usuarios')->where('email', 'superadmin@demo.test')->value('tenant_id'));
        $this->assertSame(1, (int) DB::table('usuarios')->where('email', 'admin@demo.test')->value('id_rol'));
    }
}
