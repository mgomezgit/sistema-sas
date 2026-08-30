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
        Schema::create('recursos_reservables', function (Blueprint $table) {
            $table->bigIncrements('id_recurso');
            $table->unsignedBigInteger('tenant_id');
            $table->string('categoria', 100)->nullable();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->unsignedInteger('duracion_minutos');
            $table->decimal('precio', 10, 2);
            $table->unsignedInteger('capacidad')->nullable();
            $table->string('usuario_registra', 100)->nullable();
            $table->dateTime('fecha_registro')->nullable();
            $table->tinyInteger('estado')->default(1);

            $table->foreign('tenant_id')->references('id_negocio')->on('negocios')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recursos_reservables');
    }
};
