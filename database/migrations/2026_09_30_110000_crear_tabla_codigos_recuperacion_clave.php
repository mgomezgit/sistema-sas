<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos de 6 dígitos para recuperar la clave de una cuenta.
 *
 * - codigo_hash: el código, hasheado con Hash::make (bcrypt). Nunca en claro:
 *   leer esta tabla no permite cambiar la clave de nadie.
 * - fecha_expiracion: 60 minutos desde que se pidió.
 * - usado_en: se llena al cambiar la clave; un código usado no vuelve a servir.
 *
 * La FK se llama id_usuario (no usuario_id) para seguir la convención de todas
 * las demás tablas del proyecto, donde la llave de usuarios es id_usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codigos_recuperacion_clave', function (Blueprint $table) {
            $table->bigIncrements('id_codigo');
            $table->unsignedBigInteger('id_usuario');
            $table->string('codigo_hash', 255);
            $table->dateTime('fecha_expiracion');
            $table->dateTime('usado_en')->nullable();
            $table->dateTime('fecha_registro');

            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->restrictOnDelete();
            $table->index(['id_usuario', 'usado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_recuperacion_clave');
    }
};
