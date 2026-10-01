<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Una fila por cada cambio de estado de una reserva. APPEND-ONLY: se inserta y
 * nunca se modifica ni se borra. Guardar o borrar una fila ya cargada lanza
 * una excepción, para que ningún código lo haga sin querer. Ojo: los eventos
 * de Eloquent no cubren un update()/delete() masivo por query (->where()->
 * delete()); la regla para ese caso es simplemente no escribir ese código.
 *
 * Solo lo escribe SvcReserva::cambiarEstado(), dentro de la misma transacción
 * que el cambio de estado.
 */
class HistorialEstadoReserva extends Model
{
    use HasFactory;

    protected $table = 'historial_estados_reserva';

    protected $primaryKey = 'id_historial';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_historial',
        'tenant_id',
        'id_reserva',
        'estado_anterior',
        'estado_nuevo',
        'id_usuario',
        'fecha_cambio',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('El historial de estados de reserva no se puede modificar.');
        });

        static::deleting(function () {
            throw new \LogicException('El historial de estados de reserva no se puede borrar.');
        });
    }
}
