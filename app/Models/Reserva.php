<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reservas';

    protected $primaryKey = 'id_reserva';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_reserva',
        'tenant_id',
        'id_cliente',
        'id_recurso',
        'id_empleado',
        'fecha_reserva',
        'hora_inicio',
        'hora_fin',
        'estado_reserva',
        'notas',
        'usuario_registra',
        'fecha_registro',
        'estado',
    ];
}
