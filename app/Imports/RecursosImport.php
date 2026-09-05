<?php

namespace App\Imports;

use App\Service\SvcRecursoReservable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Carga masiva de servicios (recursos reservables) desde un Excel.
 *
 * Igual que el resto de importadores: el tenant_id se fuerza al negocio de la
 * sesión y una fila con error no interrumpe el proceso.
 */
class RecursosImport implements ToCollection
{
    private int $tenantId;

    private SvcRecursoReservable $svcRecurso;

    /** @var array<int, array{fila:int, exito:bool, mensaje:string}> */
    private array $resultados = [];

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->svcRecurso = new SvcRecursoReservable;
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
                'categoria' => trim((string) ($fila[0] ?? '')),
                'nombre' => trim((string) ($fila[1] ?? '')),
                'descripcion' => trim((string) ($fila[2] ?? '')),
                'duracion_minutos' => $fila[3] ?? null,
                'precio' => $fila[4] ?? null,
                'capacidad' => $fila[5] ?? null,
            ];

            // Mismos campos obligatorios que el alta normal de un servicio.
            $validador = Validator::make($datos, [
                'nombre' => 'required',
                'duracion_minutos' => 'required|numeric|min:1',
                'precio' => 'required|numeric|min:0',
                'capacidad' => 'nullable|numeric|min:1',
            ]);

            if ($validador->fails()) {
                $this->resultados[] = [
                    'fila' => $numeroFila,
                    'exito' => false,
                    'mensaje' => implode(' ', $validador->errors()->all()),
                ];

                continue;
            }

            $idRecurso = $this->svcRecurso->crear([
                'tenant_id' => $this->tenantId,
                'categoria' => $datos['categoria'] ?: null,
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'] ?: null,
                'duracion_minutos' => (int) $datos['duracion_minutos'],
                'precio' => $datos['precio'],
                'capacidad' => $datos['capacidad'] !== null && $datos['capacidad'] !== ''
                    ? (int) $datos['capacidad']
                    : null,
                'usuario_registra' => 'Carga Masiva',
                'fecha_registro' => now(),
                'estado' => 1,
            ]);

            $this->resultados[] = [
                'fila' => $numeroFila,
                'exito' => $idRecurso !== false,
                'mensaje' => $idRecurso !== false
                    ? 'Creado correctamente'
                    : 'No se pudo guardar el servicio',
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
