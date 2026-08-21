<?php

namespace App\Http\Controllers\Admin;

use App\Constants\ClavePrimaria;
use App\Http\Requests\RequestAdmin\RequestSniesImportacion\SubirArchivoSniesRequest;
use App\Jobs\ImportarSniesJob;
use App\Models\SniesImportacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sube y procesa el Excel de "Oferta y Programas" del SNIES.
 *
 * El archivo se guarda en el disco privado `local` (no `public`: es un archivo de trabajo, se
 * borra al terminar) y el procesamiento real corre en `ImportarSniesJob`, despachado a la cola.
 * Requiere el servicio `queue-worker` de docker-compose.yml corriendo — sin él, la importación
 * se queda en estado `pendiente` para siempre.
 */
class SniesImportacionController
{
    public function subir(SubirArchivoSniesRequest $request)
    {
        try {
            $archivo = $request->file('archivo');
            $nombreOriginal = $archivo->getClientOriginalName();
            $nombreGuardado = Str::uuid() . '.xlsx';
            $ruta = $archivo->storeAs('snies-importaciones', $nombreGuardado, 'local');

            $importacion = SniesImportacion::create([
                'nombre_archivo' => $nombreOriginal,
                'ruta_archivo' => $ruta,
                'estado' => 'pendiente',
            ]);

            ImportarSniesJob::dispatch($importacion->id_importacion);

            return response()->json([
                'status' => 'success',
                'message' => 'Archivo recibido, la importación comenzará en breve.',
                // refresh(): create() solo trae en memoria los campos que se asignaron; sin esto
                // el JSON no incluye filas_procesadas/total_filas/etc. (sus valores por defecto
                // de la BD), y el frontend los recibe como undefined en vez de 0/null.
                'data' => $importacion->refresh(),
            ], 202);
        } catch (\Exception $e) {
            Log::error('Error al subir el archivo de importación SNIES: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al subir el archivo.',
            ], 500);
        }
    }

    public function estado($id)
    {
        try {
            if (ClavePrimaria::fueraDeRango($id)) {
                return response()->json(['status' => 'error', 'message' => 'Importación no encontrada.'], 404);
            }

            $importacion = SniesImportacion::find($id);

            if (!$importacion) {
                return response()->json(['status' => 'error', 'message' => 'Importación no encontrada.'], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $importacion,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al consultar el estado de la importación SNIES: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al consultar el estado de la importación.',
            ], 500);
        }
    }

    public function historial()
    {
        try {
            $importaciones = SniesImportacion::orderByDesc('id_importacion')->limit(20)->get();

            return response()->json([
                'status' => 'success',
                'data' => $importaciones,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar el historial de importaciones SNIES: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al listar el historial de importaciones.',
            ], 500);
        }
    }
}
