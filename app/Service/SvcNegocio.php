<?php

namespace App\Service;

use App\Models\Negocio;
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
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }
}
