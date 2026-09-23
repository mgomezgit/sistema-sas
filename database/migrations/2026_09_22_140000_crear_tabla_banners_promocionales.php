<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Banners rotativos que cada negocio muestra en su página pública.
     *
     * Las dos fechas de vigencia son opcionales y se leen como extremos
     * abiertos: sin fecha_inicio vale desde siempre, sin fecha_fin vale para
     * siempre. Así una promoción permanente no obliga a inventarse fechas.
     */
    public function up(): void
    {
        Schema::create('banners_promocionales', function (Blueprint $table) {
            $table->bigIncrements('id_banner');
            $table->unsignedBigInteger('tenant_id');
            // Ruta relativa dentro del disco público. La URL completa se arma al
            // leer, para que mover el disco no obligue a reescribir la tabla.
            $table->string('imagen_path', 255);
            $table->string('titulo', 150)->nullable();
            $table->string('texto', 300)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->string('usuario_registra', 100)->nullable();
            $table->dateTime('fecha_registro')->nullable();
            $table->tinyInteger('estado')->default(1);

            $table->foreign('tenant_id')->references('id_negocio')->on('negocios')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners_promocionales');
    }
};
