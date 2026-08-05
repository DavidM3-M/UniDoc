<?php

namespace App\Http\Controllers\Docente;

use App\Http\Requests\RequestDocente\RequestEvaluacionDocente\ActualizarEvaluacionDocenteRequest;
use App\Http\Requests\RequestDocente\RequestEvaluacionDocente\CrearEvaluacionDocenteRequest;
use App\Models\Docente\EvaluacionDocente;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;


//Este controlador maneja las evaluaciones docentes, permitiendo crear, ver y actualizar evaluaciones.
class EvaluacionDocenteController
{
    /**
     * Crear una evaluación docente para el usuario autenticado.
     *
     * Este método permite registrar una única evaluación docente por usuario. Antes de la creación,
     * se verifica si ya existe una evaluación registrada para evitar duplicados. Si no existe,
     * se procede a crear el registro dentro de una transacción para asegurar la integridad de los datos,
     * asignando automáticamente el estado "Pendiente" a la evaluación. En caso de que ya exista una evaluación
     * o ocurra un error durante el proceso, se retorna una respuesta adecuada con el mensaje correspondiente.
     *
     * @param CrearEvaluacionDocenteRequest $request Solicitud validada con los datos de la evaluación docente.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function crearEvaluacionDocente(CrearEvaluacionDocenteRequest $request)
    {
        try {
            $usuarioId = $request->user()->id; // Obtener el ID del usuario autenticado
            $evaluacionExistente = EvaluacionDocente::where('user_id', $usuarioId)->first(); // Verificar si ya existe una evaluación para el usuario

            if ($evaluacionExistente) {
                return response()->json([ // Si ya existe, devuelve una respuesta con código 409 (conflicto) e impide crear otra.
                    'message' => 'Ya tienes una evaluación docente registrada. No puedes crear otra.',
                ], 409);
            }

            DB::transaction(function () use ($request) { // Inicia una transacción de base para asegurar que se ejecute correctamente.

                $datosEvaluacion = $request->validated(); // Valida los datos de la solicitud
                $datosEvaluacion['user_id'] = $request->user()->id; // Asigna el ID del usuario autenticado a los datos de la evaluación

                $datosEvaluacion['estado_evaluacion_docente'] = 'Pendiente'; // Asignar automáticamente estado 'pendiente'

                EvaluacionDocente::create($datosEvaluacion); // Crea la evaluación docente en la base de datos
            });

            return response()->json([
                'message' => 'Evaluación docente creada exitosamente.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([ // Manejo de excepciones
                'message' => 'Error al crear la evaluación docente.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver la evaluación docente asociada a un usuario por su ID.
     *
     * Este método permite consultar una evaluación docente registrada para un usuario específico,
     * identificada por su ID. Si no se encuentra ninguna evaluación asociada, se devuelve una respuesta
     * con código 404. En caso de producirse un error durante la consulta, se captura la excepción
     * y se retorna una respuesta con el mensaje de error.
     *
     * @param int $user_id ID del usuario cuya evaluación docente se desea consultar.
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
        return response()->json([
            'message' => 'Error al obtener la evaluación.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Actualizar la evaluación docente del usuario autenticado.
     *
     * Este método permite modificar una evaluación docente existente, asociada al usuario autenticado.
     * Los datos se validan y la operación se ejecuta dentro de una transacción para garantizar la integridad
     * de la información. Si la evaluación no existe o se presenta un error durante la actualización,
     * se captura una excepción y se retorna una respuesta con el mensaje correspondiente.
     *
     * @param ActualizarEvaluacionDocenteRequest $request Solicitud validada con los datos actualizados de la evaluación.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function actualizarEvaluacionDocente(ActualizarEvaluacionDocenteRequest $request)
    {
        try {
            DB::transaction(function () use ($request) { // Inicia una transacción de base de datos para asegurar la atomicidad de la operación

                $user = $request->user(); // Obtener el usuario autenticado
                $evaluacion = EvaluacionDocente::where('user_id', $user->id)->first(); // Obtener la evaluación docente del usuario autenticado
                $datosEvaluacionActualizar = $request->validated(); // Validar los datos de la solicitud
                $evaluacion->update($datosEvaluacionActualizar); // Actualizar la evaluación docente con los nuevos datos

            });

            return response()->json([ // Devuelve una respuesta JSON indicando que la evaluación se actualizó exitosamente
                'message' => 'Evaluación actualizada exitosamente.',
            ]);
        } catch (\Exception $e) { // Manejo de excepciones
            return response()->json([
                'message' => 'Error al actualizar la evaluación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
