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
                'sku',
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
                'sku',
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

    /**
     * Etiqueta la urgencia de cada producto ya filtrado como falto de stock:
     * "agotado" si no queda ninguna unidad, "bajo" si todavía queda algo pero
     * por debajo del mínimo. Es solo una etiqueta: no decide quién entra a la
     * lista, eso ya lo resolvió la consulta.
     *
     * Se calcula en PHP y no con un CASE en SQL para que dé exactamente igual en
     * MySQL (producción) y en SQLite (pruebas).
     */
    private function etiquetarUrgencia(array $productos): array
    {
        return array_map(function ($producto) {
            $producto['urgencia'] = (int) $producto['cantidad_actual'] === 0 ? 'agotado' : 'bajo';

            return $producto;
        }, $productos);
    }

    public function listarStockBajo($tenantId)
    {
        try {
            $productos = Producto::select('id_producto', 'nombre', 'sku', 'cantidad_actual', 'cantidad_minima')
                ->where('tenant_id', $tenantId)
                ->where('estado', 1)
                ->whereColumn('cantidad_actual', '<', 'cantidad_minima')
                ->get()
                ->toArray() ?? [];

            return $this->etiquetarUrgencia($productos);
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Busca un producto por su SKU dentro del negocio indicado.
     *
     * @return array|null El registro, o null si ese negocio no tiene ese SKU.
     */
    public function buscarPorSku($sku, $tenantId): ?array
    {
        try {
            if (trim((string) $sku) === '') {
                return null;
            }

            $producto = Producto::select(
                'id_producto',
                'tenant_id',
                'nombre',
                'sku',
                'descripcion',
                'cantidad_actual',
                'cantidad_minima',
                'estado'
            )
                ->where('tenant_id', $tenantId)
                ->where('sku', $sku)
                ->first();

            return $producto ? $producto->toArray() : null;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return null;
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

            $productos = Producto::select(
                'productos.id_producto',
                'productos.tenant_id',
                'productos.nombre',
                'productos.sku',
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

            return $this->etiquetarUrgencia($productos);
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
