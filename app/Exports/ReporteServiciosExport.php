<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Reporte de ingresos por servicio: una fila por servicio, con cuántas veces se
 * reservó y cuánto aportó en el rango consultado.
 */
class ReporteServiciosExport implements FromArray, WithHeadings
{
    private array $servicios;

    public function __construct(array $servicios)
    {
        $this->servicios = $servicios;
    }

    public function headings(): array
    {
        return [
            'Servicio',
            'Cantidad de Reservas',
            'Ingresos Totales',
        ];
    }

    public function array(): array
    {
        return array_map(function ($servicio) {
            return [
                $servicio['nombre_recurso'],
                (int) $servicio['cantidad_reservas'],
                (float) $servicio['ingresos_totales'],
            ];
        }, $this->servicios);
    }
}
