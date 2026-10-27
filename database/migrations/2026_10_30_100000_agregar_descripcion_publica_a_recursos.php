<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Descripción PÚBLICA del servicio (hasta 200 caracteres): se muestra en la
     * página pública al tocar la tarjeta. Es una columna distinta de
     * "descripcion", que es una nota INTERNA y nunca sale a la web. Los
     * servicios existentes quedan sin descripción pública.
     */
    public function up(): void
    {
        Schema::table('recursos_reservables', function (Blueprint $table) {
            $table->string('descripcion_publica', 200)->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('recursos_reservables', function (Blueprint $table) {
            $table->dropColumn('descripcion_publica');
        });
    }
};
