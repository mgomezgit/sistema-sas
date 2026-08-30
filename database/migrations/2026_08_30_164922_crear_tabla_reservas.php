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
        Schema::create('reservas', function (Blueprint $table) {
            $table->bigIncrements('id_reserva');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('id_cliente');
            $table->unsignedBigInteger('id_recurso');
            $table->unsignedBigInteger('id_empleado')->nullable();
            $table->date('fecha_reserva');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->string('estado_reserva', 20)->default('pendiente');
            $table->text('notas')->nullable();
            $table->string('usuario_registra', 100)->nullable();
            $table->dateTime('fecha_registro')->nullable();
            $table->tinyInteger('estado')->default(1);

            $table->foreign('tenant_id')->references('id_negocio')->on('negocios')->restrictOnDelete();
            $table->foreign('id_cliente')->references('id_cliente')->on('clientes')->restrictOnDelete();
            $table->foreign('id_recurso')->references('id_recurso')->on('recursos_reservables')->restrictOnDelete();
            $table->foreign('id_empleado')->references('id_empleado')->on('empleados')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
