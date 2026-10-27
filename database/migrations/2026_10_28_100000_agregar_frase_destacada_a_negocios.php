<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frase destacada: UNA por negocio (franja bajo el encabezado de la página
     * pública). frase_hasta es el último día en que se ve (inclusive); null =
     * sin vencimiento. Los negocios existentes quedan sin frase.
     */
    public function up(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->string('frase_destacada', 140)->nullable()->after('politica_cancelacion');
            $table->date('frase_hasta')->nullable()->after('frase_destacada');
        });
    }

    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->dropColumn(['frase_destacada', 'frase_hasta']);
        });
    }
};
