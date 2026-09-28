<?php

namespace Tests\Feature;

use App\Mail\ResumenStockBajo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Comando productos:enviar-resumen-stock-bajo, de punta a punta.
 *
 * ================= INVESTIGACIÓN =================
 *
 * Antes de corregir, así funcionaba:
 *
 *   SvcProducto::listarStockBajoTodosLosNegocios() unía productos con
 *   negocios Y con usuarios (filtrado a id_rol = admin, SIN estado = 1) en
 *   la MISMA consulta. Un LEFT JOIN sobre una relación 1-a-N (un negocio
 *   puede tener varios admins) multiplica las filas: un negocio con 2 admins
 *   devolvía cada producto DOS VECES, uno por cada fila del join.
 *
 *   El comando agrupaba por tenant_id en un array asociativo y hacía
 *   $porNegocio[$tenantId]['email_admin'] = $producto['email_admin'] dentro
 *   del foreach: una asignación simple, no una acumulación. Cada vuelta del
 *   bucle SOBRESCRIBÍA el email anterior, así que al final solo sobrevivía
 *   el de la ÚLTIMA fila procesada para ese negocio — ni siquiera había
 *   garantía de que fuera un admin activo, porque el JOIN tampoco filtraba
 *   por estado.
 *
 *   Consecuencia real: un negocio con 2 admins activos enviaba UN solo
 *   correo (al azar cuál de los dos, según el orden de las filas) con cada
 *   producto DUPLICADO. Y un admin dado de baja podía terminar siendo el
 *   destinatario, mientras el admin activo nunca se enteraba.
 *
 * La corrección separa las dos preguntas en dos consultas: qué productos
 * (sin admins) y qué admins activos (sin productos), y el comando cruza
 * ambas sin volver a duplicar nada.
 *
 * ================= PRUEBA DE MUTACIÓN =================
 *
 * 1. En EnviarResumenStockBajo::handle(), dentro del foreach que arma cada
 *    correo, se cambió:
 *        new ResumenStockBajo($datosNegocio['nombre_negocio'], $datosNegocio['productos'])
 *    por:
 *        new ResumenStockBajo($datosNegocio['nombre_negocio'], $productos)
 *    ($productos es la lista SIN agrupar, de TODOS los negocios).
 * 2. php artisan test --filter=ResumenStockBajoCommandTest
 *    Resultado: 6 tests, 5 passed, 1 FAILED —
 *      test_el_correo_de_un_negocio_jamas_contiene_productos_de_otro:
 *      "The expected [App\Mail\ResumenStockBajo] mailable was not queued.
 *       Failed asserting that false is true."
 *    Cada admin recibió TODOS los productos de TODOS los negocios, no solo
 *    los del suyo.
 *
 *    Nota sobre la propia prueba: la primera versión de este archivo tenía
 *    un bug en el closure de esta prueba ("si el correo no es para este
 *    admin, se deja pasar sin comprobar nada"), que hacía que el correo del
 *    OTRO admin satisficiera la aserción trivialmente y la prueba pasara
 *    incluso con la fuga activa. Se corrigió antes de dar la prueba por
 *    buena: el closure ahora exige hasTo() Y el aislamiento en el MISMO
 *    correo, nunca uno u otro.
 * 3. Se restauró la línea tal cual estaba.
 * 4. Se volvió a ejecutar: 6 passed.
 */
class ResumenStockBajoCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->negocioA = $this->crearNegocio('Negocio A');
        $this->negocioB = $this->crearNegocio('Negocio B');
    }

    /* ================= AYUDANTES ================= */

    private function crearNegocio(string $nombre): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'rubro' => 'spa',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function crearAdmin(int $tenantId, string $usuario, string $email, int $estado = 1): int
    {
        return DB::table('usuarios')->insertGetId([
            'tenant_id' => $tenantId,
            'id_rol' => 1,
            'usuario' => $usuario,
            'nombre' => 'Admin '.$usuario,
            'email' => $email,
            'clave' => bcrypt('secreta'),
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => $estado,
        ]);
    }

    private function crearProducto(int $tenantId, string $nombre, int $actual, int $minimo, int $estado = 1): int
    {
        return DB::table('productos')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'cantidad_actual' => $actual,
            'cantidad_minima' => $minimo,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => $estado,
        ]);
    }

    /* ================= 1) UN ADMIN, VARIOS PRODUCTOS ================= */

    public function test_un_admin_activo_recibe_un_correo_con_todos_los_productos(): void
    {
        Mail::fake();

        $this->crearAdmin($this->negocioA, 'admin.a', 'admin.a@test.local');
        $this->crearProducto($this->negocioA, 'Shampoo', 1, 10);
        $this->crearProducto($this->negocioA, 'Crema', 2, 10);

        Artisan::call('productos:enviar-resumen-stock-bajo');

        Mail::assertQueuedCount(1);
        Mail::assertQueued(ResumenStockBajo::class, function (ResumenStockBajo $mail) {
            return $mail->hasTo('admin.a@test.local') && count($mail->productos) === 2;
        });
    }

    /* ================= 2) DOS ADMINS ACTIVOS: DOS CORREOS, SIN DUPLICAR ================= */

    public function test_negocio_con_dos_admins_activos_cada_uno_recibe_su_correo_sin_productos_repetidos(): void
    {
        Mail::fake();

        $this->crearAdmin($this->negocioA, 'admin.uno', 'uno@test.local');
        $this->crearAdmin($this->negocioA, 'admin.dos', 'dos@test.local');
        $this->crearProducto($this->negocioA, 'Shampoo', 1, 10);
        $this->crearProducto($this->negocioA, 'Crema', 2, 10);

        Artisan::call('productos:enviar-resumen-stock-bajo');

        Mail::assertQueuedCount(2, ResumenStockBajo::class);

        foreach (['uno@test.local', 'dos@test.local'] as $email) {
            Mail::assertQueued(ResumenStockBajo::class, function (ResumenStockBajo $mail) use ($email) {
                if (! $mail->hasTo($email)) {
                    return false;
                }

                $nombres = array_column($mail->productos, 'nombre');

                // Sin duplicados dentro del correo de ESTE admin.
                return count($nombres) === 2 && count(array_unique($nombres)) === 2;
            });
        }
    }

    /* ================= 3) ADMIN INACTIVO ================= */

    public function test_un_admin_inactivo_no_recibe_nada(): void
    {
        Mail::fake();

        $this->crearAdmin($this->negocioA, 'admin.activo', 'activo@test.local');
        $this->crearAdmin($this->negocioA, 'admin.inactivo', 'inactivo@test.local', 0);
        $this->crearProducto($this->negocioA, 'Shampoo', 1, 10);

        Artisan::call('productos:enviar-resumen-stock-bajo');

        Mail::assertQueuedCount(1);
        Mail::assertNotQueued(ResumenStockBajo::class, fn ($mail) => $mail->hasTo('inactivo@test.local'));
    }

    public function test_un_negocio_sin_ningun_admin_activo_no_envia_nada(): void
    {
        Mail::fake();

        $this->crearAdmin($this->negocioA, 'admin.inactivo', 'inactivo@test.local', 0);
        $this->crearProducto($this->negocioA, 'Shampoo', 1, 10);

        Artisan::call('productos:enviar-resumen-stock-bajo');

        Mail::assertNothingQueued();
    }

    /* ================= 4) AISLAMIENTO ENTRE NEGOCIOS ================= */

    /** Ver la nota de MUTACIÓN en el encabezado de la clase. */
    public function test_el_correo_de_un_negocio_jamas_contiene_productos_de_otro(): void
    {
        Mail::fake();

        $this->crearAdmin($this->negocioA, 'admin.a', 'admin.a@test.local');
        $this->crearAdmin($this->negocioB, 'admin.b', 'admin.b@test.local');
        $this->crearProducto($this->negocioA, 'Producto Solo De A', 1, 10);
        $this->crearProducto($this->negocioB, 'Producto Solo De B', 1, 10);

        Artisan::call('productos:enviar-resumen-stock-bajo');

        Mail::assertQueuedCount(2);

        // assertQueued exige que AL MENOS UN correo encolado cumpla el
        // closure entero: no basta con "si no es el destinatario, se deja
        // pasar", porque entonces el correo del OTRO admin satisface la
        // condición trivialmente y la prueba no comprueba nada. El closure
        // debe exigir hasTo() Y el aislamiento a la vez, sobre el mismo
        // correo.
        Mail::assertQueued(ResumenStockBajo::class, function (ResumenStockBajo $mail) {
            $nombres = array_column($mail->productos, 'nombre');

            return $mail->hasTo('admin.a@test.local')
                && in_array('Producto Solo De A', $nombres, true)
                && ! in_array('Producto Solo De B', $nombres, true);
        });

        Mail::assertQueued(ResumenStockBajo::class, function (ResumenStockBajo $mail) {
            $nombres = array_column($mail->productos, 'nombre');

            return $mail->hasTo('admin.b@test.local')
                && in_array('Producto Solo De B', $nombres, true)
                && ! in_array('Producto Solo De A', $nombres, true);
        });
    }

    /* ================= 5) EL LÍMITE <= SIGUE IGUAL ================= */

    public function test_un_producto_con_cantidad_igual_al_minimo_aparece(): void
    {
        Mail::fake();

        $this->crearAdmin($this->negocioA, 'admin.a', 'admin.a@test.local');
        $this->crearProducto($this->negocioA, 'Justo En El Minimo', 5, 5);
        $this->crearProducto($this->negocioA, 'Una Unidad Arriba', 6, 5);

        Artisan::call('productos:enviar-resumen-stock-bajo');

        Mail::assertQueued(ResumenStockBajo::class, function (ResumenStockBajo $mail) {
            $nombres = array_column($mail->productos, 'nombre');

            return in_array('Justo En El Minimo', $nombres, true)
                && ! in_array('Una Unidad Arriba', $nombres, true);
        });
    }
}
