<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de pago "ia" (asistente creativo): genera PROPUESTAS de texto para el
 * admin del negocio. Nunca guarda ni publica nada por su cuenta.
 *
 * 1. La fila del catálogo. NO se activa en ningún negocio: nace apagado y
 *    solo el super admin lo enciende negocio por negocio.
 * 2. ia_uso: contadores mensuales por negocio (generaciones y tokens).
 * 3. ia_actividad: historial de METADATOS de cada uso (quién, cuándo, tipo,
 *    tono, estado y tokens). NUNCA el texto generado ni el tema escrito por
 *    el admin: no hay columna donde guardarlos.
 * 4. negocios.ia_limite_mensual: límite propio de generaciones al mes;
 *    null = el valor por defecto de config/ia.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos_plataforma')->insert([
            'clave' => 'ia',
            'nombre' => 'Asistente creativo (IA)',
            'descripcion' => 'Propone textos para banners, frases y descripciones de servicios con el tono que el negocio elija. Solo propone: el negocio decide qué usar.',
            'usuario_registra' => 'migracion',
            'fecha_registro' => now(),
            'estado' => 1,
        ]);

        Schema::create('ia_uso', function (Blueprint $table) {
            $table->bigIncrements('id_ia_uso');
            $table->unsignedBigInteger('tenant_id');
            // Mes calendario en formato YYYY-MM: un mes nuevo es una fila nueva.
            $table->string('mes', 7);
            $table->unsignedInteger('generaciones')->default(0);
            $table->unsignedBigInteger('tokens_entrada')->default(0);
            $table->unsignedBigInteger('tokens_salida')->default(0);
            $table->dateTime('fecha_registro')->nullable();

            $table->foreign('tenant_id')->references('id_negocio')->on('negocios')->restrictOnDelete();
            $table->unique(['tenant_id', 'mes'], 'ia_uso_unico');
        });

        Schema::create('ia_actividad', function (Blueprint $table) {
            $table->bigIncrements('id_ia_actividad');
            $table->unsignedBigInteger('tenant_id');
            // Sin llave foránea, igual que historial_estados_reserva: es un hecho
            // registrado, no un vínculo que deba seguir vivo si la cuenta cambia.
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->string('tipo', 30);
            $table->string('tono', 20)->nullable();
            // ok | error | bloqueado
            $table->string('estado', 20);
            $table->unsignedInteger('tokens_entrada')->default(0);
            $table->unsignedInteger('tokens_salida')->default(0);
            $table->dateTime('fecha_registro');

            $table->foreign('tenant_id')->references('id_negocio')->on('negocios')->restrictOnDelete();
            $table->index(['tenant_id', 'fecha_registro'], 'ia_actividad_negocio_fecha');
        });

        Schema::table('negocios', function (Blueprint $table) {
            $table->unsignedInteger('ia_limite_mensual')->nullable()->after('chatbot_limite_mensual');
        });
    }

    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->dropColumn('ia_limite_mensual');
        });

        Schema::dropIfExists('ia_actividad');
        Schema::dropIfExists('ia_uso');

        $idModulo = DB::table('modulos_plataforma')->where('clave', 'ia')->value('id_modulo');

        if ($idModulo !== null) {
            DB::table('negocio_modulos')->where('id_modulo', $idModulo)->delete();
            DB::table('modulos_plataforma')->where('id_modulo', $idModulo)->delete();
        }
    }
};
