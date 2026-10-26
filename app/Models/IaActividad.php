<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Historial del asistente creativo: SOLO metadatos de cada uso (quién,
 * cuándo, tipo, tono, estado y tokens). Nunca el texto generado ni el tema.
 */
class IaActividad extends Model
{
    use HasFactory;

    protected $table = 'ia_actividad';

    protected $primaryKey = 'id_ia_actividad';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_ia_actividad',
        'tenant_id',
        'id_usuario',
        'tipo',
        'tono',
        'estado',
        'tokens_entrada',
        'tokens_salida',
        'fecha_registro',
    ];
}
