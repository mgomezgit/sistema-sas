<?php

namespace App\Service;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SvcUsuario
{
    public function crear($info)
    {
        try {
            $info['clave'] = Hash::make($info['clave']);

            $usuario = Usuario::create($info);

            return $usuario->id_usuario;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    public function editar($id, $info, $tenantId = null): bool
    {
        try {
            if (array_key_exists('clave', $info)) {
                if (! empty($info['clave'])) {
                    $info['clave'] = Hash::make($info['clave']);
                } else {
                    unset($info['clave']);
                }
            }

            $query = Usuario::where('id_usuario', $id);

            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }

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

    public function eliminar($id, $tenantId = null): bool
    {
        try {
            $query = Usuario::where('id_usuario', $id);

            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }

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

    public function listar($tenantId = null)
    {
        try {
            $query = Usuario::from('usuarios as u')
                ->join('roles as r', 'r.id_rol', '=', 'u.id_rol')
                ->leftJoin('negocios as n', 'n.id_negocio', '=', 'u.tenant_id')
                ->select(
                    'u.id_usuario',
                    'u.usuario',
                    'u.nombre',
                    'u.email',
                    'u.id_rol',
                    'u.tenant_id',
                    'u.estado as estado',
                    'r.nombre_rol as nombre_rol',
                    'n.nombre_negocio as nombre_negocio'
                );

            if ($tenantId !== null) {
                $query->where('u.tenant_id', $tenantId);
            }

            return $query->get()->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function listarById($id, $tenantId = null)
    {
        try {
            $query = Usuario::from('usuarios as u')
                ->join('roles as r', 'r.id_rol', '=', 'u.id_rol')
                ->leftJoin('negocios as n', 'n.id_negocio', '=', 'u.tenant_id')
                ->select(
                    'u.id_usuario',
                    'u.usuario',
                    'u.nombre',
                    'u.email',
                    'u.id_rol',
                    'u.tenant_id',
                    'u.estado as estado',
                    'r.nombre_rol as nombre_rol',
                    'n.nombre_negocio as nombre_negocio'
                )
                ->where('u.id_usuario', $id);

            if ($tenantId !== null) {
                $query->where('u.tenant_id', $tenantId);
            }

            return $query->get()->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function getUsuarioByEmail($email)
    {
        try {
            $usuario = Usuario::select('id_usuario', 'usuario', 'nombre', 'email', 'clave', 'tenant_id', 'id_rol', 'estado')
                ->where('email', $email)
                ->where('estado', 1)
                ->first();

            return $usuario ? $usuario->toArray() : [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }
}
