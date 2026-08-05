<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReporteCompletoExport implements WithMultipleSheets
{
    /**
     * Construye la colección de hojas que serán exportadas a Excel.
     *
     * Esta función define las diferentes hojas que se incluirán en el archivo Excel.
     * Cada hoja corresponde a una clase de exportación específica, que maneja un conjunto
     * particular de datos relacionados con los usuarios aspirantes.
     *
     * @return array
     */
    public function sheets(): array
    {
        return [
            new UsuariosExport(),
            new EstudiosUsuarioExport(),
            new ProduccionesAcademicasUsuarioExport(),
            new IdiomasUsuarioExport(),
            new ExperienciasUsuarioExport(),

        ];
        
    }
}
