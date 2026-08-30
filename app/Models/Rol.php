<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $primaryKey = 'id_rol';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_rol',
        'nombre_rol',
        'usuario_registra',
        'fecha_registro',
        'estado',
    ];

    /**
     * Indica si el rol recibido corresponde al rol "empleado". Centraliza la
     * comparación para no repartir comparaciones de id_rol por los controllers.
     */
    public static function esRolEmpleado($idRol): bool
    {
        if (empty($idRol)) {
            return false;
        }

        return self::where('id_rol', $idRol)->value('nombre_rol') === 'empleado';
    }
}
