<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Libro de pagos de la plataforma: registro INTERNO que solo ve el super admin.
 *
 * Mateo cobra por fuera (transferencia, Nequi, QR). No hay pasarela ni factura
 * electrónica; el documento que recibe el negocio es un "Comprobante de pago",
 * nunca una factura. El consecutivo es único y NUNCA se reutiliza, ni de un
 * registro anulado. Anular conserva el consecutivo y excluye el registro de los
 * totales, y NO cambia la vigencia del módulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_plataforma', function (Blueprint $table) {
            $table->bigIncrements('id_pago');
            $table->unsignedBigInteger('tenant_id');
            // Nullable: el módulo puede retirarse del catálogo con el tiempo; el
            // concepto guarda el nombre al momento del pago, así que el registro
            // se sostiene solo.
            $table->unsignedBigInteger('id_modulo')->nullable();
            $table->string('concepto', 120);
            $table->date('periodo_desde');
            $table->date('periodo_hasta')->nullable();
            // Pesos colombianos, enteros (sin decimales).
            $table->unsignedBigInteger('monto');
            $table->string('metodo', 20);
            $table->string('referencia', 100)->nullable();
            $table->unsignedInteger('consecutivo');
            // Sin llave foránea a propósito: es un hecho registrado, no un
            // vínculo que deba seguir vivo si la cuenta cambia (igual que
            // id_usuario_registra en otros módulos).
            $table->unsignedBigInteger('id_usuario_registra');
            $table->dateTime('fecha_registro');
            $table->dateTime('comprobante_enviado_en')->nullable();
            $table->dateTime('anulado_en')->nullable();
            $table->unsignedBigInteger('anulado_por')->nullable();
            $table->string('motivo_anulacion', 200)->nullable();

            $table->foreign('tenant_id')->references('id_negocio')->on('negocios')->restrictOnDelete();
            $table->foreign('id_modulo')->references('id_modulo')->on('modulos_plataforma')->nullOnDelete();

            $table->index('tenant_id', 'pagos_plataforma_tenant_id_index');
            $table->index('fecha_registro', 'pagos_plataforma_fecha_registro_index');
            // El consecutivo es único en toda la plataforma: nunca se repite.
            $table->unique('consecutivo', 'pagos_plataforma_consecutivo_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_plataforma');
    }
};
