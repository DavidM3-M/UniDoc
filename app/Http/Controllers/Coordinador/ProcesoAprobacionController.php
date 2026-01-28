<?php

namespace App\Http\Controllers\Coordinador;

use App\Http\Controllers\Controller;
use App\Models\Coordinador\CoordinadorEvaluacion;
use App\Models\Coordinador\CoordinadorPlantilla;
use App\Models\TalentoHumano\Convocatoria;
use App\Models\TalentoHumano\Postulacion;
use App\Models\Usuario\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcesoAprobacionController extends Controller
{
    /**
     * Listar evaluaciones del coordinador (opcionalmente por aspirante).
     */
    public function index(Request $request)
    {
        try {
            $query = CoordinadorEvaluacion::query()
                ->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('users')
                        ->whereColumn('users.id', 'coordinador_evaluaciones.aspirante_user_id')
                        ->where('aval_talento_humano', true);
                })
                ->orderByDesc('created_at');

            if ($request->filled('aspirante_user_id')) {
                $query->where('aspirante_user_id', $request->aspirante_user_id);
            }

            return response()->json([
                'data' => $query->get(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener evaluaciones.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registrar evaluación del coordinador.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'aspirante_user_id' => 'required|exists:users,id',
                'plantilla_id' => 'nullable|exists:coordinador_plantillas,id',
                'prueba_psicotecnica' => 'required|string|max:255',
                'validacion_archivos' => 'required|boolean',
                'clase_organizada' => 'required|boolean',
                'aprobado' => 'required|boolean',
                'observaciones' => 'nullable|string|max:2000',
                'formulario' => 'nullable|array',
            ]);

            $aspirante = User::findOrFail($request->aspirante_user_id);

            if (! $aspirante->aval_talento_humano) {
                return response()->json([
                    'message' => 'El aspirante no está aprobado por Talento Humano.',
                ], 403);
            }

            // Eliminar evaluaciones anteriores del mismo aspirante
            CoordinadorEvaluacion::where('aspirante_user_id', $request->aspirante_user_id)->delete();

            $evaluacion = CoordinadorEvaluacion::create([
                'aspirante_user_id' => $request->aspirante_user_id,
                'coordinador_user_id' => $request->user()->id,
                'plantilla_id' => $request->plantilla_id,
                'prueba_psicotecnica' => $request->prueba_psicotecnica,
                'validacion_archivos' => $request->validacion_archivos,
                'clase_organizada' => $request->clase_organizada,
                'aprobado' => $request->aprobado,
                'observaciones' => $request->observaciones,
                'formulario' => $request->formulario,
            ]);

            return response()->json([
                'message' => 'Evaluación registrada correctamente.',
                'data' => $evaluacion,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar la evaluación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver evaluación de un aspirante específico (filtrado por aspirante_user_id)
     */
    public function show($userId)
    {
        try {
            $evaluacion = CoordinadorEvaluacion::where('aspirante_user_id', $userId)->first();
            if (!$evaluacion) {
                return response()->json([
                    'message' => 'Evaluación no encontrada para este usuario.',
                    'error' => 'No existe una evaluación para el usuario con ID proporcionado.'
                ], 404);
            }
            $plantilla = null;
            if ($evaluacion->plantilla_id) {
                $plantilla = CoordinadorPlantilla::find($evaluacion->plantilla_id);
            }
            return response()->json([
                'data' => [
                    'evaluacion' => $evaluacion,
                    'plantilla' => $plantilla,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error inesperado al obtener la evaluación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar una evaluación.
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'plantilla_id' => 'nullable|exists:coordinador_plantillas,id',
                'prueba_psicotecnica' => 'sometimes|required|string|max:255',
                'validacion_archivos' => 'sometimes|required|boolean',
                'clase_organizada' => 'sometimes|required|boolean',
                'aprobado' => 'sometimes|required|boolean',
                'observaciones' => 'nullable|string|max:2000',
                'formulario' => 'nullable|array',
            ]);

            $evaluacion = CoordinadorEvaluacion::findOrFail($id);
            $evaluacion->fill($request->only([
                'plantilla_id',
                'prueba_psicotecnica',
                'validacion_archivos',
                'clase_organizada',
                'aprobado',
                'observaciones',
                'formulario',
            ]));
            $evaluacion->save();

            return response()->json([
                'message' => 'Evaluación actualizada correctamente.',
                'data' => $evaluacion,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la evaluación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar plantillas del coordinador.
     */
    public function listarPlantillas(Request $request)
    {
        try {
            $plantillas = CoordinadorPlantilla::query()
                ->where('creado_por', $request->user()->id)
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'data' => $plantillas,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener plantillas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear plantilla de evaluación.
     */
    public function crearPlantilla(Request $request)
    {
        try {
            $request->validate([
                'nombre' => 'required|string|max:150',
                'descripcion' => 'nullable|string|max:2000',
                'estructura' => 'required|array',
            ]);

            $plantilla = CoordinadorPlantilla::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'estructura' => $request->estructura,
                'creado_por' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Plantilla creada correctamente.',
                'data' => $plantilla,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la plantilla.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver plantilla específica.
     */
    public function verPlantilla($id)
    {
        try {
            $plantilla = CoordinadorPlantilla::findOrFail($id);

            return response()->json([
                'data' => $plantilla,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la plantilla.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar plantilla.
     */
    public function actualizarPlantilla(Request $request, $id)
    {
        try {
            $request->validate([
                'nombre' => 'sometimes|required|string|max:150',
                'descripcion' => 'nullable|string|max:2000',
                'estructura' => 'sometimes|required|array',
            ]);

            $plantilla = CoordinadorPlantilla::findOrFail($id);
            $plantilla->fill($request->only(['nombre', 'descripcion', 'estructura']));
            $plantilla->save();

            return response()->json([
                'message' => 'Plantilla actualizada correctamente.',
                'data' => $plantilla,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la plantilla.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar postulaciones de aspirantes aprobados por Talento Humano, agrupadas por convocatoria.
     */
    public function listarPostulacionesPorConvocatoria(Request $request)
    {
        try {
            $estado = $request->query('estado');
            $estadoPostulacion = $request->query('estado_postulacion');

            $query = Postulacion::with(['usuarioPostulacion', 'convocatoriaPostulacion'])
                ->whereHas('usuarioPostulacion', function ($subQuery) {
                    $subQuery->where('aval_talento_humano', true);
                });

            if ($estado === 'talento_humano_aprobado') {
                // sin filtro adicional
            }

            if ($estadoPostulacion) {
                $query->where('estado_postulacion', $estadoPostulacion);
            }

            $postulaciones = $query->orderBy('created_at', 'desc')->get();

            $agrupadas = $postulaciones
                ->groupBy('convocatoria_id')
                ->map(function ($items) {
                    $convocatoria = optional($items->first())->convocatoriaPostulacion;

                    return [
                        'convocatoria' => $convocatoria,
                        'postulaciones' => $items->values(),
                    ];
                })
                ->values();

            return response()->json([
                'data' => $agrupadas,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener postulaciones por convocatoria.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar aspirantes aprobados por Talento Humano y su convocatoria.
     */
    public function listarAspirantesTalentoHumano(Request $request)
    {
        try {
            $estadoPostulacion = $request->query('estado_postulacion');

            $query = Postulacion::with(['usuarioPostulacion', 'convocatoriaPostulacion'])
                ->whereHas('usuarioPostulacion', function ($subQuery) {
                    $subQuery->where('aval_talento_humano', true);
                });

            if ($estadoPostulacion) {
                $query->where('estado_postulacion', $estadoPostulacion);
            }

            $postulaciones = $query->orderBy('created_at', 'desc')->get();

            $data = $postulaciones->map(function ($postulacion) {
                return [
                    'aspirante' => $postulacion->usuarioPostulacion,
                    'convocatoria' => $postulacion->convocatoriaPostulacion,
                    'estado_postulacion' => $postulacion->estado_postulacion,
                    'postulacion_id' => $postulacion->id_postulacion,
                ];
            });

            return response()->json([
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener aspirantes aprobados por Talento Humano.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar convocatorias con aspirantes aprobados por Talento Humano.
     */
    public function listarConvocatoriasConAspirantes(Request $request)
    {
        try {
            $estadoPostulacion = $request->query('estado_postulacion');

            $convocatorias = Convocatoria::whereHas('postulacionesConvocatoria', function ($subQuery) use ($estadoPostulacion) {
                if ($estadoPostulacion) {
                    $subQuery->where('estado_postulacion', $estadoPostulacion);
                }
                $subQuery->whereHas('usuarioPostulacion', function ($userQuery) {
                    $userQuery->where('aval_talento_humano', true);
                });
            })
                ->with(['postulacionesConvocatoria' => function ($subQuery) use ($estadoPostulacion) {
                    if ($estadoPostulacion) {
                        $subQuery->where('estado_postulacion', $estadoPostulacion);
                    }
                    $subQuery->with('usuarioPostulacion')
                        ->whereHas('usuarioPostulacion', function ($userQuery) {
                            $userQuery->where('aval_talento_humano', true);
                        });
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'data' => $convocatorias,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener convocatorias con aspirantes.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver información completa del aspirante para Coordinación.
     */
    public function verAspirante($id)
    {
        try {
            $aspirante = User::role('Aspirante')
                ->with([
                    'municipioUsuarios.departamentoMunicipio',
                    'fotoPerfilUsuario.documentosFotoPerfil',
                    'informacionContactoUsuario',
                    'epsUsuario',
                    'rutUsuario',
                    'idiomasUsuario',
                    'experienciasUsuario',
                    'estudiosUsuario',
                    'produccionAcademicaUsuario',
                    'aptitudesUsuario',
                    'postulacionesUsuario.convocatoriaPostulacion',
                    'documentosUser'
                ])
                ->findOrFail($id);

            $fotoUrl = null;
            if ($aspirante->fotoPerfilUsuario && $aspirante->fotoPerfilUsuario->documentosFotoPerfil->count() > 0) {
                $fotoUrl = asset('storage/' . $aspirante->fotoPerfilUsuario->documentosFotoPerfil->first()->archivo);
            }

            $documentos = $aspirante->documentosUser->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'nombre' => $doc->archivo,
                    'url' => asset('storage/' . $doc->archivo),
                    'tipo' => pathinfo($doc->archivo, PATHINFO_EXTENSION),
                ];
            });

            $data = [
                'id' => $aspirante->id,
                'datos_personales' => [
                    'primer_nombre' => $aspirante->primer_nombre,
                    'segundo_nombre' => $aspirante->segundo_nombre,
                    'primer_apellido' => $aspirante->primer_apellido,
                    'segundo_apellido' => $aspirante->segundo_apellido,
                    'tipo_identificacion' => $aspirante->tipo_identificacion,
                    'numero_identificacion' => $aspirante->numero_identificacion,
                    'genero' => $aspirante->genero,
                    'fecha_nacimiento' => $aspirante->fecha_nacimiento,
                    'estado_civil' => $aspirante->estado_civil,
                    'email' => $aspirante->email,
                    'municipio' => $aspirante->municipioUsuarios ? $aspirante->municipioUsuarios->nombre_municipio : null,
                    'departamento' => $aspirante->municipioUsuarios && $aspirante->municipioUsuarios->departamentoMunicipio
                        ? $aspirante->municipioUsuarios->departamentoMunicipio->nombre_departamento
                        : null,
                    'foto_perfil_url' => $fotoUrl,
                ],
                'informacion_contacto' => $aspirante->informacionContactoUsuario ? [
                    'telefono' => $aspirante->informacionContactoUsuario->telefono_movil ?? null,
                    'celular' => $aspirante->informacionContactoUsuario->celular_alternativo ?? null,
                    'direccion' => $aspirante->informacionContactoUsuario->direccion_residencia ?? null,
                    'barrio' => $aspirante->informacionContactoUsuario->barrio ?? null,
                    'correo_alterno' => $aspirante->informacionContactoUsuario->correo_alterno ?? null,
                ] : null,
                'eps' => $aspirante->epsUsuario,
                'rut' => $aspirante->rutUsuario,
                'idiomas' => $aspirante->idiomasUsuario,
                'experiencias' => $aspirante->experienciasUsuario,
                'estudios' => $aspirante->estudiosUsuario,
                'produccion_academica' => $aspirante->produccionAcademicaUsuario,
                'aptitudes' => $aspirante->aptitudesUsuario,
                'postulaciones' => $aspirante->postulacionesUsuario,
                'documentos' => $documentos,
                'avales' => [
                    'talento_humano' => [
                        'estado' => $aspirante->aval_talento_humano,
                        'aprobado_por' => $aspirante->aval_talento_humano_by,
                        'fecha' => $aspirante->aval_talento_humano_at,
                    ],
                    'coordinador' => [
                        'estado' => $aspirante->aval_coordinador,
                        'aprobado_por' => $aspirante->aval_coordinador_by,
                        'fecha' => $aspirante->aval_coordinador_at,
                    ],
                    'vicerrectoria' => [
                        'estado' => $aspirante->aval_vicerrectoria,
                        'aprobado_por' => $aspirante->aval_vicerrectoria_by,
                        'fecha' => $aspirante->aval_vicerrectoria_at,
                    ],
                    'rectoria' => [
                        'estado' => $aspirante->aval_rectoria,
                        'aprobado_por' => $aspirante->aval_rectoria_by,
                        'fecha' => $aspirante->aval_rectoria_at,
                    ],
                ],
            ];

            return response()->json(['aspirante' => $data], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener aspirante (coordinador): ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al obtener información del aspirante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar todas las evaluaciones con datos de usuario aspirante y coordinador
     */
    public function evaluacionesConUsuarios()
    {
        try {
            $evaluaciones = CoordinadorEvaluacion::with(['aspirante', 'coordinador'])
                ->orderByDesc('created_at')
                ->get();

            $data = $evaluaciones->map(function ($eval) {
                return [
                    'evaluacion' => $eval,
                    'aspirante' => $eval->aspirante,
                    'coordinador' => $eval->coordinador,
                ];
            });

            return response()->json(['data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener evaluaciones con usuarios.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
