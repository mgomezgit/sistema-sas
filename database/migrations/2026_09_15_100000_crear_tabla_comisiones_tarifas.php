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
        Schema::create('comisiones_tarifas', function (Blueprint $table) {
            $table->bigIncrements('id_comision_tarifa');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('id_empleado');
            $table->unsignedBigInteger('id_recurso_reservable');
            $table->decimal('porcentaje_comision', 5, 2);
            $table->string('usuario_registra', 100)->nullable();
            $table->dateTime('fecha_registro')->nullable();
            $table->tinyInteger('estado')->default(1);

            $table->foreign('tenant_id')->references('id_negocio')->on('negocios')->restrictOnDelete();
            $table->foreign('id_empleado')->references('id_empleado')->on('empleados')->restrictOnDelete();
            $table->foreign('id_recurso_reservable')->references('id_recurso')->on('recursos_reservables')->restrictOnDelete();

            // Una sola tarifa por combinación empleado+servicio dentro del negocio.
            // El nombre va explícito porque el que generaría Laravel por defecto
            // supera el límite de 64 caracteres de MySQL.
            $table->unique(['tenant_id', 'id_empleado', 'id_recurso_reservable'], 'comisiones_tarifas_unica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comisiones_tarifas');
    }
};
