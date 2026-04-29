<?php

namespace App\Http\Controllers\Aspirante;

use App\Services\PuntajeAspiranteService;
use Illuminate\Http\Request;

class PuntajeAspiranteController
{
    public function __construct(private readonly PuntajeAspiranteService $service) {}

    /**
     * Devuelve el puntaje de aptitud de un aspirante.
     * Si no se pasa {userId} se usa el usuario autenticado.
     */
    public function calcular(Request $request, ?int $userId = null): \Illuminate\Http\JsonResponse
    {
        try {
            $targetId = $userId ?? $request->user()->id;
            $resultado = $this->service->calcular($targetId);
            return response()->json($resultado, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al calcular el puntaje.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
