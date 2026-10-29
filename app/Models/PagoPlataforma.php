<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un pago registrado en el libro de pagos de la plataforma (interno del super
 * admin). El consecutivo es único y nunca se reutiliza.
 */
class PagoPlataforma extends Model
{
    use HasFactory;

    protected $table = 'pagos_plataforma';

    protected $primaryKey = 'id_pago';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_pago',
        'tenant_id',
        'id_modulo',
        'concepto',
        'periodo_desde',
        'periodo_hasta',
        'monto',
        'metodo',
        'referencia',
        'consecutivo',
        'id_usuario_registra',
        'fecha_registro',
        'comprobante_enviado_en',
        'anulado_en',
        'anulado_por',
        'motivo_anulacion',
    ];
}
