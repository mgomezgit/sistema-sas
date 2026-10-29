<?php

namespace App\Console\Commands;

use App\Mail\ModuloPorVencer;
use App\Mail\ModuloVencido;
use App\Models\NegocioModulo;
use App\Service\SvcModulo;
use App\Service\SvcProducto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Proceso diario de vencimientos de módulos de pago.
 *
 * (a) Apaga los módulos activos cuya vigencia ya pasó (vigente_hasta < hoy),
 *     marca vencido_avisado_en y avisa por correo.
 * (b) Envía el aviso de 7 días (vigente_hasta = hoy + 7) y el de 1 día
 *     (hoy + 1) una sola vez cada uno, marcando su bandera.
 *
 * Es un PROCESO DE SISTEMA (sin sesión de usuario): por eso trabaja con el
 * tenant de cada fila, no con session(). Idempotente: las banderas y el estado
 * evitan repetir el apagado o duplicar correos si se corre dos veces el día.
 *
 * El acceso NO depende de este comando: SvcModulo::estaActivo ya corta por
 * vigencia en tiempo real. Esto solo limpia el estado y avisa.
 *
 * Negocios suspendidos (negocios.estado = 0): sus módulos SÍ se apagan, pero no
 * reciben correos. Los correos van SOLO a los administradores activos de ESE
 * negocio (se reutiliza el mismo método que el resumen de stock).
 */
class ProcesarVencimientosModulos extends Command
{
    protected $signature = 'modulos:procesar-vencimientos';

    protected $description = 'Apaga los modulos vencidos y envia los avisos de vencimiento a los administradores del negocio';

    public function handle(SvcModulo $svcModulo, SvcProducto $svcProducto): int
    {
        $apagados = 0;
        $avisos = 0;

        // (a) Apagar lo vencido.
        foreach ($svcModulo->modulosParaApagar() as $fila) {
            DB::transaction(function () use ($fila) {
                // El WHERE lleva tenant_id además del id: nunca se toca una fila
                // de otro negocio por un id adivinado.
                NegocioModulo::where('id_negocio_modulo', $fila['id_negocio_modulo'])
                    ->where('tenant_id', $fila['tenant_id'])
                    ->update([
                        'activo' => false,
                        'fecha_desactivacion' => now(),
                        'vencido_avisado_en' => now(),
                    ]);

                Log::channel('database')->info(sprintf(
                    'VENCIMIENTO: sistema APAGO el modulo "%s" del negocio %d (vigente_hasta %s) el %s',
                    $fila['clave_modulo'],
                    $fila['tenant_id'],
                    $fila['vigente_hasta'],
                    now()->format('Y-m-d H:i:s')
                ));
            });

            $apagados++;

            if ((int) $fila['negocio_estado'] === 1
                && $this->enviar($svcProducto, $fila, new ModuloVencido(
                    (string) $fila['nombre_negocio'],
                    (string) $fila['nombre_modulo'],
                    (string) $fila['vigente_hasta'],
                    $fila['color_acento'] ?? null
                ))) {
                $avisos++;
            }
        }

        // (b) Avisos de 7 y 1 días.
        foreach ([7, 1] as $dias) {
            $bandera = $dias === 7 ? 'aviso_7_enviado_en' : 'aviso_1_enviado_en';

            foreach ($svcModulo->modulosEnAviso($dias) as $fila) {
                DB::transaction(function () use ($fila, $bandera) {
                    NegocioModulo::where('id_negocio_modulo', $fila['id_negocio_modulo'])
                        ->where('tenant_id', $fila['tenant_id'])
                        ->update([$bandera => now()]);
                });

                if ((int) $fila['negocio_estado'] === 1
                    && $this->enviar($svcProducto, $fila, new ModuloPorVencer(
                        (string) $fila['nombre_negocio'],
                        (string) $fila['nombre_modulo'],
                        (string) $fila['vigente_hasta'],
                        $dias,
                        $fila['color_acento'] ?? null
                    ))) {
                    $avisos++;
                }
            }
        }

        $this->info('Modulos apagados por vencimiento: '.$apagados);
        $this->info('Avisos enviados: '.$avisos);

        return self::SUCCESS;
    }

    /**
     * Encola el correo a los administradores activos del negocio. Devuelve true
     * si había destinatarios. Un fallo al encolar no detiene el proceso.
     */
    private function enviar(SvcProducto $svcProducto, array $fila, $mailable): bool
    {
        try {
            $emails = $svcProducto->listarEmailsAdminsActivosPorNegocio([$fila['tenant_id']])[$fila['tenant_id']] ?? [];

            if (empty($emails)) {
                return false;
            }

            Mail::to($emails)->queue($mailable);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }
}
