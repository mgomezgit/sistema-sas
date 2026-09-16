<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alinea el nombre de la columna con la convención del resto del proyecto:
     * la FK al servicio se llama "id_recurso" en reservas y la PK de
     * recursos_reservables también, así que comisiones_tarifas era el único
     * lugar que la llamaba distinto.
     *
     * Va como migración aparte (y no editando la que creó la tabla) porque esa
     * ya se ejecutó. El índice único no hace falta recrearlo: se creó con
     * nombre explícito ("comisiones_tarifas_unica") y el motor actualiza por su
     * cuenta la columna a la que apunta, igual que la llave foránea.
     */
    public function up(): void
    {
        Schema::table('comisiones_tarifas', function (Blueprint $table) {
            $table->renameColumn('id_recurso_reservable', 'id_recurso');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comisiones_tarifas', function (Blueprint $table) {
            $table->renameColumn('id_recurso', 'id_recurso_reservable');
        });
    }
};
