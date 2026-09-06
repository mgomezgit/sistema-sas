<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'productos';

    protected $primaryKey = 'id_producto';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_producto',
        'tenant_id',
        'nombre',
        'descripcion',
        'cantidad_actual',
        'cantidad_minima',
        'usuario_registra',
        'fecha_registro',
        'estado',
    ];
}
