<?php

namespace App\Console\Commands;

use App\Service\SvcProducto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reparte un SKU a los productos que quedaron sin código.
 *
 * El SKU pasó a ser obligatorio después de que ya existieran productos, así que
 * los registrados antes se quedaron con la columna en NULL y no se pueden
 * editar hasta que tengan uno. Este comando los pone al día.
 *
 * Es de mantenimiento y se corre a mano una sola vez: NO va en el Scheduler.
 * Aun así es seguro repetirlo, porque solo toca los que tienen el SKU en NULL;
 * a los que ya tienen uno ni los mira.
 */
class AsignarSkuFaltantes extends Command
{
    protected $signature = 'productos:asignar-sku-faltantes';

    protected $description = 'Asigna un SKU a los productos que quedaron sin código, en todos los negocios';

    public function handle(): int
    {
        $svcProducto = new SvcProducto;
        $pendientes = $svcProducto->listarSinSkuTodosLosNegocios();

        if (empty($pendientes)) {
            $this->info('No hay productos sin SKU: no hay nada que hacer.');

            return self::SUCCESS;
        }

        $this->info('Productos sin SKU encontrados: '.count($pendientes));
        $this->newLine();

        $filas = [];
        $asignados = 0;
        $fallidos = 0;

        foreach ($pendientes as $producto) {
            // El código se genera contra el negocio DUEÑO del producto, no contra
            // uno fijo: así dos negocios pueden acabar con el mismo SKU, cada uno
            // el suyo, que es justo lo que permite el índice único por negocio.
            $tenantId = $producto['tenant_id'];

            try {
                // Una transacción POR PRODUCTO, no una general. Cada asignación es
                // independiente: no hay ninguna regla que ate a un producto con
                // otro, así que si el número 40 falla no tiene sentido deshacer los
                // 39 que ya quedaron bien. Además el comando se puede repetir y
                // retoma justo donde se quedó, porque solo busca los que están en
                // NULL. Con una transacción general, un fallo tardío obligaría a
                // rehacer todo el trabajo bueno.
                $sku = DB::transaction(function () use ($svcProducto, $producto, $tenantId) {
                    $sugerido = $svcProducto->generarSkuSugerido($tenantId, $producto['nombre']);

                    if ($sugerido === false) {
                        throw new \RuntimeException('no se encontró un código libre');
                    }

                    // Se guarda por el Service, que filtra por negocio igual que
                    // cualquier otra edición.
                    $guardado = $svcProducto->editar($producto['id_producto'], ['sku' => $sugerido], $tenantId);

                    if (! $guardado) {
                        throw new \RuntimeException('no se pudo guardar el producto');
                    }

                    return $sugerido;
                });

                $filas[] = [$producto['id_producto'], $tenantId, $producto['nombre'], $sku];
                $asignados++;
            } catch (\Exception $e) {
                Log::channel('database')->info($e);

                $filas[] = [$producto['id_producto'], $tenantId, $producto['nombre'], 'ERROR: '.$e->getMessage()];
                $fallidos++;
            }
        }

        $this->table(['ID', 'Negocio', 'Producto', 'SKU asignado'], $filas);

        $this->info('Productos actualizados: '.$asignados);

        if ($fallidos > 0) {
            $this->warn('Productos que no se pudieron actualizar: '.$fallidos);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
