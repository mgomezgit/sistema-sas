<?php

namespace App\Imports;

use App\Service\SvcEmpleado;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Carga masiva de empleados desde un Excel.
 *
 * El tenant_id NUNCA se lee del archivo: se fuerza al negocio de la sesión, así
 * un archivo manipulado no puede insertar registros en otro negocio.
 *
 * Una fila inválida no detiene el proceso: se anota el motivo y se sigue con
 * las demás, para que una carga de 50 filas no se pierda por un dato suelto.
 */
class EmpleadosImport implements ToCollection
{
    /** Fila del Excel donde empiezan los datos (1 es el encabezado). */
    const PRIMERA_FILA_DE_DATOS = 2;

    private int $tenantId;

    private SvcEmpleado $svcEmpleado;

    /** @var array<int, array{fila:int, exito:bool, mensaje:string}> */
    private array $resultados = [];

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->svcEmpleado = new SvcEmpleado;
    }

    public function collection(Collection $filas): void
    {
        foreach ($filas as $indice => $fila) {
            // Se salta el encabezado.
            if ($indice === 0) {
                continue;
            }

            $numeroFila = $indice + 1;

            // Una fila totalmente vacía al final del archivo no es un error.
            if ($this->filaVacia($fila)) {
                continue;
            }

            $datos = [
                'nombre' => trim((string) ($fila[0] ?? '')),
                'telefono' => trim((string) ($fila[1] ?? '')),
                'email' => trim((string) ($fila[2] ?? '')),
                'cargo' => trim((string) ($fila[3] ?? '')),
                'porcentaje_comision' => $fila[4] ?? null,
            ];

            // Las mismas reglas que exige el alta normal de un empleado.
            $validador = Validator::make($datos, [
                'nombre' => 'required',
                'telefono' => 'required',
                'email' => 'nullable|email',
                'porcentaje_comision' => 'nullable|numeric|min:0|max:100',
            ]);

            if ($validador->fails()) {
                $this->resultados[] = [
                    'fila' => $numeroFila,
                    'exito' => false,
                    'mensaje' => implode(' ', $validador->errors()->all()),
                ];

                continue;
            }

            $idEmpleado = $this->svcEmpleado->crear([
                'tenant_id' => $this->tenantId,
                'nombre' => $datos['nombre'],
                'telefono' => $datos['telefono'],
                'email' => $datos['email'] ?: null,
                'cargo' => $datos['cargo'] ?: null,
                'porcentaje_comision' => $datos['porcentaje_comision'],
                'id_usuario' => null,
                'usuario_registra' => 'Carga Masiva',
                'fecha_registro' => now(),
                'estado' => 1,
            ]);

            $this->resultados[] = [
                'fila' => $numeroFila,
                'exito' => $idEmpleado !== false,
                'mensaje' => $idEmpleado !== false
                    ? 'Creado correctamente'
                    : 'No se pudo guardar el empleado',
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
