<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Contadores mensuales del asistente creativo por negocio (generaciones y
 * tokens). Una fila por negocio y mes (índice único).
 */
class IaUso extends Model
{
    use HasFactory;

    protected $table = 'ia_uso';

    protected $primaryKey = 'id_ia_uso';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_ia_uso',
        'tenant_id',
        'mes',
        'generaciones',
        'tokens_entrada',
        'tokens_salida',
        'fecha_registro',
    ];
}
