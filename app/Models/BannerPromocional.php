<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BannerPromocional extends Model
{
    use HasFactory;

    protected $table = 'banners_promocionales';

    protected $primaryKey = 'id_banner';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_banner',
        'tenant_id',
        'imagen_path',
        'titulo',
        'texto',
        'fecha_inicio',
        'fecha_fin',
        'orden',
        'usuario_registra',
        'fecha_registro',
        'estado',
    ];
}
