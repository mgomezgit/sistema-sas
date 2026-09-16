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
        Schema::create('pagos_comisiones', function (Blueprint $table) {
            $table->bigIncrements('id_pago_comision');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('id_empleado');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->decimal('monto_total', 10, 2);
            $table->dateTime('fecha_pago');
            $table->string('usuario_registra', 100)->nullable();
            $table->dateTime('fecha_registro')->nullable();
            $table->tinyInteger('estado')->default(1);

            $table->foreign('tenant_id')->references('id_negocio')->on('negocios')->restrictOnDelete();
            $table->foreign('id_empleado')->references('id_empleado')->on('empleados')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos_comisiones');
    }
};
