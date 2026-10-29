<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vigencia de los módulos de pago por negocio.
 *
 * vigente_hasta es el ÚLTIMO día en que el módulo funciona, inclusive; nulo =
 * sin vencimiento. El acceso se corta en tiempo real (SvcModulo::estaActivo),
 * no por el proceso diario: este solo limpia el estado y avisa.
 *
 * origen y nota son PRIVADOS del super admin: nunca salen a un admin de negocio.
 * Los tres campos *_enviado_en / *_avisado_en son banderas de "aviso ya enviado
 * para esta fecha de vigencia", para no duplicar correos; se limpian al cambiar
 * vigente_hasta.
 *
 * Las filas existentes quedan como están: vigente_hasta nulo (sin vencimiento),
 * origen nulo (se muestra "Sin registrar").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negocio_modulos', function (Blueprint $table) {
            $table->date('vigente_hasta')->nullable()->after('activo');
            $table->string('origen', 20)->nullable()->after('vigente_hasta');
            $table->string('nota', 200)->nullable()->after('origen');
            $table->dateTime('aviso_7_enviado_en')->nullable()->after('fecha_desactivacion');
            $table->dateTime('aviso_1_enviado_en')->nullable()->after('aviso_7_enviado_en');
            $table->dateTime('vencido_avisado_en')->nullable()->after('aviso_1_enviado_en');

            $table->index('vigente_hasta', 'negocio_modulos_vigente_hasta_index');
        });
    }

    public function down(): void
    {
        Schema::table('negocio_modulos', function (Blueprint $table) {
            $table->dropIndex('negocio_modulos_vigente_hasta_index');
            $table->dropColumn([
                'vigente_hasta',
                'origen',
                'nota',
                'aviso_7_enviado_en',
                'aviso_1_enviado_en',
                'vencido_avisado_en',
            ]);
        });
    }
};
