<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\RequestAdmin\RequestEscalonDocente\ActualizarReglaExcepcionEscalonRequest;
use App\Http\Requests\RequestAdmin\RequestEscalonDocente\CrearReglaExcepcionEscalonRequest;
use App\Constants\ClavePrimaria;
use App\Models\ReglaExcepcionEscalon;
use Illuminate\Support\Facades\Log;

/**
 * Administración de las reglas de excepción del escalafón docente: si un docente cumple la
 * condición, queda como mínimo en el escalón que otorga la regla, sin importar si cumple el
 * resto de requisitos de ese escalón. Ver `MotorEscalafonDocenteService`.
 */
class ReglaExcepcionEscalonController
{
    private function buscar($id): ?ReglaExcepcionEscalon
    {
        if (ClavePrimaria::fueraDeRango($id)) {
            return null;
        }

        return ReglaExcepcionEscalon::find($id);
    }

    public function listar()
    {
        try {
            $reglas = ReglaExcepcionEscalon::with('escalonOtorgado:id_escalon,nombre,orden')
                ->orderByDesc('id_regla_excepcion')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $reglas,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar las reglas de excepción del escalafón: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al listar las excepciones.',
            ], 500);
        }
    }

    public function crear(CrearReglaExcepcionEscalonRequest $request)
    {
        try {
            $regla = ReglaExcepcionEscalon::create($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Excepción creada correctamente.',
                'data' => $regla->load('escalonOtorgado:id_escalon,nombre,orden'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear la excepción del escalafón: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear la excepción.',
            ], 500);
        }
    }

    public function actualizar(ActualizarReglaExcepcionEscalonRequest $request, $id)
    {
        try {
            $regla = $this->buscar($id);

            if (!$regla) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Excepción no encontrada.',
                ], 404);
            }

            $regla->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Excepción actualizada correctamente.',
                'data' => $regla->fresh()->load('escalonOtorgado:id_escalon,nombre,orden'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al actualizar la excepción del escalafón: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al actualizar la excepción.',
            ], 500);
        }
    }

    public function eliminar($id)
    {
        try {
            $regla = $this->buscar($id);

            if (!$regla) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Excepción no encontrada.',
                ], 404);
            }

            $regla->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Excepción eliminada correctamente.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar la excepción del escalafón: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al eliminar la excepción.',
            ], 500);
        }
    }
}
