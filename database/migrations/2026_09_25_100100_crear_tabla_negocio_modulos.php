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
        Schema::create('negocio_modulos', function (Blueprint $table) {
            $table->bigIncrements('id_negocio_modulo');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('id_modulo');
            // Por defecto FALSE: un negocio nuevo no estrena ningún módulo de
            // pago. Solo el super admin los activa.
            $table->boolean('activo')->default(false);
            $table->dateTime('fecha_activacion')->nullable();
            $table->dateTime('fecha_desactivacion')->nullable();
            $table->string('usuario_registra', 100)->nullable();
            $table->dateTime('fecha_registro')->nullable();

            $table->foreign('tenant_id')->references('id_negocio')->on('negocios')->restrictOnDelete();
            $table->foreign('id_modulo')->references('id_modulo')->on('modulos_plataforma')->restrictOnDelete();

            // Una sola fila por negocio+módulo: el estado se alterna sobre esa
            // misma fila, nunca acumulando filas nuevas. El nombre va explícito
            // porque el que generaría Laravel supera los 64 caracteres de MySQL.
            $table->unique(['tenant_id', 'id_modulo'], 'negocio_modulos_unico');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('negocio_modulos');
    }
};
