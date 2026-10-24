<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de pago "chatbot" (asistente virtual de la página pública).
 *
 * Tres piezas:
 *
 * 1. La fila del catálogo de módulos. NO se activa en ningún negocio: el
 *    módulo nace apagado y solo el super admin lo enciende negocio por negocio.
 * 2. chatbot_uso: contadores mensuales por negocio (mensajes y tokens). Es lo
 *    ÚNICO que el chatbot guarda de forma permanente — ni preguntas, ni
 *    respuestas, ni nada del visitante.
 * 3. negocios.chatbot_limite_mensual: límite propio de mensajes al mes. null =
 *    usar el de config/chatbot.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos_plataforma')->insert([
            'clave' => 'chatbot',
            'nombre' => 'Asistente virtual (chatbot)',
            'descripcion' => 'Responde en la página pública las preguntas de los clientes sobre servicios, precios, horario y política de cancelación, con los datos que el negocio ya publica. No agenda citas.',
            'usuario_registra' => 'migracion',
            'fecha_registro' => now(),
            'estado' => 1,
        ]);

        Schema::create('chatbot_uso', function (Blueprint $table) {
            $table->bigIncrements('id_chatbot_uso');
            $table->unsignedBigInteger('tenant_id');
            // Mes calendario en formato YYYY-MM.
            $table->string('mes', 7);
            $table->unsignedInteger('mensajes')->default(0);
            $table->unsignedBigInteger('tokens_entrada')->default(0);
            $table->unsignedBigInteger('tokens_salida')->default(0);
            $table->dateTime('fecha_registro')->nullable();

            $table->foreign('tenant_id')->references('id_negocio')->on('negocios')->restrictOnDelete();

            // Una sola fila por negocio y mes: los contadores se incrementan
            // sobre ella con increment(), nunca leyendo y reescribiendo.
            $table->unique(['tenant_id', 'mes'], 'chatbot_uso_unico');
        });

        Schema::table('negocios', function (Blueprint $table) {
            $table->unsignedInteger('chatbot_limite_mensual')->nullable()->after('politica_cancelacion');
        });
    }

    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->dropColumn('chatbot_limite_mensual');
        });

        Schema::dropIfExists('chatbot_uso');

        // La activación por negocio apunta al catálogo con restrictOnDelete:
        // primero se borran esas filas, luego la del módulo.
        $idModulo = DB::table('modulos_plataforma')->where('clave', 'chatbot')->value('id_modulo');

        if ($idModulo !== null) {
            DB::table('negocio_modulos')->where('id_modulo', $idModulo)->delete();
            DB::table('modulos_plataforma')->where('id_modulo', $idModulo)->delete();
        }
    }
};
