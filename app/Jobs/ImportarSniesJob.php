<?php

namespace App\Jobs;

use App\Imports\SniesOfertaProgramasImport;
use App\Models\SniesImportacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Procesa en segundo plano un Excel de "Oferta y Programas" del SNIES ya subido. Requiere un
 * worker de colas corriendo (`php artisan queue:work`, ver servicio `queue-worker` en
 * docker-compose.yml) — sin eso el Job queda pendiente en la tabla `jobs` indefinidamente.
 */
class ImportarSniesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    // Archivos SNIES pueden traer decenas de miles de filas.
    public int $timeout = 1800;

    public function __construct(private readonly int $importacionId)
    {
    }

    public function handle(): void
    {
        $importacion = SniesImportacion::find($this->importacionId);
        if (!$importacion) {
            return;
        }

        $rutaAbsoluta = Storage::disk('local')->path($importacion->ruta_archivo);

        // Laravel guarda cada query ejecutada en un array en memoria si el query log está
        // activo (típico con APP_DEBUG=true o con Telescope/Debugbar escuchando). Con miles de
        // queries en un import grande eso infla el RAM sin razón — se apaga explícitamente.
        DB::connection()->disableQueryLog();

        try {
            $importacion->update(['estado' => 'procesando', 'iniciado_en' => now()]);

            // listWorksheetInfo lee solo las dimensiones de la hoja, no cada celda: barato incluso
            // en archivos grandes, y es lo que permite mostrar "1.204 / 48.213 filas" en el frontend.
            // Se busca la hoja "Programas" por nombre (no por índice 0): el archivo real del SNIES
            // trae más hojas (Cobertura, Cobertura convenios) y su orden no está garantizado.
            $info = IOFactory::createReader('Xlsx')->listWorksheetInfo($rutaAbsoluta);
            $hojaProgramas = collect($info)->firstWhere('worksheetName', 'Programas') ?? ($info[0] ?? ['totalRows' => 1]);
            $importacion->update(['total_filas' => max(0, ($hojaProgramas['totalRows'] ?? 1) - 1)]);

            Excel::import(new SniesOfertaProgramasImport($importacion->id_importacion), $rutaAbsoluta);

            $importacion->update(['estado' => 'completado', 'finalizado_en' => now()]);
        } catch (\Throwable $e) {
            Log::error('Error al importar programas SNIES: ' . $e->getMessage());
            $importacion->update([
                'estado' => 'fallido',
                'mensaje_error' => $e->getMessage(),
                'finalizado_en' => now(),
            ]);
        } finally {
            Storage::disk('local')->delete($importacion->ruta_archivo);
        }
    }
}
