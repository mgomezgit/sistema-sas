<?php

namespace App\Service;

use App\Models\RecursoReservable;
use Illuminate\Support\Facades\Log;

class SvcRecursoReservable
{
    public function crear($info)
    {
        try {
            $recurso = RecursoReservable::create($info);

            return $recurso->id_recurso;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function editar($id, $info, $tenantId): bool
    {
        try {
            $query = RecursoReservable::where('id_recurso', $id)->where('tenant_id', $tenantId);

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
            $query = RecursoReservable::where('id_recurso', $id)->where('tenant_id', $tenantId);

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
            return RecursoReservable::select(
                'id_recurso',
                'categoria',
                'nombre',
                'descripcion',
                'duracion_minutos',
                'precio',
                'capacidad',
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
            return RecursoReservable::select(
                'id_recurso',
                'categoria',
                'nombre',
                'descripcion',
                'duracion_minutos',
                'precio',
                'capacidad',
                'estado'
            )
                ->where('id_recurso', $id)
                ->where('tenant_id', $tenantId)
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function listarActivosPorCategoria($tenantId)
    {
        try {
            return RecursoReservable::select('id_recurso', 'categoria', 'nombre', 'duracion_minutos', 'precio')
                ->where('tenant_id', $tenantId)
                ->where('estado', 1)
                ->orderBy('categoria')
                ->orderBy('nombre')
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }
}
