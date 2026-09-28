<?php

namespace App\Console\Commands;

use App\Mail\ResumenStockBajo;
use App\Service\SvcProducto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarResumenStockBajo extends Command
{
    protected $signature = 'productos:enviar-resumen-stock-bajo';

    protected $description = 'Envía a cada negocio un correo con sus productos en stock bajo';

    public function handle(): int
    {
        $svcProducto = new SvcProducto;
        $productos = $svcProducto->listarStockBajoTodosLosNegocios();

        // Un producto por fila, sin duplicados: listarStockBajoTodosLosNegocios()
        // ya no une con usuarios, así que agrupar por tenant_id aquí no repite
        // nada.
        $productosPorNegocio = [];

        foreach ($productos as $producto) {
            $productosPorNegocio[$producto['tenant_id']]['nombre_negocio'] = $producto['nombre_negocio'];
            $productosPorNegocio[$producto['tenant_id']]['productos'][] = $producto;
        }

        if (empty($productosPorNegocio)) {
            $this->info('Negocios con stock bajo: 0');
            $this->info('Correos encolados: 0');

            return self::SUCCESS;
        }

        // Una sola consulta para los admins activos de TODOS los negocios que
        // tienen stock bajo, en vez de una por negocio.
        $emailsPorNegocio = $svcProducto->listarEmailsAdminsActivosPorNegocio(array_keys($productosPorNegocio));

        $encolados = 0;

        foreach ($productosPorNegocio as $tenantId => $datosNegocio) {
            $emails = $emailsPorNegocio[$tenantId] ?? [];

            // Sin ningún admin activo no hay a quién avisar: se omite en
            // silencio, igual que antes cuando faltaba el email.
            if (empty($emails)) {
                continue;
            }

            // Cada admin activo recibe SU PROPIO correo, con la lista
            // completa (no repetida) de productos de su negocio.
            foreach ($emails as $email) {
                // Un fallo puntual no debe detener el resumen de los demás.
                try {
                    Mail::to($email)->queue(
                        new ResumenStockBajo($datosNegocio['nombre_negocio'], $datosNegocio['productos'])
                    );

                    $encolados++;
                } catch (\Exception $e) {
                    Log::channel('database')->info($e);
                    $this->warn('No se pudo encolar el resumen del negocio '.$datosNegocio['nombre_negocio'].' para '.$email);
                }
            }
        }

        $this->info('Negocios con stock bajo: '.count($productosPorNegocio));
        $this->info('Correos encolados: '.$encolados);

        return self::SUCCESS;
    }
}
