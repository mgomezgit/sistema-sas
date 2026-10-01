<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un código de 6 dígitos para recuperar la clave. Ver la migración
 * crear_tabla_codigos_recuperacion_clave para el porqué de cada columna.
 */
class CodigoRecuperacionClave extends Model
{
    use HasFactory;

    protected $table = 'codigos_recuperacion_clave';

    protected $primaryKey = 'id_codigo';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_codigo',
        'id_usuario',
        'codigo_hash',
        'fecha_expiracion',
        'usado_en',
        'fecha_registro',
    ];

    /** Nunca debe salir en un toArray()/JSON, ni por descuido. */
    protected $hidden = [
        'codigo_hash',
    ];
}
