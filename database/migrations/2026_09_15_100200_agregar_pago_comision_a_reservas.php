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
        Schema::table('reservas', function (Blueprint $table) {
            // Null = comisión todavía no pagada. Es la marca que evita que una
            // misma cita se pague dos veces en periodos que se solapen.
            $table->unsignedBigInteger('id_pago_comision')->nullable()->after('notas');

            $table->foreign('id_pago_comision')->references('id_pago_comision')->on('pagos_comisiones')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropForeign(['id_pago_comision']);
            $table->dropColumn('id_pago_comision');
        });
    }
};
