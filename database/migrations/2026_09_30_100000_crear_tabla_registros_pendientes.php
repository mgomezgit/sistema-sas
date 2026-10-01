<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registros públicos a la espera de que se confirme el correo.
 *
 * Hasta que la persona abre el link de confirmación NO existe ni negocio ni
 * usuario: solo esta fila. No lleva tenant_id porque todavía no hay negocio.
 *
 * - clave_hash: la clave elegida, hasheada desde el primer momento (bcrypt,
 *   igual que usuarios.clave). Nunca se guarda en texto plano.
 * - token_hash: sha256 del token del link. El token solo existe en claro en el
 *   correo; aquí se guarda su hash, así que leer esta tabla no permite
 *   confirmar ningún registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_pendientes', function (Blueprint $table) {
            $table->bigIncrements('id_registro_pendiente');
            $table->string('nombre_negocio', 150);
            $table->string('rubro', 20);
            $table->string('telefono_contacto', 30);
            $table->string('nombre_admin', 150);
            $table->string('correo', 150);
            $table->string('clave_hash', 255);
            $table->string('token_hash', 64)->unique();
            $table->dateTime('fecha_expiracion');
            $table->dateTime('confirmado_en')->nullable();
            $table->dateTime('fecha_registro');

            $table->index('correo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_pendientes');
    }
};
