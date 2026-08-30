<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $primaryKey = 'id_cliente';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_cliente',
        'tenant_id',
        'nombre',
        'telefono',
        'email',
        'documento_identidad',
        'fecha_nacimiento',
        'notas',
        'usuario_registra',
        'fecha_registro',
        'estado',
    ];
}
