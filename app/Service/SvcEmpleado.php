<?php

namespace App\Service;

use App\Models\Empleado;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SvcEmpleado
{
    public function crear($info)
    {
        try {
            $empleado = Empleado::create($info);

            return $empleado->id_empleado;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Le quita el acceso al sistema al usuario vinculado de un empleado que se
     * está dando de baja.
     *
     * Se llama SIEMPRE dentro de la transacción de quien desactiva, para que
     * valga la regla de todo o nada: nunca debe quedar un empleado inactivo con
     * su usuario todavía activo. Por eso lanza excepción en vez de devolver
     * false; quien la llama deja que reviente y la transacción se revierte.
     *
     * @param  int|null  $idUsuario  El usuario vinculado, si lo hay.
     */
    private function revocarAccesoVinculado($idEmpleado, $idUsuario, $tenantId): void
    {
        if ($idUsuario === null) {
            return;
        }

        $usuario = Usuario::where('id_usuario', $idUsuario)->first();

        // Referencia huérfana: el usuario ya no existe, así que no hay ningún
        // acceso que revocar y la regla se cumple igual. Se deja rastro porque
        // es un dato inconsistente que alguien debería mirar.
        if ($usuario === null) {
            Log::channel('database')->info(
                'Cascada de baja: el empleado '.$idEmpleado.' apunta al usuario '.$idUsuario.', que ya no existe. No hay acceso que revocar.'
            );

            return;
        }

        // Un empleado no debería jamás apuntar a un usuario de otro negocio. Si
        // pasa, no se toca ese usuario (sería pisar datos ajenos), pero tampoco
        // se puede dar por buena la baja: quedaría un acceso vivo que el admin
        // cree revocado. Se aborta todo y se revisa a mano.
        if ((int) $usuario->tenant_id !== (int) $tenantId) {
            Log::channel('database')->info(
                'Cascada de baja ABORTADA: el empleado '.$idEmpleado.' (negocio '.$tenantId.') apunta al usuario '
                .$idUsuario.', que pertenece al negocio '.$usuario->tenant_id.'.'
            );

            throw new \RuntimeException('EMP-CASCADA-TENANT');
        }

        Usuario::where('id_usuario', $idUsuario)
            ->where('tenant_id', $tenantId)
            ->update(['estado' => 0]);

        // Queda registrado que la baja del usuario fue automática y no una
        // acción directa sobre el módulo de Usuarios.
        Log::channel('database')->info(
            'Cascada de baja: al desactivar el empleado '.$idEmpleado.' se desactivó automáticamente su usuario '
            .$idUsuario.' (negocio '.$tenantId.').'
        );
    }

    /**
     * Desactivar a un empleado le quita también el acceso al sistema; reactivarlo
     * NO se lo devuelve.
     *
     * La asimetría es deliberada: quitar el acceso al dar de baja es lo que
     * espera cualquier administrador, pero devolverlo solo, sin que nadie lo
     * pida, sería una sorpresa peligrosa. Volver a habilitar la cuenta es una
     * acción explícita y aparte, desde el módulo de Usuarios.
     *
     * Solo cuenta la TRANSICIÓN de activo a inactivo. Guardar un empleado que ya
     * estaba inactivo no vuelve a revocar nada: si el admin reactivó su usuario a
     * propósito, editarle el teléfono no debería deshacerlo por la espalda.
     */
    public function editar($id, $info, $tenantId): bool
    {
        try {
            $query = Empleado::where('id_empleado', $id)->where('tenant_id', $tenantId);

            // Si el registro no existe (o es de otro negocio) sí es un fallo real. En
            // cambio, guardar sin cambiar ningún valor afecta 0 filas y es un caso válido.
            $empleado = $query->first();

            if ($empleado === null) {
                return false;
            }

            $seEstaDesactivando = array_key_exists('estado', $info)
                && (int) $info['estado'] === 0
                && (int) $empleado->estado === 1;

            DB::transaction(function () use ($query, $info, $id, $empleado, $tenantId, $seEstaDesactivando) {
                $query->update($info);

                if ($seEstaDesactivando) {
                    $this->revocarAccesoVinculado($id, $empleado->id_usuario, $tenantId);
                }
            });

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Baja lógica desde el botón de la papelera. Es el otro camino que deja a un
     * empleado inactivo, así que arrastra la misma cascada que editar(): de lo
     * contrario sería una puerta trasera para dejar el acceso vivo.
     */
    public function eliminar($id, $tenantId): bool
    {
        try {
            $query = Empleado::where('id_empleado', $id)->where('tenant_id', $tenantId);

            $empleado = $query->first();

            if ($empleado === null) {
                return false;
            }

            $seEstaDesactivando = (int) $empleado->estado === 1;

            DB::transaction(function () use ($query, $id, $empleado, $tenantId, $seEstaDesactivando) {
                $query->update(['estado' => 0]);

                if ($seEstaDesactivando) {
                    $this->revocarAccesoVinculado($id, $empleado->id_usuario, $tenantId);
                }
            });

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Por defecto solo trae los empleados activos: uno dado de baja no debe
     * seguir apareciendo en el listado normal. $incluirInactivos es la puerta
     * para verlos igual, por ejemplo desde un filtro "Mostrar inactivos" en la
     * tabla, o para poder abrir uno en modo edición y reactivarlo.
     *
     * Este filtro es solo de presentación: no tiene nada que ver con el acceso
     * al sistema. De revocarlo se encarga la cascada de editar()/eliminar().
     */
    public function listar($tenantId, $incluirInactivos = false)
    {
        try {
            $query = Empleado::select(
                'id_empleado',
                'nombre',
                'telefono',
                'email',
                'cargo',
                'porcentaje_comision',
                'id_usuario',
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
            return Empleado::select(
                'id_empleado',
                'nombre',
                'telefono',
                'email',
                'cargo',
                'porcentaje_comision',
                'id_usuario',
                'estado'
            )
                ->where('id_empleado', $id)
                ->where('tenant_id', $tenantId)
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function listarActivos($tenantId)
    {
        try {
            return Empleado::select('id_empleado', 'nombre', 'cargo')
                ->where('tenant_id', $tenantId)
                ->where('estado', 1)
                ->get()
                ->toArray() ?? [];
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    public function vincularUsuario($idEmpleado, $idUsuario, $tenantId): bool
    {
        try {
            $query = Empleado::where('id_empleado', $idEmpleado)->where('tenant_id', $tenantId);

            if (! $query->exists()) {
                return false;
            }

            $query->update(['id_usuario' => $idUsuario]);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }
}
