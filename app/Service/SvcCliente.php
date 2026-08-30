<?php

namespace App\Service;

use App\Models\Cliente;
use Illuminate\Support\Facades\Log;

class SvcCliente
{
    public function crear($info)
    {
        try {
            $cliente = Cliente::create($info);

            return $cliente->id_cliente;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function editar($id, $info, $tenantId): bool
    {
        try {
            $query = Cliente::where('id_cliente', $id)->where('tenant_id', $tenantId);

            // Si el registro no existe (o es de otro negocio) sí es un fallo real. En
            // cambio, guardar sin cambiar ningún valor afecta 0 filas y es un caso válido.
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
            $query = Cliente::where('id_cliente', $id)->where('tenant_id', $tenantId);

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
            return Cliente::select(
                'id_cliente',
                'nombre',
                'telefono',
                'email',
                'documento_identidad',
                'fecha_nacimiento',
                'notas',
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
            return Cliente::select(
                'id_cliente',
                'nombre',
                'telefono',
                'email',
                'documento_identidad',
                'fecha_nacimiento',
                'notas',
                'estado'
            )
                ->where('id_cliente', $id)
                ->where('tenant_id', $tenantId)
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function buscarPorTelefonoOEmail($valor, $tenantId)
    {
        try {
            return Cliente::select('id_cliente', 'nombre', 'telefono', 'email')
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($valor) {
                    $query->where('telefono', $valor)
                        ->orWhere('email', $valor);
                })
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }
}
