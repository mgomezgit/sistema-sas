<?php

namespace App\Service;

use App\Models\Cliente;
use Illuminate\Support\Facades\Log;

class SvcCliente
{
    /**
     * Total de clientes activos del negocio. Alimenta la tarjeta del dashboard.
     */
    public function contarActivos($tenantId)
    {
        try {
            return Cliente::where('tenant_id', $tenantId)
                ->where('estado', 1)
                ->count();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return 0;
        }
    }

    /**
     * Clientes activos que ya existían antes de que empezara el mes actual.
     * Comparado contra el total de hoy da la variación mensual del panel.
     */
    public function contarActivosMesAnterior($tenantId)
    {
        try {
            return Cliente::where('tenant_id', $tenantId)
                ->where('estado', 1)
                ->where('fecha_registro', '<', date('Y-m-01').' 00:00:00')
                ->count();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return 0;
        }
    }

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

    /**
     * Por defecto solo trae los clientes activos: uno dado de baja no debe
     * seguir apareciendo en el listado normal. $incluirInactivos es la puerta
     * para verlos igual, por ejemplo desde un filtro "Mostrar inactivos" en la
     * tabla, o para poder abrir uno en modo edición y reactivarlo.
     */
    public function listar($tenantId, $incluirInactivos = false)
    {
        try {
            $query = Cliente::select(
                'id_cliente',
                'nombre',
                'telefono',
                'email',
                'documento_identidad',
                'fecha_nacimiento',
                'notas',
                'estado'
            )
                ->where('tenant_id', $tenantId);

            if (! $incluirInactivos) {
                $query->where('estado', 1);
            }

            return $query
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

    /**
     * Busca un cliente por su teléfono dentro del negocio.
     *
     * Es lo que permite que alguien que ya vino antes no se duplique al pedir
     * cita desde la página pública: el teléfono es el dato que un cliente sí
     * da siempre, a diferencia del correo.
     *
     * La búsqueda NO filtra por estado a propósito: si el negocio dio de baja
     * a un cliente y esa misma persona vuelve a pedir cita, hay que reconocerla
     * y reutilizar su ficha, no crear una segunda con el mismo teléfono.
     *
     * @return array|null El cliente, o null si ese teléfono no está registrado.
     */
    public function buscarPorTelefono($telefono, $tenantId)
    {
        try {
            $cliente = Cliente::select('id_cliente', 'nombre', 'telefono', 'email', 'estado')
                ->where('tenant_id', $tenantId)
                ->where('telefono', $telefono)
                ->first();

            return $cliente ? $cliente->toArray() : null;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return null;
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
