<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComisionTarifa extends Model
{
    use HasFactory;

    protected $table = 'comisiones_tarifas';

    protected $primaryKey = 'id_comision_tarifa';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_comision_tarifa',
        'tenant_id',
        'id_empleado',
        'id_recurso',
        'porcentaje_comision',
        'usuario_registra',
        'fecha_registro',
        'estado',
    ];
}
