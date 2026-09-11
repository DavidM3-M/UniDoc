<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\RequestAdmin\RequestExamenIdioma\ActualizarRangoExamenIdiomaRequest;
use App\Http\Requests\RequestAdmin\RequestExamenIdioma\CrearRangoExamenIdiomaRequest;
use App\Constants\ClavePrimaria;
use App\Models\RangoExamenIdioma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Administración de los rangos de puntaje de un examen de idioma y su nivel MCER equivalente
 * (ej. IELTS 5.5–6.5 = B2). Es la tabla de equivalencia que mantiene el Administrador.
 */
class RangoExamenIdiomaController
{
    private function buscar($id): ?RangoExamenIdioma
    {
        if (ClavePrimaria::fueraDeRango($id)) {
            return null;
        }

        return RangoExamenIdioma::find($id);
    }

    /**
     * Lista los rangos de un examen. Requiere `?examen_idioma_id=`.
     */
    public function listar(Request $request)
    {
        try {
            $rangos = RangoExamenIdioma::query()
                ->when(
                    $request->filled('examen_idioma_id'),
                    fn($query) => $query->where('examen_idioma_id', $request->input('examen_idioma_id'))
                )
                ->orderBy('puntaje_min')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $rangos,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar los rangos de examen de idioma: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al listar los rangos.',
            ], 500);
        }
    }

    public function crear(CrearRangoExamenIdiomaRequest $request)
    {
        try {
            $rango = RangoExamenIdioma::create($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Rango creado correctamente.',
                'data' => $rango,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear el rango de examen de idioma: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear el rango.',
            ], 500);
        }
    }

    public function actualizar(ActualizarRangoExamenIdiomaRequest $request, $id)
    {
        try {
            $rango = $this->buscar($id);

            if (!$rango) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Rango no encontrado.',
                ], 404);
            }

            $rango->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Rango actualizado correctamente.',
                'data' => $rango->fresh(),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al actualizar el rango de examen de idioma: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al actualizar el rango.',
            ], 500);
        }
    }

    public function eliminar($id)
    {
        try {
            $rango = $this->buscar($id);

            if (!$rango) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Rango no encontrado.',
                ], 404);
            }

            // De estos rangos sale el nivel MCER que se atribuye a cada puntaje. Quitar uno cambia
            // en silencio el nivel de los certificados que caían dentro, y el escalafón exige
            // `nivel_mcer_minimo`, así que puede mover la categoría de un docente.
            $certificados = DB::table('idiomas')
                ->where('examen_idioma_id', $rango->examen_idioma_id)
                ->whereNotNull('puntaje_obtenido')
                ->whereBetween('puntaje_obtenido', [$rango->puntaje_min, $rango->puntaje_max])
                ->count();

            if ($certificados > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "No se puede eliminar: {$certificados} certificado(s) tienen un puntaje dentro de este rango y perderían su nivel MCER. Ajuste los límites del rango en lugar de borrarlo.",
                    'certificados_asociados' => $certificados,
                ], 409);
            }

            $rango->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Rango eliminado correctamente.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar el rango de examen de idioma: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al eliminar el rango.',
            ], 500);
        }
    }
}
