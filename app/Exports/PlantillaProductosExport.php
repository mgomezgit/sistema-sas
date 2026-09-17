<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Plantilla vacía para cargar productos de inventario en lote.
 * Los encabezados coinciden con lo que espera ProductosImport.
 *
 * El SKU es opcional, pero es la llave que decide si una fila actualiza un
 * producto que ya existe o crea uno nuevo.
 */
class PlantillaProductosExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'SKU',
            'Nombre',
            'Descripcion',
            'Cantidad Actual',
            'Cantidad Minima',
        ];
    }

    public function array(): array
    {
        return [
            ['SH-500', 'Shampoo hidratante 500ml', 'Para cabello seco', 12, 4],
            ['', 'Toallas desechables', 'Paquete x100', 30, 10],
        ];
    }
}
