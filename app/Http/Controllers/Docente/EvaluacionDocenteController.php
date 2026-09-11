<?php

namespace App\Http\Controllers\Docente;

use Illuminate\Support\Facades\Log;

use App\Models\Docente\EvaluacionDocente;
use Illuminate\Http\Request;

/**
 * Acceso de solo lectura del docente a su propia evaluación.
 *
 * La evaluación docente la asigna el rol "Apoyo Profesoral"
 * (ver `App\Http\Controllers\ApoyoProfesoral\EvaluacionDocenteController`).
 * El docente no puede crearla ni modificarla, porque el promedio alimenta directamente
 * el requisito de ascenso de categoría en `MotorEscalafonDocenteService`.
 */
class EvaluacionDocenteController
{
    /**
     * Ver la evaluación docente del usuario autenticado.
     *
     * Si no se encuentra ninguna evaluación asociada, se devuelve una respuesta con código 404.
     * En caso de producirse un error durante la consulta, se captura la excepción
     * y se retorna una respuesta con el mensaje de error.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los datos de la evaluación o mensaje de error.
     */
    public function verEvaluacionDocente(Request $request)
    {
        try {
            $user = $request->user();
            $evaluacion = EvaluacionDocente::where('user_id', $user->id)->first();

            if (!$evaluacion) {
                return response()->json([
                    'message' => 'No se encontró su evaluación.',
                ], 404);
            }

            return response()->json(['data' => $evaluacion]);
        } catch (\Exception $e) {
            Log::error('EvaluacionDocenteController: ' . $e->getMessage(), ['excepcion' => $e]);
            return response()->json([
                'message' => 'Error al obtener la evaluación.',
            ], 500);
        }
    }
}
