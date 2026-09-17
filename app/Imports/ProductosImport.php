<?php

namespace App\Imports;

use App\Service\SvcProducto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Carga masiva de productos de inventario desde un Excel.
 *
 * Igual que el resto de importadores: el tenant_id se fuerza al negocio de la
 * sesión y una fila con error no interrumpe el proceso.
 *
 * La diferencia con los demás es el SKU: si la fila trae uno que ya existe en
 * ESE negocio, actualiza ese producto en vez de crear un duplicado. La búsqueda
 * del SKU va siempre acotada al tenant de la sesión, así que un archivo no puede
 * alcanzar el producto de otro negocio aunque comparta el código.
 *
 * "cantidad_actual" solo se usa al CREAR. Al actualizar se deja intacta a
 * propósito: el stock real se mueve con ingresarStock() (increment), y
 * sobrescribirlo desde una planilla descuadraría las existencias sin dejar
 * rastro de por qué cambiaron.
 */
class ProductosImport implements ToCollection
{
    private int $tenantId;

    private SvcProducto $svcProducto;

    /** @var array<int, array{fila:int, exito:bool, mensaje:string}> */
    private array $resultados = [];

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->svcProducto = new SvcProducto;
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
                'sku' => trim((string) ($fila[0] ?? '')),
                'nombre' => trim((string) ($fila[1] ?? '')),
                'descripcion' => trim((string) ($fila[2] ?? '')),
                'cantidad_actual' => $fila[3] ?? null,
                'cantidad_minima' => $fila[4] ?? null,
            ];

            $validador = Validator::make($datos, [
                'sku' => 'nullable|max:50',
                'nombre' => 'required',
                'cantidad_actual' => 'required|numeric|min:0',
                'cantidad_minima' => 'required|numeric|min:0',
            ]);

            if ($validador->fails()) {
                $this->resultados[] = [
                    'fila' => $numeroFila,
                    'exito' => false,
                    'mensaje' => implode(' ', $validador->errors()->all()),
                ];

                continue;
            }

            // Solo se considera "existente" lo que esté en el negocio de la
            // sesión: el SKU de otro negocio es invisible desde aquí.
            $existente = $datos['sku'] !== ''
                ? $this->svcProducto->buscarPorSku($datos['sku'], $this->tenantId)
                : null;

            $this->resultados[] = $existente !== null
                ? $this->actualizar($existente, $datos, $numeroFila)
                : $this->crear($datos, $numeroFila);
        }
    }

    private function actualizar(array $existente, array $datos, int $numeroFila): array
    {
        $resultado = $this->svcProducto->editar(
            $existente['id_producto'],
            [
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'] ?: null,
                'cantidad_minima' => (int) $datos['cantidad_minima'],
            ],
            $this->tenantId
        );

        return [
            'fila' => $numeroFila,
            'exito' => $resultado,
            'mensaje' => $resultado
                ? 'Actualizado por SKU (la cantidad actual no se modifica desde la carga masiva)'
                : 'No se pudo actualizar el producto',
        ];
    }

    private function crear(array $datos, int $numeroFila): array
    {
        $idProducto = $this->svcProducto->crear([
            'tenant_id' => $this->tenantId,
            'nombre' => $datos['nombre'],
            'sku' => $datos['sku'] !== '' ? $datos['sku'] : null,
            'descripcion' => $datos['descripcion'] ?: null,
            'cantidad_actual' => (int) $datos['cantidad_actual'],
            'cantidad_minima' => (int) $datos['cantidad_minima'],
            'usuario_registra' => 'Carga Masiva',
            'fecha_registro' => now(),
            'estado' => 1,
        ]);

        return [
            'fila' => $numeroFila,
            'exito' => $idProducto !== false,
            'mensaje' => $idProducto !== false
                ? 'Creado correctamente'
                : 'No se pudo guardar el producto',
        ];
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
