<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Plantilla vacía para cargar usuarios en lote.
 *
 * La columna "Rol" va como texto (admin o empleado); UsuariosImport se encarga
 * de traducirlo al id_rol correspondiente.
 */
class PlantillaUsuariosExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Usuario',
            'Nombre',
            'Email',
            'Clave Temporal',
            'Rol',
        ];
    }

    public function array(): array
    {
        return [
            ['ana.restrepo', 'Ana María Restrepo', 'ana.restrepo@ejemplo.com', 'Temporal2026', 'empleado'],
        ];
    }
}
