<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo de pago "pagina_publica" (Página pública completa).
 *
 * Sin él, reservar/{slug} es la página BÁSICA (nombre, horario y WhatsApp) y
 * los endpoints publico/{slug}/* de servicios, equipo, banners, agendar y
 * chat responden 404. La página completa era para todos los negocios; la
 * regla nueva (un negocio nace SIN módulos) se la quitaría de un día para
 * otro a quienes hoy la usan. Por eso:
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
        if (DB::table('modulos_plataforma')->where('clave', 'pagina_publica')->doesntExist()) {
            DB::table('modulos_plataforma')->insert([
                'clave' => 'pagina_publica',
                'nombre' => 'Página pública completa',
                'descripcion' => 'Tu página con servicios, equipo, promociones y solicitud de citas en línea, más el asistente del chat si lo tienes.',
                'usuario_registra' => 'migracion',
                'fecha_registro' => now(),
                'estado' => 1,
            ]);
        }

        $idModulo = DB::table('modulos_plataforma')->where('clave', 'pagina_publica')->value('id_modulo');

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
        $idModulo = DB::table('modulos_plataforma')->where('clave', 'pagina_publica')->value('id_modulo');

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
