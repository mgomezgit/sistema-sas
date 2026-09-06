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

        $porNegocio = [];

        foreach ($productos as $producto) {
            $porNegocio[$producto['tenant_id']]['nombre_negocio'] = $producto['nombre_negocio'];
            $porNegocio[$producto['tenant_id']]['email_admin'] = $producto['email_admin'];
            $porNegocio[$producto['tenant_id']]['productos'][] = $producto;
        }

        $encolados = 0;

        foreach ($porNegocio as $datosNegocio) {
            // Sin email de admin no hay a quién avisar: se omite en silencio.
            if (empty($datosNegocio['email_admin'])) {
                continue;
            }

            // Un fallo puntual no debe detener el resumen de los demás negocios.
            try {
                Mail::to($datosNegocio['email_admin'])->queue(
                    new ResumenStockBajo($datosNegocio['nombre_negocio'], $datosNegocio['productos'])
                );

                $encolados++;
            } catch (\Exception $e) {
                Log::channel('database')->info($e);
                $this->warn('No se pudo encolar el resumen del negocio '.$datosNegocio['nombre_negocio']);
            }
        }

        $this->info('Negocios con stock bajo: '.count($porNegocio));
        $this->info('Correos encolados: '.$encolados);

        return self::SUCCESS;
    }
}
