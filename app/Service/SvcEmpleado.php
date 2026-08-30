<?php

namespace App\Service;

use App\Models\Empleado;
use Illuminate\Support\Facades\Log;

class SvcEmpleado
{
    public function crear($info)
    {
        try {
            $empleado = Empleado::create($info);

            return $empleado->id_empleado;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function editar($id, $info, $tenantId): bool
    {
        try {
            $query = Empleado::where('id_empleado', $id)->where('tenant_id', $tenantId);

            // Si el registro no existe (o es de otro negocio) sí es un fallo real. En
            // cambio, guardar sin cambiar ningún valor afecta 0 filas y es un caso válido.
            if (! $query->exists()) {
                return false;
            }

            $query->update($info);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function eliminar($id, $tenantId): bool
    {
        try {
            $query = Empleado::where('id_empleado', $id)->where('tenant_id', $tenantId);

            if (! $query->exists()) {
                return false;
            }

            $query->update(['estado' => 0]);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function listar($tenantId)
    {
        try {
            return Empleado::select(
                'id_empleado',
                'nombre',
                'telefono',
                'email',
                'cargo',
                'porcentaje_comision',
                'id_usuario',
                'estado'
            )
                ->where('tenant_id', $tenantId)
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function listarById($id, $tenantId)
    {
        try {
            return Empleado::select(
                'id_empleado',
                'nombre',
                'telefono',
                'email',
                'cargo',
                'porcentaje_comision',
                'id_usuario',
                'estado'
            )
                ->where('id_empleado', $id)
                ->where('tenant_id', $tenantId)
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function listarActivos($tenantId)
    {
        try {
            return Empleado::select('id_empleado', 'nombre', 'cargo')
                ->where('tenant_id', $tenantId)
                ->where('estado', 1)
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function vincularUsuario($idEmpleado, $idUsuario, $tenantId): bool
    {
        try {
            $query = Empleado::where('id_empleado', $idEmpleado)->where('tenant_id', $tenantId);

            if (! $query->exists()) {
                return false;
            }

            $query->update(['id_usuario' => $idUsuario]);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }
}
