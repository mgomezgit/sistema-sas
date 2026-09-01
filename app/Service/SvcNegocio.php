<?php

namespace App\Service;

use App\Models\Empleado;
use App\Models\Negocio;
use App\Models\RecursoReservable;
use App\Models\Reserva;
use Illuminate\Support\Facades\Log;

class SvcNegocio
{
    public function crear($info)
    {
        try {
            $negocio = Negocio::create($info);

            return $negocio->id_negocio;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function listar()
    {
        try {
            return Negocio::select('id_negocio', 'nombre_negocio', 'rubro', 'telefono_contacto', 'modo_tema', 'color_acento', 'estado')
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function obtenerConfiguracion($tenantId)
    {
        try {
            $negocio = Negocio::select(
                'id_negocio',
                'nombre_negocio',
                'telefono_contacto',
                'dias_atencion',
                'hora_apertura',
                'hora_cierre'
            )
                ->where('id_negocio', $tenantId)
                ->first();

            return $negocio ? $negocio->toArray() : [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function actualizarConfiguracion($tenantId, $info): bool
    {
        try {
            if (empty($info['nombre_negocio'])) {
                return false;
            }

            $query = Negocio::where('id_negocio', $tenantId);

            if (! $query->exists()) {
                return false;
            }

            $query->update([
                'nombre_negocio' => $info['nombre_negocio'],
                'telefono_contacto' => $info['telefono_contacto'] ?? null,
                'dias_atencion' => $info['dias_atencion'] ?? null,
                'hora_apertura' => $info['hora_apertura'] ?? null,
                'hora_cierre' => $info['hora_cierre'] ?? null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Horario de atención, usado por el calendario de reservas para calcular
     * las franjas disponibles.
     */
    public function obtenerHorario($tenantId)
    {
        try {
            $negocio = Negocio::select('dias_atencion', 'hora_apertura', 'hora_cierre')
                ->where('id_negocio', $tenantId)
                ->first();

            return $negocio ? $negocio->toArray() : [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Progreso del onboarding: cada paso se deduce del estado real del negocio,
     * no de banderas que haya que ir marcando a mano.
     */
    public function obtenerProgresoOnboarding($tenantId)
    {
        try {
            $negocio = Negocio::select('dias_atencion', 'tema_personalizado', 'tour_completado')
                ->where('id_negocio', $tenantId)
                ->first();

            if (! $negocio) {
                return ['tour_completado' => true, 'pasos' => []];
            }

            return [
                'tour_completado' => (bool) $negocio->tour_completado,
                'pasos' => [
                    [
                        'id' => 'personalizar',
                        'completado' => (bool) $negocio->tema_personalizado,
                    ],
                    [
                        'id' => 'horario',
                        'completado' => ! empty($negocio->dias_atencion),
                    ],
                    [
                        'id' => 'recurso',
                        'completado' => RecursoReservable::where('tenant_id', $tenantId)->where('estado', 1)->exists(),
                    ],
                    [
                        'id' => 'empleado',
                        'completado' => Empleado::where('tenant_id', $tenantId)->where('estado', 1)->exists(),
                    ],
                    [
                        'id' => 'reserva',
                        'completado' => Reserva::where('tenant_id', $tenantId)->where('estado', 1)->exists(),
                    ],
                    [
                        'id' => 'finalizar',
                        'completado' => (bool) $negocio->tour_completado,
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            // Ante un fallo se responde como "ya terminado" para que el widget
            // simplemente no aparezca, en vez de romper la pantalla.
            return ['tour_completado' => true, 'pasos' => []];
        }
    }

    public function completarOnboarding($tenantId): bool
    {
        try {
            $query = Negocio::where('id_negocio', $tenantId);

            if (! $query->exists()) {
                return false;
            }

            $query->update(['tour_completado' => true]);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    // Valores admitidos para el tema del backoffice.
    const MODOS_VALIDOS = ['claro', 'oscuro'];

    const ACENTOS_VALIDOS = ['oro_rosa', 'dorado', 'amarillo', 'naranja', 'rojo', 'azul', 'verde'];

    public function actualizarTema($tenantId, $modoTema, $colorAcento): bool
    {
        try {
            // Se validan aquí también (no solo en el Controller) para que ningún
            // llamador pueda guardar un valor que el CSS no sabe representar.
            if (! in_array($modoTema, self::MODOS_VALIDOS, true)) {
                return false;
            }

            if (! in_array($colorAcento, self::ACENTOS_VALIDOS, true)) {
                return false;
            }

            $query = Negocio::where('id_negocio', $tenantId);

            if (! $query->exists()) {
                return false;
            }

            $query->update([
                'modo_tema' => $modoTema,
                'color_acento' => $colorAcento,
                // Guardar el tema cuenta como paso completado del onboarding.
                'tema_personalizado' => true,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }
}
