<?php

namespace App\Service;

use App\Models\RecursoReservable;
use Illuminate\Support\Facades\Log;

class SvcRecursoReservable
{
    /** Largo máximo de la descripción PÚBLICA del servicio. */
    const LARGO_MAXIMO_DESCRIPCION_PUBLICA = 200;

    /**
     * Limpia la descripción pública: sin espacios de más (los repetidos se
     * colapsan), sin caracteres de control ni invisibles, máximo 200 (si se
     * pasa se rechaza, no se recorta) y vacía = null. Se guarda tal cual la
     * escribió el admin: el escape se hace al pintar, nunca al guardar.
     *
     * Devuelve ['ok' => bool, 'valor' => ?string].
     */
    public static function normalizarDescripcionPublica($texto): array
    {
        if ($texto === null) {
            return ['ok' => true, 'valor' => null];
        }

        if (! is_string($texto)) {
            return ['ok' => false, 'valor' => null];
        }

        $limpio = preg_replace('/\s+/u', ' ', $texto);

        if ($limpio === null) {
            return ['ok' => false, 'valor' => null];
        }

        $limpio = trim($limpio);

        if ($limpio === '') {
            return ['ok' => true, 'valor' => null];
        }

        if (mb_strlen($limpio) > self::LARGO_MAXIMO_DESCRIPCION_PUBLICA || preg_match('/[\p{Cc}\p{Cf}]/u', $limpio) === 1) {
            return ['ok' => false, 'valor' => null];
        }

        return ['ok' => true, 'valor' => $limpio];
    }

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

    /**
     * Por defecto solo trae los recursos activos: uno dado de baja no debe
     * seguir apareciendo en el listado normal. $incluirInactivos es la puerta
     * para verlos igual, por ejemplo desde un filtro "Mostrar inactivos" en la
     * tabla, o para poder abrir uno en modo edición y reactivarlo.
     */
    public function listar($tenantId, $incluirInactivos = false)
    {
        try {
            $query = RecursoReservable::select(
                'id_recurso',
                'categoria',
                'nombre',
                'descripcion',
                'descripcion_publica',
                'duracion_minutos',
                'precio',
                'capacidad',
                'estado'
            )
                ->where('tenant_id', $tenantId);

            if (! $incluirInactivos) {
                $query->where('estado', 1);
            }

            return $query
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
                'descripcion_publica',
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
