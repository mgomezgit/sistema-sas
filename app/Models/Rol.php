<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $primaryKey = 'id_rol';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_rol',
        'nombre_rol',
        'usuario_registra',
        'fecha_registro',
        'estado',
    ];
}
