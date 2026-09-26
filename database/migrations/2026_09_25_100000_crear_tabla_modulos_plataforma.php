<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('modulos_plataforma', function (Blueprint $table) {
            $table->bigIncrements('id_modulo');
            // Identificador técnico con el que lo nombran el middleware, el
            // Service y el sidebar. No cambia nunca: si cambiara, todas esas
            // referencias apuntarían a un módulo inexistente y el negocio
            // perdería el acceso en silencio.
            $table->string('clave', 50)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->string('usuario_registra', 100)->nullable();
            $table->dateTime('fecha_registro')->nullable();
            // Aquí 'estado' dice si el módulo EXISTE en la plataforma, no si un
            // negocio lo tiene contratado: eso vive en negocio_modulos.activo.
            $table->tinyInteger('estado')->default(1);
        });

        // Catálogo inicial: el único módulo de pago construido hoy. Los que
        // vengan después (Catálogo, Chatbot) agregan su propia fila en su
        // propia migración, cuando se construyan. No hay pantalla para crear
        // módulos: es un catálogo de plataforma, no un dato del negocio.
        DB::table('modulos_plataforma')->insert([
            'clave' => 'comisiones',
            'nombre' => 'Cálculo de comisiones',
            'descripcion' => 'Informe de comisiones por empleado, tarifas por servicio e historial de pagos.',
            'usuario_registra' => 'migracion',
            'fecha_registro' => now(),
            'estado' => 1,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modulos_plataforma');
    }
};
