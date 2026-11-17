<?php

namespace App\Console\Commands;

use App\Mail\ReservaRecordatorio;
use App\Service\SvcModulo;
use App\Service\SvcNotificacionReserva;
use App\Service\SvcPaginaPublica;
use App\Service\SvcReserva;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarRecordatoriosReservas extends Command
{
    protected $signature = 'reservas:enviar-recordatorios';

    protected $description = 'Envía correos de recordatorio a clientes con reservas para mañana';

    public function handle(SvcModulo $svcModulo): int
    {
        $manana = now()->addDay()->format('Y-m-d');

        $svcReserva = new SvcReserva;
        $reservas = $svcReserva->listarParaRecordatorio($manana);

        $encolados = 0;
        $omitidos = 0;
        $sinModulo = 0;

        // Módulo "correos" por negocio, comprobado UNA vez por negocio y con la
        // vigencia en tiempo real (estaActivo). Proceso de sistema: recorre
        // todos los negocios, pero cada reserva se decide con SU tenant_id.
        $correosActivos = [];

        // Página completa por negocio (módulo "pagina_publica"): decide si el
        // correo lleva el botón a reservar/{slug}. También una vez por negocio.
        $paginaCompleta = [];

        foreach ($reservas as $reserva) {
            $tenantId = (int) $reserva['tenant_id'];
            $correosActivos[$tenantId] ??= $svcModulo->estaActivo($tenantId, SvcNotificacionReserva::CLAVE_MODULO);

            // Sin el módulo, el negocio no manda correos a sus clientas: se
            // omite ANTES de encolar nada.
            if (! $correosActivos[$tenantId]) {
                $sinModulo++;

                continue;
            }

            // Sin correo no hay a quién avisar: se omite en silencio.
            if (empty($reserva['email_cliente'])) {
                $omitidos++;

                continue;
            }

            // Un fallo puntual no debe detener el resto de recordatorios.
            try {
                Mail::to($reserva['email_cliente'])->queue(
                    new ReservaRecordatorio($reserva, [
                        'nombre_negocio' => $reserva['nombre_negocio'] ?? '',
                        'color_acento' => $reserva['color_acento'] ?? null,
                        'slug' => $reserva['slug'] ?? null,
                        'politica_cancelacion' => $reserva['politica_cancelacion'] ?? null,
                        'pagina_completa' => $paginaCompleta[$tenantId] ??= $svcModulo->estaActivo($tenantId, SvcPaginaPublica::CLAVE_MODULO),
                    ])
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

        if ($sinModulo > 0) {
            $this->line('Omitidos por negocio sin el modulo de correos: '.$sinModulo);
        }

        return self::SUCCESS;
    }
}
