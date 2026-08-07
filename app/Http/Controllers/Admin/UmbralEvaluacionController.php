<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\RequestAdmin\RequestUmbralEvaluacion\CrearUmbralEvaluacionRequest;
use App\Services\UmbralEvaluacionDocenteService;
use Illuminate\Support\Facades\Log;

/**
 * Administración del umbral mínimo de evaluación docente.
 *
 * Este umbral es el requisito de evaluación que un docente debe alcanzar para ascender
 * de categoría (`CalculoPuntajeDocenteService`). Antes estaba hardcodeado en 4.0.
 *
 * Los umbrales no se editan ni se borran: registrar uno nuevo cierra el anterior con
 * `vigencia_hasta`, conservando el histórico. Eso es lo que permite reconstruir qué
 * regía cuando se le otorgó su categoría a cada docente.
 */
class UmbralEvaluacionController
{
    public function __construct(private UmbralEvaluacionDocenteService $umbrales)
    {
    }

    /**
     * Consulta el umbral vigente.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerUmbralVigente()
    {
        try {
            $umbral = $this->umbrales->vigente();

            if (!$umbral) {
                // Sin registros, el cálculo usa el valor por defecto histórico.
                return response()->json([
                    'status' => 'success',
                    'message' => 'No hay ningún umbral registrado. Se aplica el valor por defecto.',
                    'data' => [
                        'valor_minimo' => UmbralEvaluacionDocenteService::VALOR_POR_DEFECTO,
                        'por_defecto' => true,
                    ],
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'data' => $umbral,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener el umbral de evaluación vigente: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener el umbral vigente.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista el histórico completo de umbrales, del más reciente al más antiguo.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerHistoricoUmbrales()
    {
        try {
            return response()->json([
                'status' => 'success',
                'data' => $this->umbrales->historico(),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar el histórico de umbrales: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al listar el histórico de umbrales.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registra un umbral nuevo y cierra el vigente.
     *
     * @param CrearUmbralEvaluacionRequest $request Solicitud validada.
     * @return \Illuminate\Http\JsonResponse
     */
    public function crearUmbral(CrearUmbralEvaluacionRequest $request)
    {
        try {
            $datos = $request->validated();

            $umbral = $this->umbrales->registrar(
                (float) $datos['valor_minimo'],
                $datos['vigencia_desde'],
                $request->user()->id,
                $datos['observaciones'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Umbral de evaluación registrado. Las categorías ya otorgadas no se ven afectadas.',
                'data' => $umbral->load('creadoPor:id,primer_nombre,primer_apellido'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al registrar el umbral de evaluación: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al registrar el umbral de evaluación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
