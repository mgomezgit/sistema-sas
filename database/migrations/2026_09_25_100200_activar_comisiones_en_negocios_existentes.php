<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MIGRACIÓN DE DATOS PUNTUAL — no es el comportamiento por defecto.
 *
 * Comisiones existía como módulo abierto a todos los negocios antes de que
 * hubiera control de módulos de pago. Al introducir ese control, la regla
 * nueva (un negocio nace SIN módulos activos) dejaría sin acceso, de un día
 * para otro, a negocios que hoy lo están usando.
 *
 * Por eso esta migración le activa Comisiones a los negocios que YA EXISTEN
 * en el momento de correrla: preserva lo que ya tenían. Un negocio creado
 * después de esto nace con el módulo inactivo, como manda la regla nueva
 * (ver negocio_modulos.activo con default false).
 *
 * Es idempotente: solo inserta la fila del negocio que todavía no la tenga,
 * así que volver a correrla no duplica nada ni reactiva lo que un super admin
 * haya desactivado a mano después.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $idModulo = DB::table('modulos_plataforma')->where('clave', 'comisiones')->value('id_modulo');

        if (empty($idModulo)) {
            return;
        }

        $ahora = now();

        $negocios = DB::table('negocios')
            ->whereNotExists(function ($query) use ($idModulo) {
                $query->select(DB::raw(1))
                    ->from('negocio_modulos')
                    ->whereColumn('negocio_modulos.tenant_id', 'negocios.id_negocio')
                    ->where('negocio_modulos.id_modulo', $idModulo);
            })
            ->pluck('id_negocio');

        foreach ($negocios as $idNegocio) {
            DB::table('negocio_modulos')->insert([
                'tenant_id' => $idNegocio,
                'id_modulo' => $idModulo,
                'activo' => true,
                'fecha_activacion' => $ahora,
                'fecha_desactivacion' => null,
                'usuario_registra' => 'migracion',
                'fecha_registro' => $ahora,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Solo retira las filas que puso esta migración (las marcadas como
     * 'migracion'), para no borrar activaciones hechas después por un
     * super admin.
     */
    public function down(): void
    {
        $idModulo = DB::table('modulos_plataforma')->where('clave', 'comisiones')->value('id_modulo');

        if (empty($idModulo)) {
            return;
        }

        DB::table('negocio_modulos')
            ->where('id_modulo', $idModulo)
            ->where('usuario_registra', 'migracion')
            ->delete();
    }
};
