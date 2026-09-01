<?php

namespace App\Console\Commands;

use App\Mail\ReservaRecordatorio;
use App\Service\SvcReserva;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarRecordatoriosReservas extends Command
{
    protected $signature = 'reservas:enviar-recordatorios';

    protected $description = 'Envía correos de recordatorio a clientes con reservas para mañana';

    public function handle(): int
    {
        $manana = now()->addDay()->format('Y-m-d');

        $svcReserva = new SvcReserva;
        $reservas = $svcReserva->listarParaRecordatorio($manana);

        $encolados = 0;
        $omitidos = 0;

        foreach ($reservas as $reserva) {
            // Sin correo no hay a quién avisar: se omite en silencio.
            if (empty($reserva['email_cliente'])) {
                $omitidos++;

                continue;
            }

            // Un fallo puntual no debe detener el resto de recordatorios.
            try {
                Mail::to($reserva['email_cliente'])->queue(
                    new ReservaRecordatorio($reserva, $reserva['nombre_negocio'] ?? '')
                );

                $encolados++;
            } catch (\Exception $e) {
                Log::channel('database')->info($e);
                $this->warn('No se pudo encolar el recordatorio de la reserva '.$reserva['id_reserva']);
            }
        }

        $this->info('Reservas para el '.$manana.': '.count($reservas));
        $this->info('Recordatorios encolados: '.$encolados);

        if ($omitidos > 0) {
            $this->line('Omitidos por falta de correo del cliente: '.$omitidos);
        }

        return self::SUCCESS;
    }
}
