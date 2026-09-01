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
            // Tema del backoffice: el modo define claro/oscuro y el acento el color de marca.
            $table->string('modo_tema', 10)->default('claro')->after('telefono_contacto');
            $table->string('color_acento', 20)->default('oro_rosa')->after('modo_tema');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->dropColumn(['modo_tema', 'color_acento']);
        });
    }
};
