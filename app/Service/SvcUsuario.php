<?php

namespace App\Service;

use App\Models\Empleado;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
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

    /**
     * Columnas del empleado vinculado para los listados.
     *
     * Van como subconsultas y no como un LEFT JOIN a propósito: no hay índice
     * único sobre empleados.id_usuario, así que un join podría duplicar filas
     * justo en el caso anómalo que la cascada detecta. La tabla solo necesita
     * saber si existe el vínculo, y con cuál, para avisar antes de dar de baja.
     */
    private function subconsultasDelEmpleadoVinculado(): array
    {
        return [
            'id_empleado_vinculado' => DB::table('empleados')
                ->select('id_empleado')
                ->whereColumn('empleados.id_usuario', 'u.id_usuario')
                ->limit(1),
            'nombre_empleado_vinculado' => DB::table('empleados')
                ->select('nombre')
                ->whereColumn('empleados.id_usuario', 'u.id_usuario')
                ->limit(1),
        ];
    }

    /**
     * Da de baja al empleado vinculado de un usuario que se está desactivando.
     *
     * Es la imagen en espejo de SvcEmpleado::revocarAccesoVinculado(), y se
     * llama SIEMPRE dentro de la transacción de quien desactiva, para que valga
     * la regla de todo o nada. Por eso lanza excepción en vez de devolver
     * false; quien la llama deja que reviente y la transacción se revierte.
     *
     * OJO CON LA RECURSIÓN. Ahora hay dos cascadas que se apuntan entre sí. El
     * corte está en que ninguna de las dos pasa por el método de servicio del
     * otro módulo: aquí se escribe DIRECTO sobre la columna estado de empleados
     * (igual que allá se escribe directo sobre la de usuarios). Llamar a
     * SvcEmpleado::editar() desde aquí volvería a disparar su cascada hacia
     * este mismo usuario, y esa de vuelta a esta. No se hace, y no debe hacerse.
     *
     * La consecuencia de esta dirección es más fuerte que la contraria: el
     * empleado no solo pierde el acceso al panel, deja de poder asignarse a
     * reservas nuevas, porque sale de listarActivos().
     *
     * @param  int  $tenantIdUsuario  El negocio del usuario ANTES de guardar,
     *                                que es donde tiene que vivir su empleado.
     */
    private function desactivarEmpleadoVinculado($idUsuario, $tenantIdUsuario): void
    {
        // La búsqueda es a propósito global, sin filtrar por negocio: filtrarla
        // escondería precisamente la anomalía que interesa detectar (un empleado
        // de otro negocio apuntando a este usuario), y la baja seguiría adelante
        // dejando vivo un empleado que el admin cree dado de baja.
        $empleados = Empleado::where('id_usuario', $idUsuario)->get();

        // Lo normal en la mayoría de cuentas: un administrador no es empleado de
        // nadie. No hay nada que arrastrar y la baja procede.
        if ($empleados->isEmpty()) {
            return;
        }

        // No hay índice único sobre empleados.id_usuario, así que esto es
        // posible. Se da de baja a todos, pero queda el rastro: dos empleados
        // compartiendo una misma cuenta es un dato que alguien debería mirar.
        if ($empleados->count() > 1) {
            Log::channel('database')->info(
                'Cascada de baja: el usuario '.$idUsuario.' está vinculado a '.$empleados->count()
                .' empleados a la vez ('.$empleados->pluck('id_empleado')->implode(', ').'), lo que no debería ocurrir.'
            );
        }

        foreach ($empleados as $empleado) {
            // Un empleado no debería jamás apuntar a un usuario de otro negocio.
            // Si pasa, no se toca esa fila ajena, pero tampoco se puede dar por
            // buena la baja: quedaría un empleado activo cuyo acceso el admin
            // cree haber cerrado. Se aborta todo y se revisa a mano.
            if ((int) $empleado->tenant_id !== (int) $tenantIdUsuario) {
                Log::channel('database')->info(
                    'Cascada de baja ABORTADA: el usuario '.$idUsuario.' (negocio '.$tenantIdUsuario
                    .') está vinculado al empleado '.$empleado->id_empleado.', que pertenece al negocio '
                    .$empleado->tenant_id.'.'
                );

                throw new \RuntimeException('USR-CASCADA-TENANT');
            }
        }

        foreach ($empleados as $empleado) {
            Empleado::where('id_empleado', $empleado->id_empleado)
                ->where('tenant_id', $tenantIdUsuario)
                ->update(['estado' => 0]);

            // Queda registrado que la baja del empleado fue automática y no una
            // acción directa sobre el módulo de Empleados.
            Log::channel('database')->info(
                'Cascada de baja: al desactivar el usuario '.$idUsuario.' se desactivó automáticamente su empleado '
                .$empleado->id_empleado.' (negocio '.$tenantIdUsuario.').'
            );
        }
    }

    /**
     * El negocio del usuario NUNCA se toma de $info.
     *
     * Cuando la edición la hace el administrador de un negocio, su tenant llega
     * como $tenantId (desde la sesión) y se impone sobre cualquier tenant_id que
     * venga en el cuerpo de la petición. Sin esto, un admin podía mover a un
     * usuario suyo al negocio de otro: al siguiente inicio de sesión, ese usuario
     * entraba al panel ajeno, porque el tenant de la sesión sale de esta columna.
     *
     * El formulario ya manda el campo bloqueado, pero eso vive en el navegador y
     * se salta armando la petición a mano; la garantía tiene que estar aquí.
     *
     * Con $tenantId null (el super admin, que no pertenece a ningún negocio) se
     * respeta la asignación que haga desde su panel, donde elegir el negocio es
     * parte del flujo.
     *
     * Desactivar la cuenta da de baja también al empleado vinculado, si lo hay;
     * reactivarla NO lo reactiva. La asimetría es la misma de la cascada
     * contraria y es deliberada: devolverle a alguien la asignabilidad a
     * reservas sin que nadie lo pida sería una sorpresa peligrosa. Volver a
     * darlo de alta es una acción explícita y aparte, desde Empleados.
     *
     * Solo cuenta la TRANSICIÓN de activo a inactivo: guardar un usuario que ya
     * estaba inactivo no vuelve a arrastrar nada, para no deshacer por la
     * espalda una reactivación que el admin hizo a propósito del otro lado.
     */
    public function editar($id, $info, $tenantId = null): bool
    {
        try {
            if ($tenantId !== null) {
                $info['tenant_id'] = $tenantId;
            }

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
            $usuario = $query->first();

            if ($usuario === null) {
                return false;
            }

            $seEstaDesactivando = array_key_exists('estado', $info)
                && (int) $info['estado'] === 0
                && (int) $usuario->estado === 1;

            // El negocio de referencia sale de la propia fila, no de la sesión:
            // así vale igual para el admin del negocio y para el super admin
            // (que edita con $tenantId null), y si en este mismo guardado se
            // está moviendo la cuenta de negocio, el empleado que hay que
            // arrastrar sigue siendo el del negocio de origen.
            $tenantIdUsuario = (int) $usuario->tenant_id;

            DB::transaction(function () use ($query, $info, $id, $tenantIdUsuario, $seEstaDesactivando) {
                $query->update($info);

                if ($seEstaDesactivando) {
                    $this->desactivarEmpleadoVinculado($id, $tenantIdUsuario);
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
     * usuario inactivo, así que arrastra la misma cascada que editar(): de lo
     * contrario sería una puerta trasera para dejar activo al empleado de una
     * cuenta cerrada. Fue justo ahí donde apareció el hueco de la cascada
     * anterior, así que aquí se cubre desde el principio.
     */
    public function eliminar($id, $tenantId = null): bool
    {
        try {
            $query = Usuario::where('id_usuario', $id);

            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }

            $usuario = $query->first();

            if ($usuario === null) {
                return false;
            }

            $seEstaDesactivando = (int) $usuario->estado === 1;
            $tenantIdUsuario = (int) $usuario->tenant_id;

            DB::transaction(function () use ($query, $id, $tenantIdUsuario, $seEstaDesactivando) {
                $query->update(['estado' => 0]);

                if ($seEstaDesactivando) {
                    $this->desactivarEmpleadoVinculado($id, $tenantIdUsuario);
                }
            });

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Por defecto solo trae las cuentas activas: una dada de baja no debe seguir
     * apareciendo en el listado normal. $incluirInactivos es la puerta para
     * verlas igual, por ejemplo desde un filtro "Mostrar inactivos" en la tabla,
     * o para poder abrir una en modo edición y reactivarla.
     */
    public function listar($tenantId = null, $incluirInactivos = false)
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
                ->addSelect($this->subconsultasDelEmpleadoVinculado());

            if ($tenantId !== null) {
                $query->where('u.tenant_id', $tenantId);
            }

            if (! $incluirInactivos) {
                $query->where('u.estado', 1);
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
