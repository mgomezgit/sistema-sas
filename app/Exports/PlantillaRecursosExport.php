<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Plantilla vacía para cargar servicios (recursos reservables) en lote.
 * Los encabezados coinciden con lo que espera RecursosImport.
 */
class PlantillaRecursosExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Categoria',
            'Nombre',
            'Descripcion',
            'Duracion Minutos',
            'Precio',
            'Capacidad',
        ];
    }

    public function array(): array
    {
        return [
            ['Masajes', 'Masaje relajante', 'Masaje de cuerpo completo con aceites', 60, 95000, 1],
        ];
    }
}
