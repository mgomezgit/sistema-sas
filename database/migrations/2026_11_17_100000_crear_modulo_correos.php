<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo de pago "correos" (Correos a tus clientas).
 *
 * Controla SOLO los tres correos a la clienta (ReservaConfirmada,
 * ReservaEstadoActualizado y ReservaRecordatorio). Esos correos salían para
 * todos los negocios; la regla nueva (un negocio nace SIN módulos) se los
 * quitaría de un día para otro a quienes hoy los reciben. Por eso:
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
        if (DB::table('modulos_plataforma')->where('clave', 'correos')->doesntExist()) {
            DB::table('modulos_plataforma')->insert([
                'clave' => 'correos',
                'nombre' => 'Correos a tus clientas',
                'descripcion' => 'Correos automáticos a tus clientas: confirmación de la reserva, cambios de estado y recordatorio del día anterior.',
                'usuario_registra' => 'migracion',
                'fecha_registro' => now(),
                'estado' => 1,
            ]);
        }

        $idModulo = DB::table('modulos_plataforma')->where('clave', 'correos')->value('id_modulo');

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
        $idModulo = DB::table('modulos_plataforma')->where('clave', 'correos')->value('id_modulo');

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
