<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Neutraliza la inyección de fórmulas en las exportaciones a Excel.
 *
 * PhpSpreadsheet, por defecto, guarda como FÓRMULA cualquier texto que
 * empiece con "=". En los reportes van textos que escribió una persona que no
 * es el admin: el nombre del cliente llega desde el formulario anónimo de la
 * página pública. Un visitante que agende como "=HYPERLINK(...)" dejaría una
 * fórmula viva en el Excel que abre el admin.
 *
 * Todo texto que empiece con =, +, -, @, tabulador o retorno de carro se
 * guarda como TEXTO explícito y con "quote prefix", que es exactamente lo que
 * hace Excel cuando alguien escribe un apóstrofo inicial: la celda se ve igual
 * (el apóstrofo no aparece ni queda dentro del valor), pero nunca se evalúa,
 * ni siquiera si luego se edita la celda.
 *
 * Los números (int/float) no pasan por aquí: siguen siendo números.
 *
 * Toda exportación que lleve texto escrito por usuarios debe extender esta
 * clase. ExportacionesSegurasTest lo verifica.
 */
abstract class BinderCeldasSeguras extends DefaultValueBinder implements WithCustomValueBinder
{
    const PREFIJOS_PELIGROSOS = ['=', '+', '-', '@', "\t", "\r"];

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && $value !== '' && in_array($value[0], self::PREFIJOS_PELIGROSOS, true)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            $cell->getStyle()->setQuotePrefix(true);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
