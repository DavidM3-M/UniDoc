<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\RequestAdmin\RequestEscalonDocente\ActualizarEscalonDocenteRequest;
use App\Http\Requests\RequestAdmin\RequestEscalonDocente\CrearEscalonDocenteRequest;
use App\Constants\ClavePrimaria;
use App\Models\EscalonDocente;
use Illuminate\Support\Facades\Log;

/**
 * Administración de los escalones del escalafón docente (Auxiliar, Asistente, Asociado,
 * Titular...) y sus requisitos de ascenso.
 *
 * Los casos como "tiene Doctorado → mínimo Asociado" no se configuran aquí: son reglas de
 * excepción (ver `ReglaExcepcionEscalonController`), separadas a propósito para que el motor de
 * evaluación (`MotorEscalafonDocenteService`) no necesite lógica especial por escalón.
 */
class EscalonDocenteController
{
    private function buscar($id): ?EscalonDocente
    {
        if (ClavePrimaria::fueraDeRango($id)) {
            return null;
        }

        return EscalonDocente::with('idioma:id_idioma_catalogo,nombre_idioma')->find($id);
    }

    public function listar()
    {
        try {
            // `historial_count` deja que la pantalla sepa que el escalón está en uso antes de
            // intentar borrarlo y llevarse un 409.
            $escalones = EscalonDocente::with('idioma:id_idioma_catalogo,nombre_idioma')
                ->withCount(['excepciones', 'historial'])
                ->ordenados()
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $escalones,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar los escalones del escalafón docente: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al listar los escalones.',
            ], 500);
        }
    }

    public function obtenerPorId($id)
    {
        try {
            $escalon = $this->buscar($id);

            if (!$escalon) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Escalón no encontrado.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $escalon,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener el escalón: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener el escalón.',
            ], 500);
        }
    }

    public function crear(CrearEscalonDocenteRequest $request)
    {
        try {
            $escalon = EscalonDocente::create($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Escalón creado correctamente.',
                'data' => $escalon->load('idioma:id_idioma_catalogo,nombre_idioma'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear el escalón: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear el escalón.',
            ], 500);
        }
    }

    public function actualizar(ActualizarEscalonDocenteRequest $request, $id)
    {
        try {
            $escalon = $this->buscar($id);

            if (!$escalon) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Escalón no encontrado.',
                ], 404);
            }

            $escalon->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Escalón actualizado correctamente.',
                'data' => $escalon->fresh()->load('idioma:id_idioma_catalogo,nombre_idioma'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al actualizar el escalón: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al actualizar el escalón.',
            ], 500);
        }
    }

    /**
     * Elimina un escalón que nadie esté usando.
     *
     * Dos cosas lo pueden estar usando: una regla de excepción que lo otorgue como piso, y el
     * historial de los docentes que lo tuvieron. Lo segundo faltaba, y no era un descuido inocuo:
     * la FK `historial_escalon_docente.escalon_id` es `restrictOnDelete` —a propósito, para que
     * borrar un escalón no se lleve por delante el expediente de nadie—, así que la petición no
     * pasaba en silencio: PostgreSQL abortaba con SQLSTATE 23503, la excepción caía en el `catch`
     * genérico y la API devolvía un 500 donde correspondía un 409 explicando qué estorba.
     *
     * Para retirarlo de la evaluación sin perder el histórico de docentes que ya lo alcanzaron
     * está `activo = false`.
     */
    public function eliminar($id)
    {
        try {
            $escalon = $this->buscar($id);

            if (!$escalon) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Escalón no encontrado.',
                ], 404);
            }

            $excepciones = $escalon->excepciones()->count();
            $tramos = $escalon->historial()->count();
            $docentes = $escalon->historial()->distinct()->count('user_id');

            if ($excepciones > 0 || $tramos > 0) {
                $motivos = [];

                if ($excepciones > 0) {
                    $motivos[] = "{$excepciones} excepción(es) lo otorgan como mínimo";
                }
                if ($tramos > 0) {
                    $motivos[] = "{$docentes} docente(s) lo tienen o lo tuvieron en su historial";
                }

                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede eliminar: ' . implode(' y ', $motivos)
                        . '. Puede marcarlo como inactivo para retirarlo de la evaluación.',
                    'excepciones_asociadas' => $excepciones,
                    'docentes_asociados' => $docentes,
                    'tramos_asociados' => $tramos,
                ], 409);
            }

            $escalon->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Escalón eliminado correctamente.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar el escalón: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al eliminar el escalón.',
            ], 500);
        }
    }
}
