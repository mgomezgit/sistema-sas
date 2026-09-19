<?php

namespace App\Imports;

use App\Models\Rol;
use App\Models\Usuario;
use App\Service\SvcUsuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Carga masiva de usuarios desde un Excel.
 *
 * El archivo trae el rol como texto ("admin" o "empleado") y aquí se traduce al
 * id_rol correspondiente. Solo se aceptan esos dos: crear un super admin desde
 * una carga masiva sería una vía de escalada de privilegios.
 *
 * El tenant_id se fuerza al negocio de la sesión, nunca se lee del archivo.
 */
class UsuariosImport implements ToCollection
{
    /** Roles que se pueden asignar desde una carga masiva. */
    const ROLES_PERMITIDOS = ['admin', 'empleado'];

    private int $tenantId;

    private SvcUsuario $svcUsuario;

    /** @var array<int, array{fila:int, exito:bool, mensaje:string}> */
    private array $resultados = [];

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->svcUsuario = new SvcUsuario;
    }

    public function collection(Collection $filas): void
    {
        foreach ($filas as $indice => $fila) {
            if ($indice === 0) {
                continue;
            }

            $numeroFila = $indice + 1;

            if ($this->filaVacia($fila)) {
                continue;
            }

            $datos = [
                'usuario' => trim((string) ($fila[0] ?? '')),
                'nombre' => trim((string) ($fila[1] ?? '')),
                'email' => trim((string) ($fila[2] ?? '')),
                'clave' => trim((string) ($fila[3] ?? '')),
                'rol' => mb_strtolower(trim((string) ($fila[4] ?? ''))),
            ];

            // Mismos campos obligatorios que el alta normal de un usuario.
            $validador = Validator::make($datos, [
                'usuario' => 'required',
                'nombre' => 'required',
                'email' => 'required|email',
                'clave' => 'required',
                'rol' => 'required',
            ]);

            if ($validador->fails()) {
                $this->resultados[] = [
                    'fila' => $numeroFila,
                    'exito' => false,
                    'mensaje' => implode(' ', $validador->errors()->all()),
                ];

                continue;
            }

            if (! in_array($datos['rol'], self::ROLES_PERMITIDOS, true)) {
                $this->resultados[] = [
                    'fila' => $numeroFila,
                    'exito' => false,
                    'mensaje' => 'El rol debe ser "admin" o "empleado"',
                ];

                continue;
            }

            $idRol = Rol::where('nombre_rol', $datos['rol'])->value('id_rol');

            if (empty($idRol)) {
                $this->resultados[] = [
                    'fila' => $numeroFila,
                    'exito' => false,
                    'mensaje' => 'El rol "'.$datos['rol'].'" no existe en el sistema',
                ];

                continue;
            }

            // El nombre de usuario es la credencial de acceso: si ya lo tiene una
            // cuenta activa, el alta fallaría con un error opaco de base de datos.
            // Las cuentas desactivadas no compiten: su usuario queda libre.
            if (Usuario::where('usuario', $datos['usuario'])->where('estado', 1)->exists()) {
                $this->resultados[] = [
                    'fila' => $numeroFila,
                    'exito' => false,
                    'mensaje' => 'El usuario "'.$datos['usuario'].'" ya está registrado',
                ];

                continue;
            }

            // El correo es único en toda la plataforma, no solo dentro del negocio,
            // porque el login es una sola pantalla global: un mismo correo no puede
            // apuntar a dos cuentas ACTIVAS. Se comprueba aquí para decir cuál es
            // el problema; sin esto la fila fallaba con un "no se pudo guardar" que
            // no le explicaba nada a quien hizo la carga.
            if (Usuario::where('email', $datos['email'])->where('estado', 1)->exists()) {
                $this->resultados[] = [
                    'fila' => $numeroFila,
                    'exito' => false,
                    'mensaje' => 'Ya existe un usuario con el correo "'.$datos['email'].'"',
                ];

                continue;
            }

            $idUsuario = $this->svcUsuario->crear([
                'tenant_id' => $this->tenantId,
                'id_rol' => $idRol,
                'usuario' => $datos['usuario'],
                'nombre' => $datos['nombre'],
                'email' => $datos['email'],
                // El Service se encarga de encriptarla.
                'clave' => $datos['clave'],
                'usuario_registra' => 'Carga Masiva',
                'fecha_registro' => now(),
                'estado' => 1,
            ]);

            $this->resultados[] = [
                'fila' => $numeroFila,
                'exito' => $idUsuario !== false,
                'mensaje' => $idUsuario !== false
                    ? 'Creado correctamente'
                    : 'No se pudo guardar el usuario',
            ];
        }
    }

    private function filaVacia($fila): bool
    {
        foreach ($fila as $celda) {
            if (trim((string) $celda) !== '') {
                return false;
            }
        }

        return true;
    }

    public function resultados(): array
    {
        return $this->resultados;
    }
}
