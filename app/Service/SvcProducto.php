<?php

namespace App\Service;

use App\Models\Producto;
use App\Models\Rol;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SvcProducto
{
    /** Letras del nombre que forman el prefijo del SKU sugerido. */
    const LARGO_PREFIJO_SKU = 4;

    /** Menos letras que esto no identifican nada: se usa el prefijo genérico. */
    const LARGO_MINIMO_PREFIJO_SKU = 3;

    const PREFIJO_SKU_GENERICO = 'PROD';

    /** Tope del secuencial, que es de tres dígitos. */
    const MAXIMO_SECUENCIAL_SKU = 999;

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
     * ya tocó el mínimo. Es solo una etiqueta: no decide quién entra a la
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

    /**
     * El mínimo es el punto de reposición, no el punto de quiebre: tener
     * exactamente el mínimo ya cuenta como stock bajo y entra a las alertas.
     */
    public function listarStockBajo($tenantId)
    {
        try {
            $productos = Producto::select('id_producto', 'nombre', 'sku', 'cantidad_actual', 'cantidad_minima')
                ->where('tenant_id', $tenantId)
                ->where('estado', 1)
                ->whereColumn('cantidad_actual', '<=', 'cantidad_minima')
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
     * Productos sin SKU de TODOS los negocios, para el comando de
     * mantenimiento que reparte los códigos que faltan.
     *
     * Trae el tenant_id de cada uno a propósito: el código se genera contra el
     * inventario del negocio dueño del producto, nunca contra uno fijo.
     *
     * Incluye los inactivos: también ocupan su lugar en el índice único y en su
     * día pueden reactivarse.
     */
    public function listarSinSkuTodosLosNegocios()
    {
        try {
            return Producto::select('id_producto', 'tenant_id', 'nombre', 'estado')
                ->whereNull('sku')
                ->orderBy('tenant_id')
                ->orderBy('id_producto')
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Propone un SKU libre para un producto, a partir de su nombre.
     *
     * Del nombre sale un prefijo de 4 letras sin tildes ni espacios
     * ("Depilación de piernas" → "DEPI") y se le pega un secuencial de tres
     * dígitos, probando desde 001 hasta encontrar uno que ese negocio no use.
     * La búsqueda va por buscarPorSku(), que ya filtra por tenant: por eso dos
     * negocios pueden recibir el mismo "DEPI-001", cada uno el suyo.
     *
     * Es solo una sugerencia: no reserva nada. Entre pedirla y guardar, otro
     * podría tomarla; de eso se encarga la validación de unicidad al guardar.
     *
     * @return string|false El SKU sugerido, o false si no se pudo.
     */
    public function generarSkuSugerido($tenantId, $nombre)
    {
        try {
            $prefijo = $this->prefijoDesdeNombre($nombre);

            for ($secuencial = 1; $secuencial <= self::MAXIMO_SECUENCIAL_SKU; $secuencial++) {
                $candidato = $prefijo.'-'.str_pad((string) $secuencial, 3, '0', STR_PAD_LEFT);

                if ($this->buscarPorSku($candidato, $tenantId) === null) {
                    return $candidato;
                }
            }

            // Agotados los 999 de ese prefijo, quien registra tendrá que escribir
            // uno a mano: inventar un formato distinto aquí sería peor.
            return false;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Prefijo del SKU a partir del nombre: sin tildes, sin espacios, sin
     * símbolos ni números, en mayúsculas y recortado.
     *
     * Un nombre vacío o demasiado corto para reconocerse ("Té", "3D") cae al
     * prefijo genérico, que es más útil que un código de una sola letra.
     */
    private function prefijoDesdeNombre($nombre): string
    {
        $soloLetras = preg_replace('/[^A-Za-z]/', '', Str::ascii((string) $nombre));
        $prefijo = strtoupper(substr($soloLetras, 0, self::LARGO_PREFIJO_SKU));

        return strlen($prefijo) >= self::LARGO_MINIMO_PREFIJO_SKU
            ? $prefijo
            : self::PREFIJO_SKU_GENERICO;
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
                ->whereColumn('productos.cantidad_actual', '<=', 'productos.cantidad_minima')
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
