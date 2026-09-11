<?php

namespace App\Http\Controllers\ApoyoProfesoral;

use App\Constants\ConstDocente\EstadoEvaluacionDocente;
use App\Http\Requests\RequestApoyoProfesoral\RequestEvaluacionDocente\ActualizarEvaluacionDocenteRequest;
use App\Http\Requests\RequestApoyoProfesoral\RequestEvaluacionDocente\AsignarEvaluacionDocenteRequest;
use App\Models\Docente\EvaluacionDocente;
use App\Models\Usuario\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gestiona las evaluaciones docentes desde el rol "Apoyo Profesoral".
 *
 * La evaluación docente dejó de ser una autoevaluación: es Apoyo Profesoral quien la
 * asigna a cada docente. El docente solo puede consultarla (ver `Docente\EvaluacionDocenteController`).
 * El promedio asignado alimenta el requisito de categoría de `MotorEscalafonDocenteService`
 * (evaluación >= `EscalonDocente.evaluacion_minima` de cada escalón), por lo que la escritura
 * queda restringida a este rol.
 */
class EvaluacionDocenteController
{
    /**
     * Columnas del usuario asignador que se exponen en las respuestas.
     *
     * Se limita a la identidad mínima para mostrar "asignada por Fulano": cargar la relación
     * completa serializaría también cédula, género, fecha de nacimiento y estado civil del
     * funcionario, datos que ninguna de estas respuestas necesita.
     */
    private const RELACION_ASIGNADOR = 'asignadaPor:id,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido';

    /**
     * Lista todos los docentes con su evaluación asignada (o null si aún no tienen).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listarEvaluaciones()
    {
        try {
            $docentes = User::role('Docente')
                ->with('evaluacionDocenteUsuario.' . self::RELACION_ASIGNADOR)
                ->get();

            $data = $docentes->map(function ($docente) {
                return [
                    'id' => $docente->id,
                    'nombre_completo' => $this->nombreCompleto($docente),
                    'email' => $docente->email,
                    'numero_identificacion' => $docente->numero_identificacion,
                    'evaluacion' => $docente->evaluacionDocenteUsuario,
                ];
            })->values();

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar las evaluaciones docentes: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al listar las evaluaciones docentes.',
            ], 500);
        }
    }

    /**
     * Consulta la evaluación asignada a un docente específico.
     *
     * @param int $userId ID del docente.
     * @return \Illuminate\Http\JsonResponse
     */
    public function verEvaluacionDocente($userId)
    {
        try {
            $docente = $this->buscarDocente($userId);

            if (!$docente) {
                return $this->respuestaDocenteInvalido($userId);
            }

            $evaluacion = EvaluacionDocente::with(self::RELACION_ASIGNADOR)
                ->where('user_id', $docente->id)
                ->first();

            if (!$evaluacion) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El docente aún no tiene una evaluación asignada.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $evaluacion,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener la evaluación docente: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener la evaluación docente.',
            ], 500);
        }
    }

    /**
     * Asigna una evaluación a un docente que aún no tiene una.
     *
     * Registra en la propia evaluación quién la asignó (`asignado_por`) y cuándo
     * (`fecha_asignacion`). Si el docente ya tiene evaluación, responde 409 y hay que
     * usar `actualizarEvaluacionDocente`.
     *
     * @param AsignarEvaluacionDocenteRequest $request Solicitud validada.
     * @param int $userId ID del docente al que se le asigna la evaluación.
     * @return \Illuminate\Http\JsonResponse
     */
    public function asignarEvaluacionDocente(AsignarEvaluacionDocenteRequest $request, $userId)
    {
        try {
            $docente = $this->buscarDocente($userId);

            if (!$docente) {
                return $this->respuestaDocenteInvalido($userId);
            }

            if (EvaluacionDocente::where('user_id', $docente->id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El docente ya tiene una evaluación asignada. Use la ruta de actualización.',
                ], 409);
            }

            $evaluacion = DB::transaction(function () use ($request, $docente) {
                $datos = $request->validated();
                $datos['user_id'] = $docente->id;
                // Si Apoyo Profesoral no envía un estado explícito, la evaluación nace 'Pendiente'.
                $datos['estado_evaluacion_docente'] ??= EstadoEvaluacionDocente::PENDIENTE;
                // Auditoría: quién asignó la evaluación y en qué momento.
                $datos['asignado_por'] = $request->user()->id;
                $datos['fecha_asignacion'] = now();

                $evaluacion = EvaluacionDocente::create($datos);
                \App\Models\MovimientoExpediente::registrar(
                    $docente->id, \App\Models\MovimientoExpediente::ACTUALIZADO, 'Evaluación docente',
                    'Evaluación asignada: ' . $evaluacion->estado_evaluacion_docente . '. Promedio: ' . $evaluacion->promedio_evaluacion_docente
                );
                return $evaluacion;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Evaluación docente asignada exitosamente.',
                'data' => $evaluacion->load(self::RELACION_ASIGNADOR),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al asignar la evaluación docente: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al asignar la evaluación docente.',
            ], 500);
        }
    }

    /**
     * Actualiza la evaluación ya asignada a un docente.
     *
     * Reasigna la auditoría al usuario que realiza el cambio, de modo que `asignado_por`
     * y `fecha_asignacion` siempre reflejan la última modificación de la calificación.
     *
     * @param ActualizarEvaluacionDocenteRequest $request Solicitud validada.
     * @param int $userId ID del docente cuya evaluación se actualiza.
     * @return \Illuminate\Http\JsonResponse
     */
    public function actualizarEvaluacionDocente(ActualizarEvaluacionDocenteRequest $request, $userId)
    {
        try {
            $docente = $this->buscarDocente($userId);

            if (!$docente) {
                return $this->respuestaDocenteInvalido($userId);
            }

            $evaluacion = EvaluacionDocente::where('user_id', $docente->id)->first();

            if (!$evaluacion) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El docente aún no tiene una evaluación asignada. Use la ruta de asignación.',
                ], 404);
            }

            DB::transaction(function () use ($request, $evaluacion) {
                $datos = $request->validated();
                $datos['asignado_por'] = $request->user()->id;
                $datos['fecha_asignacion'] = now();

                $evaluacion->update($datos);
                \App\Models\MovimientoExpediente::registrar(
                    $evaluacion->user_id, \App\Models\MovimientoExpediente::ACTUALIZADO, 'Evaluación docente',
                    'Evaluación actualizada: ' . $evaluacion->estado_evaluacion_docente . '. Promedio: ' . $evaluacion->promedio_evaluacion_docente
                );
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Evaluación docente actualizada exitosamente.',
                'data' => $evaluacion->fresh()->load(self::RELACION_ASIGNADOR),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al actualizar la evaluación docente: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al actualizar la evaluación docente.',
            ], 500);
        }
    }

    /**
     * Busca un usuario y confirma que efectivamente tenga el rol Docente.
     *
     * Evita que se asignen evaluaciones docentes a aspirantes, administrativos u otros roles.
     *
     * @param int $userId
     * @return User|null
     */
    private function buscarDocente($userId): ?User
    {
        $usuario = User::find($userId);

        return $usuario && $usuario->hasRole('Docente') ? $usuario : null;
    }

    /**
     * Respuesta estándar cuando el usuario indicado no existe o no es docente.
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    private function respuestaDocenteInvalido($userId)
    {
        return response()->json([
            'status' => 'error',
            'message' => "No se encontró un docente con el ID {$userId}.",
        ], 404);
    }

    /**
     * Arma el nombre completo del docente a partir de sus cuatro campos de nombre.
     *
     * @param User $docente
     * @return string
     */
    private function nombreCompleto(User $docente): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', [
            $docente->primer_nombre,
            $docente->segundo_nombre,
            $docente->primer_apellido,
            $docente->segundo_apellido,
        ])));
    }
}
