<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anulación de pagos de comisión. Un pago NUNCA se borra: queda marcado como
 * anulado con quién, cuándo y por qué, y su monto_total no se toca.
 *
 * anulado_por es el id del usuario que anuló, SIN llave foránea (igual que
 * id_usuario en historial_estados_reserva): es un hecho registrado, no un
 * vínculo que deba seguir vivo si la cuenta cambia.
 *
 * reservas_liberadas guarda (en JSON) los ids de las reservas que el pago
 * tenía al anularse, porque al liberar esas reservas su id_pago_comision
 * pasa a null y ya no habría otra forma de saber cuáles eran.
 *
 * Los pagos existentes quedan vigentes (anulado_en null): no se tocan datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_comisiones', function (Blueprint $table) {
            $table->dateTime('anulado_en')->nullable()->after('fecha_pago');
            $table->unsignedBigInteger('anulado_por')->nullable()->after('anulado_en');
            $table->string('motivo_anulacion', 200)->nullable()->after('anulado_por');
            $table->text('reservas_liberadas')->nullable()->after('motivo_anulacion');
        });
    }

    public function down(): void
    {
        Schema::table('pagos_comisiones', function (Blueprint $table) {
            $table->dropColumn(['anulado_en', 'anulado_por', 'motivo_anulacion', 'reservas_liberadas']);
        });
    }
};
