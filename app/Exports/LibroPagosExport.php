<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Exporta el libro de pagos de la plataforma (el filtro actual).
 *
 * Extiende BinderCeldasSeguras: la referencia la escribe una persona y puede
 * empezar con =, +, -, @ — queda neutralizada como texto, nunca como fórmula.
 * Recibe las filas ya consultadas por el Service.
 */
class LibroPagosExport extends BinderCeldasSeguras implements FromArray, WithHeadings
{
    private array $filas;

    public function __construct(array $filas)
    {
        $this->filas = $filas;
    }

    public function headings(): array
    {
        return [
            'Comprobante',
            'Fecha',
            'Negocio',
            'Concepto',
            'Periodo',
            'Monto',
            'Metodo',
            'Referencia',
            'Estado',
        ];
    }

    public function array(): array
    {
        return $this->filas;
    }
}
