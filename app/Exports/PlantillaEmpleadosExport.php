<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Plantilla vacía para cargar empleados en lote.
 *
 * Los encabezados coinciden con lo que espera EmpleadosImport, y va una fila de
 * ejemplo para que se vea el formato esperado de cada columna.
 */
class PlantillaEmpleadosExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Nombre',
            'Telefono',
            'Email',
            'Cargo',
            'Porcentaje Comision',
        ];
    }

    public function array(): array
    {
        return [
            ['Ana María Restrepo', '3001234567', 'ana.restrepo@ejemplo.com', 'Masajista', 15],
        ];
    }
}
