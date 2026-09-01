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
            // Onboarding: el tema se marca al guardarlo, el tour al cerrarlo manualmente.
            $table->boolean('tema_personalizado')->default(false)->after('hora_cierre');
            $table->boolean('tour_completado')->default(false)->after('tema_personalizado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->dropColumn(['tema_personalizado', 'tour_completado']);
        });
    }
};
