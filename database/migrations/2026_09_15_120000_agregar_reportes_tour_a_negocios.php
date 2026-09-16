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
            // El paso "Reportes" del onboarding no se completa creando nada:
            // se da por cumplido cuando el negocio terminó el tour guiado que
            // explica los reportes disponibles.
            $table->boolean('reportes_tour_visto')->default(false)->after('bienvenida_vista');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->dropColumn('reportes_tour_visto');
        });
    }
};
