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
        Schema::table('productos', function (Blueprint $table) {
            $table->string('sku', 50)->nullable()->after('nombre');

            // Único por negocio, no global: dos negocios distintos pueden usar
            // el mismo código sin estorbarse. MySQL admite varios NULL dentro de
            // un índice único, así que los productos sin SKU no chocan entre sí.
            $table->unique(['tenant_id', 'sku'], 'productos_tenant_sku_unico');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropUnique('productos_tenant_sku_unico');
            $table->dropColumn('sku');
        });
    }
};
