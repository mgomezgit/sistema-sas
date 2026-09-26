<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Qué módulos de pago tiene activos cada negocio.
 *
 * Una sola fila por negocio+módulo (índice único): activar y desactivar
 * alternan 'activo' sobre esa misma fila, nunca acumulan filas nuevas, para
 * que el estado vigente sea siempre inequívoco.
 */
class NegocioModulo extends Model
{
    use HasFactory;

    protected $table = 'negocio_modulos';

    protected $primaryKey = 'id_negocio_modulo';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_negocio_modulo',
        'tenant_id',
        'id_modulo',
        'activo',
        'fecha_activacion',
        'fecha_desactivacion',
        'usuario_registra',
        'fecha_registro',
    ];
}
