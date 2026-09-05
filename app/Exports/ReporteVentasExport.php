<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Reporte de ventas: una fila por reserva del rango consultado.
 *
 * Recibe los datos ya consultados por el Service; aquí solo se les da forma de
 * hoja de cálculo, para no repartir consultas fuera de la capa de servicio.
 */
class ReporteVentasExport implements FromArray, WithHeadings
{
    private array $ventas;

    public function __construct(array $ventas)
    {
        $this->ventas = $ventas;
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Hora Inicio',
            'Hora Fin',
            'Cliente',
            'Servicio',
            'Empleado',
            'Estado',
            'Precio',
        ];
    }

    public function array(): array
    {
        return array_map(function ($venta) {
            return [
                $venta['fecha_reserva'],
                $venta['hora_inicio'],
                $venta['hora_fin'],
                $venta['nombre_cliente'],
                $venta['nombre_recurso'],
                // Una reserva puede no tener empleado asignado.
                $venta['nombre_empleado'] ?? 'Sin asignar',
                $venta['estado_reserva'],
                (float) $venta['precio'],
            ];
        }, $this->ventas);
    }
}
