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
        Schema::create('empleados', function (Blueprint $table) {
            $table->bigIncrements('id_empleado');
            $table->unsignedBigInteger('tenant_id');
            $table->string('nombre', 150);
            $table->string('telefono', 30);
            $table->string('email', 150)->nullable();
            $table->string('cargo', 100)->nullable();
            $table->decimal('porcentaje_comision', 5, 2)->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->string('usuario_registra', 100)->nullable();
            $table->dateTime('fecha_registro')->nullable();
            $table->tinyInteger('estado')->default(1);

            $table->foreign('tenant_id')->references('id_negocio')->on('negocios')->restrictOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
