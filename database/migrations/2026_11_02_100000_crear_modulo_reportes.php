<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo de pago "reportes" (Reportes a Excel).
 *
 * Reportes era del núcleo (cualquier negocio lo usaba). Al convertirlo en
 * módulo de pago, la regla nueva (un negocio nace SIN módulos) dejaría sin
 * acceso de un día para otro a quienes hoy lo usan. Por eso:
 *
 * 1. Se siembra la fila del catálogo.
 * 2. Se ACTIVA para los negocios que YA EXISTEN al correr la migración, como
 *    cortesía y sin vencimiento. Un negocio creado después nace sin el módulo.
 *
 * Es idempotente: solo inserta lo que falta, así que volver a correrla no
 * duplica filas ni reactiva lo que un super admin haya apagado después.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('modulos_plataforma')->where('clave', 'reportes')->doesntExist()) {
            DB::table('modulos_plataforma')->insert([
                'clave' => 'reportes',
                'nombre' => 'Reportes a Excel',
                'descripcion' => 'Informes de ventas y de servicios, con vista previa en pantalla y descarga a Excel.',
                'usuario_registra' => 'migracion',
                'fecha_registro' => now(),
                'estado' => 1,
            ]);
        }

        $idModulo = DB::table('modulos_plataforma')->where('clave', 'reportes')->value('id_modulo');

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
                'vigente_hasta' => null,
                'origen' => 'cortesia',
                'nota' => 'Incluido al crear el modulo',
                'fecha_activacion' => $ahora,
                'fecha_desactivacion' => null,
                'usuario_registra' => 'migracion',
                'fecha_registro' => $ahora,
            ]);
        }
    }

    /**
     * Solo retira lo que puso esta migración (filas 'migracion' del módulo) y
     * la fila del catálogo, para no borrar activaciones hechas después por un
     * super admin.
     */
    public function down(): void
    {
        $idModulo = DB::table('modulos_plataforma')->where('clave', 'reportes')->value('id_modulo');

        if (empty($idModulo)) {
            return;
        }

        DB::table('negocio_modulos')
            ->where('id_modulo', $idModulo)
            ->where('usuario_registra', 'migracion')
            ->delete();

        DB::table('modulos_plataforma')->where('id_modulo', $idModulo)->delete();
    }
};
