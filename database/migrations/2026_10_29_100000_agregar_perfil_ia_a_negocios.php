<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perfil PRIVADO del negocio para el asistente creativo (módulo "ia"):
     * qué lo hace especial y a quién le habla. No se muestra en la página
     * pública, no entra al contexto del chatbot ni al historial de IA. Los
     * negocios existentes quedan sin perfil.
     */
    public function up(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->string('ia_especialidad', 200)->nullable()->after('frase_hasta');
            $table->string('ia_publico', 120)->nullable()->after('ia_especialidad');
        });
    }

    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->dropColumn(['ia_especialidad', 'ia_publico']);
        });
    }
};
