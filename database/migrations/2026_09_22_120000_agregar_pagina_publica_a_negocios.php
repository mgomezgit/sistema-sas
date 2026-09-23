<?php

use App\Service\SvcNegocio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos de la página pública de autogestión de cada negocio.
     *
     * El slug es la dirección con la que el negocio comparte su página
     * (/reservar/spa-fashion), así que su unicidad es GLOBAL y no por tenant:
     * dos negocios distintos no pueden competir por la misma URL pública.
     *
     * Nace nullable porque los negocios que ya existen no tienen uno; se les
     * rellena aquí mismo, y el índice único se agrega después del relleno para
     * que no choque con las filas antiguas mientras se pueblan.
     */
    public function up(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->string('slug', 100)->nullable()->after('nombre_negocio');
            $table->string('whatsapp_numero', 20)->nullable()->after('telefono_contacto');
            $table->text('politica_cancelacion')->nullable()->after('hora_cierre');
        });

        $this->rellenarSlugsExistentes();

        Schema::table('negocios', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Le da un slug a cada negocio que ya estaba creado.
     *
     * Se apoya en SvcNegocio para no tener dos versiones de la misma regla: si
     * mañana cambia cómo se arma un slug, cambia en un solo sitio. Va fila por
     * fila a propósito, porque cada slug asignado condiciona el siguiente
     * (dos negocios con el mismo nombre no pueden quedar con el mismo slug).
     */
    private function rellenarSlugsExistentes(): void
    {
        $svcNegocio = new SvcNegocio;

        $negocios = DB::table('negocios')
            ->select('id_negocio', 'nombre_negocio')
            ->orderBy('id_negocio')
            ->get();

        foreach ($negocios as $negocio) {
            DB::table('negocios')
                ->where('id_negocio', $negocio->id_negocio)
                ->update(['slug' => $svcNegocio->generarSlug($negocio->nombre_negocio, $negocio->id_negocio)]);
        }
    }

    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'whatsapp_numero', 'politica_cancelacion']);
        });
    }
};
