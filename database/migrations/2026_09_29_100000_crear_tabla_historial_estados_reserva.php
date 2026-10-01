<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rastro de cada cambio de estado de una reserva: quién, cuándo, de qué a qué.
 *
 * Es un registro histórico APPEND-ONLY: una fila se inserta y nunca se edita ni
 * se borra (el modelo lo impide). Por eso no lleva usuario_registra ni estado
 * como las demás tablas: no es una entidad que se gestione, es un hecho que
 * ya ocurrió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_estados_reserva', function (Blueprint $table) {
            $table->bigIncrements('id_historial');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('id_reserva');
            $table->string('estado_anterior', 20);
            $table->string('estado_nuevo', 20);
            // Null = el cambio lo hizo un proceso de sistema, no una persona.
            // Sin llave foránea a propósito: el historial guarda el id de quien
            // actuó como un hecho registrado, independiente de lo que pase
            // después con la tabla usuarios.
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->dateTime('fecha_cambio');

            $table->foreign('tenant_id')->references('id_negocio')->on('negocios')->restrictOnDelete();
            $table->foreign('id_reserva')->references('id_reserva')->on('reservas')->restrictOnDelete();
            $table->index(['tenant_id', 'id_reserva']);
            $table->index('id_usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_estados_reserva');
    }
};
