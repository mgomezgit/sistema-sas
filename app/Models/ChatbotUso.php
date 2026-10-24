<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Contadores mensuales del chatbot por negocio: lo único que el chatbot guarda
 * de forma permanente. Una fila por negocio+mes (índice único).
 */
class ChatbotUso extends Model
{
    use HasFactory;

    protected $table = 'chatbot_uso';

    protected $primaryKey = 'id_chatbot_uso';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_chatbot_uso',
        'tenant_id',
        'mes',
        'mensajes',
        'tokens_entrada',
        'tokens_salida',
        'fecha_registro',
    ];
}
