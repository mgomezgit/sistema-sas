<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De dónde salió cada reserva.
     *
     * 'admin'   la creó alguien del negocio desde el backoffice.
     * 'publico' la pidió un cliente desde la página pública, y llega como
     *           solicitud pendiente de revisar.
     *
     * Es una marca explícita y no una deducción: se podría intentar adivinar
     * mirando si la reserva no tiene empleado asignado, pero eso también pasa
     * en una reserva del admin que todavía no se ha repartido. Una solicitud
     * del público tiene que poder reconocerse sin ambigüedad.
     *
     * Las filas que ya existen son todas del backoffice, que es lo que dice el
     * default: nacen como 'admin' sin tener que tocarlas.
     */
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->string('origen', 20)->default('admin')->after('estado_reserva');
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn('origen');
        });
    }
};
