<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo maestro de módulos de pago de la plataforma.
 *
 * No es un dato por negocio: aquí vive qué módulos EXISTEN. Qué negocio tiene
 * cada uno contratado se guarda en negocio_modulos.
 */
class ModuloPlataforma extends Model
{
    use HasFactory;

    protected $table = 'modulos_plataforma';

    protected $primaryKey = 'id_modulo';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_modulo',
        'clave',
        'nombre',
        'descripcion',
        'usuario_registra',
        'fecha_registro',
        'estado',
    ];
}
