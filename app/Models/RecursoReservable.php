<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecursoReservable extends Model
{
    use HasFactory;

    protected $table = 'recursos_reservables';

    protected $primaryKey = 'id_recurso';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_recurso',
        'tenant_id',
        'categoria',
        'nombre',
        'descripcion',
        'duracion_minutos',
        'precio',
        'capacidad',
        'usuario_registra',
        'fecha_registro',
        'estado',
    ];
}
