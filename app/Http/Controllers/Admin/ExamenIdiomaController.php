<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\RequestAdmin\RequestExamenIdioma\ActualizarExamenIdiomaRequest;
use App\Http\Requests\RequestAdmin\RequestExamenIdioma\CrearExamenIdiomaRequest;
use App\Constants\ClavePrimaria;
use App\Models\ExamenIdioma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Administración de exámenes de certificación de idioma (IELTS, TOEFL iBT, Cambridge FCE...).
 *
 * Cada examen cuelga de un idioma del catálogo (`IdiomaController`) y define sus propios rangos
 * de puntaje con su equivalencia MCER (ver `RangoExamenIdiomaController`).
 */
class ExamenIdiomaController
{
    private function buscar($id, array $relaciones = []): ?ExamenIdioma
    {
        if (ClavePrimaria::fueraDeRango($id)) {
            return null;
        }

        return ExamenIdioma::with($relaciones)->find($id);
    }

    /**
     * Lista los exámenes de idioma, activos e inactivos.
     *
     * Acepta el filtro opcional `?idioma_id=` para ver solo los de un idioma. Incluye el conteo
     * de rangos definidos, que es lo que se muestra en la lista antes de expandir el examen.
     */
    public function listar(Request $request)
    {
        try {
            $examenes = ExamenIdioma::withCount('rangos')
                ->when(
                    $request->filled('idioma_id'),
                    fn($query) => $query->where('idioma_catalogo_id', $request->input('idioma_id'))
                )
                ->orderBy('nombre_examen')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $examenes,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar los exámenes de idioma: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al listar los exámenes de idioma.',
            ], 500);
        }
    }

    public function obtenerPorId($id)
    {
        try {
            $examen = $this->buscar($id, ['rangos']);

            if (!$examen) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Examen de idioma no encontrado.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $examen,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener el examen de idioma: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener el examen de idioma.',
            ], 500);
        }
    }

    public function crear(CrearExamenIdiomaRequest $request)
    {
        try {
            $examen = ExamenIdioma::create($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Examen de idioma creado correctamente.',
                'data' => $examen,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear el examen de idioma: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear el examen de idioma.',
            ], 500);
        }
    }

    public function actualizar(ActualizarExamenIdiomaRequest $request, $id)
    {
        try {
            $examen = $this->buscar($id);

            if (!$examen) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Examen de idioma no encontrado.',
                ], 404);
            }

            $examen->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Examen de idioma actualizado correctamente.',
                'data' => $examen->fresh(),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al actualizar el examen de idioma: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al actualizar el examen de idioma.',
            ], 500);
        }
    }

    /**
     * Elimina un examen de idioma junto con sus rangos (borrado en cascada por FK).
     *
     * A diferencia de Idioma/Ámbito de divulgación, no hay bloqueo por uso: todavía no existe
     * ningún registro de aspirante/docente que referencie un examen (esa conexión es una fase
     * posterior), así que no hay nada que pueda quedar huérfano.
     */
    public function eliminar($id)
    {
        try {
            $examen = $this->buscar($id);

            if (!$examen) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Examen de idioma no encontrado.',
                ], 404);
            }

            // Borrar el examen arrastra dos cosas por clave foránea: los rangos MCER se van en
            // CASCADE y `idiomas.examen_idioma_id` queda en SET NULL. Eso último es lo peligroso:
            // sin examen desaparece su `vigencia_meses`, y `calcularVigencia()` interpreta la
            // ausencia como "no vence", así que todo certificado caducado volvería a mostrarse
            // como vigente.
            $certificados = DB::table('idiomas')
                ->where('examen_idioma_id', $examen->id_examen_idioma)
                ->count();

            $rangos = DB::table('examenes_idioma_rangos')
                ->where('examen_idioma_id', $examen->id_examen_idioma)
                ->count();

            if ($certificados > 0 || $rangos > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "No se puede eliminar: {$certificados} certificado(s) de docentes y {$rangos} rango(s) de puntaje dependen de este examen. Puede marcarlo como inactivo para retirarlo de los formularios.",
                    'certificados_asociados' => $certificados,
                    'rangos_asociados' => $rangos,
                ], 409);
            }

            $examen->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Examen de idioma eliminado correctamente.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar el examen de idioma: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al eliminar el examen de idioma.',
            ], 500);
        }
    }
}
