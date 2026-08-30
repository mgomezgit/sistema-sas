<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    use HasFactory;

    protected $table = 'empleados';

    protected $primaryKey = 'id_empleado';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_empleado',
        'tenant_id',
        'nombre',
        'telefono',
        'email',
        'cargo',
        'porcentaje_comision',
        'id_usuario',
        'usuario_registra',
        'fecha_registro',
        'estado',
    ];
}
