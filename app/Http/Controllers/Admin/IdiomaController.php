<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\RequestAdmin\RequestIdioma\ActualizarIdiomaRequest;
use App\Http\Requests\RequestAdmin\RequestIdioma\CrearIdiomaRequest;
use App\Constants\ClavePrimaria;
use App\Models\Idioma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Administración del catálogo de idiomas (ej. Inglés, Francés).
 *
 * Cada idioma agrupa a sus exámenes de certificación (ver `ExamenIdiomaController`), que son los
 * que definen la equivalencia entre puntaje numérico y nivel MCER.
 */
class IdiomaController
{
    private function buscar($id): ?Idioma
    {
        if (ClavePrimaria::fueraDeRango($id)) {
            return null;
        }

        return Idioma::find($id);
    }

    public function listar()
    {
        try {
            $idiomas = Idioma::withCount('examenes')
                ->orderBy('nombre_idioma')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $idiomas,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar los idiomas: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al listar los idiomas.',
            ], 500);
        }
    }

    public function obtenerPorId($id)
    {
        try {
            $idioma = $this->buscar($id);

            if (!$idioma) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Idioma no encontrado.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $idioma,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener el idioma: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener el idioma.',
            ], 500);
        }
    }

    public function crear(CrearIdiomaRequest $request)
    {
        try {
            $idioma = Idioma::create($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Idioma creado correctamente.',
                'data' => $idioma,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear el idioma: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear el idioma.',
            ], 500);
        }
    }

    public function actualizar(ActualizarIdiomaRequest $request, $id)
    {
        try {
            $idioma = $this->buscar($id);

            if (!$idioma) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Idioma no encontrado.',
                ], 404);
            }

            $idioma->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Idioma actualizado correctamente.',
                'data' => $idioma->fresh(),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al actualizar el idioma: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al actualizar el idioma.',
            ], 500);
        }
    }

    /**
     * Elimina un idioma que no tenga exámenes asociados.
     *
     * Si tiene exámenes se responde 409 en lugar de dejar que reviente la llave foránea: borrarlo
     * arrastraría exámenes y sus rangos de equivalencia. Para retirarlo sin perder esos datos
     * está `activo = false`.
     */
    public function eliminar($id)
    {
        try {
            $idioma = $this->buscar($id);

            if (!$idioma) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Idioma no encontrado.',
                ], 404);
            }

            $examenes = $idioma->examenes()->count();

            // La guarda contaba solo los exámenes. Pero `idiomas.idioma_catalogo_id` está en
            // SET NULL y el nombre queda copiado como texto en el registro: un idioma sin exámenes
            // pero con certificados se borraba, y esos certificados pasaban a ser ineditables
            // porque su nombre ya no existe en el catálogo.
            $certificados = DB::table('idiomas')
                ->where('idioma_catalogo_id', $idioma->id_idioma_catalogo)
                ->orWhere('idioma', $idioma->nombre_idioma)
                ->count();

            if ($examenes > 0 || $certificados > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "No se puede eliminar: hay {$examenes} examen(es) de certificación y {$certificados} certificado(s) de docentes asociados a este idioma. Puede marcarlo como inactivo para retirarlo de los formularios.",
                    'examenes_asociados' => $examenes,
                    'certificados_asociados' => $certificados,
                ], 409);
            }

            $idioma->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Idioma eliminado correctamente.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar el idioma: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al eliminar el idioma.',
            ], 500);
        }
    }
}
