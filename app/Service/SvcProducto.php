<?php

namespace App\Service;

use App\Models\Producto;
use App\Models\Rol;
use Illuminate\Support\Facades\Log;

class SvcProducto
{
    public function crear($info)
    {
        try {
            $producto = Producto::create($info);

            return $producto->id_producto;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function editar($id, $info, $tenantId): bool
    {
        try {
            $query = Producto::where('id_producto', $id)->where('tenant_id', $tenantId);

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
            $query = Producto::where('id_producto', $id)->where('tenant_id', $tenantId);

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
            return Producto::select(
                'id_producto',
                'nombre',
                'descripcion',
                'cantidad_actual',
                'cantidad_minima',
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
            return Producto::select(
                'id_producto',
                'nombre',
                'descripcion',
                'cantidad_actual',
                'cantidad_minima',
                'estado'
            )
                ->where('id_producto', $id)
                ->where('tenant_id', $tenantId)
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function listarStockBajo($tenantId)
    {
        try {
            return Producto::select('id_producto', 'nombre', 'cantidad_actual', 'cantidad_minima')
                ->where('tenant_id', $tenantId)
                ->where('estado', 1)
                ->whereColumn('cantidad_actual', '<', 'cantidad_minima')
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Para el scheduler, que corre a nivel de sistema y no de un tenant
     * específico: trae el stock bajo de TODOS los negocios, junto con el
     * nombre del negocio y el email de su admin, para poder enviar el
     * resumen de cada uno por correo.
     */
    public function listarStockBajoTodosLosNegocios()
    {
        try {
            $idRolAdmin = Rol::where('nombre_rol', 'admin')->value('id_rol');

            return Producto::select(
                'productos.id_producto',
                'productos.tenant_id',
                'productos.nombre',
                'productos.cantidad_actual',
                'productos.cantidad_minima',
                'negocios.nombre_negocio',
                'usuarios.email as email_admin'
            )
                ->join('negocios', 'negocios.id_negocio', '=', 'productos.tenant_id')
                ->leftJoin('usuarios', function ($join) use ($idRolAdmin) {
                    $join->on('usuarios.tenant_id', '=', 'productos.tenant_id')
                        ->where('usuarios.id_rol', $idRolAdmin);
                })
                ->where('productos.estado', 1)
                ->whereColumn('productos.cantidad_actual', '<', 'productos.cantidad_minima')
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Suma directamente en la base de datos (increment) en vez de leer y
     * reescribir el valor, para no perder unidades si dos ingresos de stock
     * del mismo producto llegan casi al mismo tiempo.
     */
    public function ingresarStock($id, $cantidadAgregar, $tenantId): bool
    {
        try {
            $query = Producto::where('id_producto', $id)->where('tenant_id', $tenantId);

            if (! $query->exists()) {
                return false;
            }

            $query->increment('cantidad_actual', $cantidadAgregar);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }
}
