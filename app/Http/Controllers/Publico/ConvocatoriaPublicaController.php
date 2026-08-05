<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\TalentoHumano\Convocatoria;
use Illuminate\Support\Facades\Log;

class ConvocatoriaPublicaController extends Controller
{
    /**
     * Obtener todas las convocatorias (público, sin autenticación)
     */
    public function obtenerConvocatorias()
    {
        try {
            $convocatorias = Convocatoria::orderBy('created_at', 'desc')->get();

            if ($convocatorias->isEmpty()) {
                return response()->json([
                    'mensaje' => 'No se encontraron convocatorias',
                    'convocatorias' => []
                ], 200);
            }

            $convocatoriasTransformadas = $convocatorias->map(function ($conv) {
                return [
                    'id_convocatoria' => $conv->id_convocatoria,
                    'numero_convocatoria' => $conv->numero_convocatoria ?? 'CONV-' . $conv->id_convocatoria,
                    'nombre_convocatoria' => $conv->nombre_convocatoria,
                    'tipo' => $conv->tipo,
                    'periodo_academico' => $conv->periodo_academico ?? 'No especificado',
                    'cargo_solicitado' => $conv->cargo_solicitado ?? 'No especificado',
                    'facultad' => $conv->facultad ?? 'No especificado',
                    'cursos' => $conv->cursos ?? 'No especificado',
                    'tipo_vinculacion' => $conv->tipo_vinculacion ?? 'No especificado',
                    'personas_requeridas' => $conv->personas_requeridas ?? 1,
                    'fecha_publicacion' => $conv->fecha_publicacion,
                    'fecha_cierre' => $conv->fecha_cierre,
                    'fecha_inicio_contrato' => $conv->fecha_inicio_contrato,
                    'perfil_profesional' => $conv->perfil_profesional ?? '',
                    'experiencia_requerida' => $conv->experiencia_requerida ?? '',
                    'solicitante' => $conv->solicitante ?? 'Talento Humano',
                    'aprobaciones' => $conv->aprobaciones ?? '',
                    'descripcion' => $conv->descripcion,
                    'estado_convocatoria' => $conv->estado_convocatoria,
                    'created_at' => $conv->created_at,
                    'updated_at' => $conv->updated_at,
                ];
            });

            return response()->json(['convocatorias' => $convocatoriasTransformadas], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener convocatorias públicas: ' . $e->getMessage());

            return response()->json([
                'mensaje' => 'Error al obtener las convocatorias',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una convocatoria por ID (público, sin autenticación)
     */
    public function obtenerConvocatoriaPorId($id)
    {
        try {
            $convocatoria = Convocatoria::where('id_convocatoria', $id)->first();

            if (!$convocatoria) {
                return response()->json([
                    'mensaje' => 'Convocatoria no encontrada',
                    'error' => 'La convocatoria solicitada no existe'
                ], 404);
            }

            $convocatoriaTransformada = [
                'id_convocatoria' => $convocatoria->id_convocatoria,
                'numero_convocatoria' => $convocatoria->numero_convocatoria ?? 'CONV-' . $convocatoria->id_convocatoria,
                'nombre_convocatoria' => $convocatoria->nombre_convocatoria,
                'tipo' => $convocatoria->tipo,
                'periodo_academico' => $convocatoria->periodo_academico ?? '',
                'cargo_solicitado' => $convocatoria->cargo_solicitado ?? '',
                'facultad' => $convocatoria->facultad ?? '',
                'cursos' => $convocatoria->cursos ?? '',
                'tipo_vinculacion' => $convocatoria->tipo_vinculacion ?? '',
                'personas_requeridas' => $convocatoria->personas_requeridas ?? 1,
                'fecha_publicacion' => $convocatoria->fecha_publicacion,
                'fecha_cierre' => $convocatoria->fecha_cierre,
                'fecha_inicio_contrato' => $convocatoria->fecha_inicio_contrato,
                'perfil_profesional' => $convocatoria->perfil_profesional ?? '',
                'experiencia_requerida' => $convocatoria->experiencia_requerida ?? '',
                'solicitante' => $convocatoria->solicitante ?? '',
                'aprobaciones' => $convocatoria->aprobaciones ?? '',
                'descripcion' => $convocatoria->descripcion ?? '',
                'estado_convocatoria' => $convocatoria->estado_convocatoria,
                'created_at' => $convocatoria->created_at,
                'updated_at' => $convocatoria->updated_at,
                'documentos_convocatoria' => [],
            ];

            return response()->json(['convocatoria' => $convocatoriaTransformada], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener convocatoria pública por ID: ' . $e->getMessage());

            return response()->json([
                'mensaje' => 'Error al obtener la convocatoria',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
