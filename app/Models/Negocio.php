<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Negocio extends Model
{
    use HasFactory;

    protected $table = 'negocios';

    protected $primaryKey = 'id_negocio';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_negocio',
        'nombre_negocio',
        'rubro',
        'usuario_registra',
        'fecha_registro',
        'estado',
    ];
}
