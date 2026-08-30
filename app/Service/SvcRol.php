<?php

namespace App\Service;

use App\Models\Rol;
use Illuminate\Support\Facades\Log;

class SvcRol
{
    public function crear($info)
    {
        try {
            $rol = Rol::create($info);

            return $rol->id_rol;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function listar()
    {
        try {
            return Rol::select('id_rol', 'nombre_rol', 'estado')
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }
}
