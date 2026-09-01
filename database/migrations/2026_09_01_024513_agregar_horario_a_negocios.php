<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            // Días en que atiende el negocio: lista separada por comas (1=lunes ... 7=domingo).
            $table->string('dias_atencion', 20)->nullable()->after('color_acento');
            $table->time('hora_apertura')->nullable()->after('dias_atencion');
            $table->time('hora_cierre')->nullable()->after('hora_apertura');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->dropColumn(['dias_atencion', 'hora_apertura', 'hora_cierre']);
        });
    }
};
