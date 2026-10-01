<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un registro público esperando la confirmación del correo. Ver la migración
 * crear_tabla_registros_pendientes para el porqué de cada columna.
 */
class RegistroPendiente extends Model
{
    use HasFactory;

    protected $table = 'registros_pendientes';

    protected $primaryKey = 'id_registro_pendiente';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_registro_pendiente',
        'nombre_negocio',
        'rubro',
        'telefono_contacto',
        'nombre_admin',
        'correo',
        'clave_hash',
        'token_hash',
        'fecha_expiracion',
        'confirmado_en',
        'fecha_registro',
    ];

    /** Nunca deben salir en un toArray()/JSON, ni por descuido. */
    protected $hidden = [
        'clave_hash',
        'token_hash',
    ];
}
