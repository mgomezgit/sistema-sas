<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PagoComision extends Model
{
    use HasFactory;

    protected $table = 'pagos_comisiones';

    protected $primaryKey = 'id_pago_comision';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_pago_comision',
        'tenant_id',
        'id_empleado',
        'fecha_inicio',
        'fecha_fin',
        'monto_total',
        'fecha_pago',
        'usuario_registra',
        'fecha_registro',
        'estado',
    ];
}
