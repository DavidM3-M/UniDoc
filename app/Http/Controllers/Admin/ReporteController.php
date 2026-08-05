<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReporteCompletoExport;
use Maatwebsite\Excel\Facades\Excel;

// Este controlador maneja la generación de reportes en formato Excel
// para la sección de administración de la aplicación.
class ReporteController
{
    /**
     * Genera y descarga un reporte completo en formato Excel con los datos de los usuarios.
     *
     * Este método utiliza la clase ReporteCompletoExport para estructurar el contenido del archivo,
     * y la función `Excel::download()` de Maatwebsite para enviar el archivo al navegador como una descarga.
     * Si ocurre algún error durante el proceso, se captura y se devuelve una respuesta JSON con el mensaje.
     */
    public function usuariosExcel()
    {
        try {
            // Genera el archivo Excel usando la clase ReporteCompletoExport
            return Excel::download(new ReporteCompletoExport, 'reporteUnidoc.xlsx');
        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json([
                'message' => 'Error al generar el reporte.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
