<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * El Excel de "Oferta y Programas" del SNIES trae 3 hojas (Programas, Cobertura convenios,
 * Cobertura); solo "Programas" nos interesa. `WithMultipleSheets` restringe la carga a esa hoja
 * — PhpSpreadsheet nunca toca las ~46.000 filas de "Cobertura".
 *
 * A propósito NO se combina con `WithChunkReading` en `SniesProgramasImport`: esa combinación
 * específica reventó el proceso por memoria en una prueba anterior (el worker se reiniciaba
 * solo). Aquí es una sola lectura de una sola hoja; el troceo para las escrituras en base de
 * datos pasa en memoria dentro de `SniesProgramasImport::collection()`, no releyendo el archivo.
 */
class SniesOfertaProgramasImport implements WithMultipleSheets
{
    public function __construct(private readonly int $importacionId)
    {
    }

    public function sheets(): array
    {
        return [
            'Programas' => new SniesProgramasImport($this->importacionId),
        ];
    }
}
