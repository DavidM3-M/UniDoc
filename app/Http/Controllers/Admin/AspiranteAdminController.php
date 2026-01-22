<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Usuario\User;
use App\Services\GeneradorHojaDeVidaPDFService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AspiranteAdminController extends Controller
{
    protected $generadorPDFService;

    public function __construct(GeneradorHojaDeVidaPDFService $generadorPDFService)
    {
        $this->generadorPDFService = $generadorPDFService;
    }

    /**
     * Comprueba de forma robusta si un usuario tiene un rol dado.
     * Soporta Spatie (`hasRole`, `getRoleNames`), relación `roles`, o atributos `rol`/`role`.
     */
    private function userHasRole($usuario, string $roleName): bool
    {
        try {
            if (! $usuario) {
                return false;
            }

            if (method_exists($usuario, 'hasRole')) {
                return (bool) $usuario->hasRole($roleName);
            }

            if (method_exists($usuario, 'getRoleNames')) {
                $roleNames = $usuario->getRoleNames();
                if (is_iterable($roleNames)) {
                    foreach ($roleNames as $r) {
                        if ((string) $r === $roleName) {
                            return true;
                        }
                    }
                }
            }

            if (isset($usuario->roles) && is_iterable($usuario->roles)) {
                // Eloquent relation or array
                $names = [];
                if ($usuario->roles instanceof \Illuminate\Support\Collection) {
                    $names = $usuario->roles->pluck('name')->toArray();
                } else {
                    foreach ($usuario->roles as $r) {
                        if (is_array($r) && isset($r['name'])) {
                            $names[] = $r['name'];
                        } elseif (is_object($r) && isset($r->name)) {
                            $names[] = $r->name;
                        } else {
                            $names[] = (string) $r;
                        }
                    }
                }

                return in_array($roleName, $names, true);
            }

            if (isset($usuario->rol) && (string) $usuario->rol === $roleName) {
                return true;
            }

            if (isset($usuario->role) && (string) $usuario->role === $roleName) {
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('Error comprobando rol: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener listado de todos los aspirantes con información resumida
     */
    public function obtenerAspirantes(Request $request)
    {
        try {
            $query = User::role('Aspirante')
                ->with([
                    'municipioUsuarios.departamentoMunicipio',
                    'fotoPerfilUsuario.documentosFotoPerfil'
                ])
                ->select([
                    'id',
                    'primer_nombre',
                    'segundo_nombre',
                    'primer_apellido',
                    'segundo_apellido',
                    'tipo_identificacion',
                    'numero_identificacion',
                    'email',
                    'genero',
                    'fecha_nacimiento',
                    'estado_civil',
                    'municipio_id',
                    'aval_rectoria',
                    'aval_rectoria_by',
                    'aval_rectoria_at',
                    'aval_vicerrectoria',
                    'aval_vicerrectoria_by',
                    'aval_vicerrectoria_at',
                    'created_at'
                ]);

            // Filtros opcionales
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('primer_nombre', 'like', "%{$search}%")
                      ->orWhere('primer_apellido', 'like', "%{$search}%")
                      ->orWhere('numero_identificacion', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->has('aval_filter')) {
                $filter = $request->aval_filter;
                if ($filter === 'con_aval_rectoria') {
                    $query->where('aval_rectoria', 'Aprobado');
                } elseif ($filter === 'sin_aval_rectoria') {
                    $query->whereNull('aval_rectoria');
                } elseif ($filter === 'con_aval_vicerrectoria') {
                    $query->where('aval_vicerrectoria', 'Aprobado');
                } elseif ($filter === 'sin_aval_vicerrectoria') {
                    $query->whereNull('aval_vicerrectoria');
                }
            }

            $aspirantes = $query->orderBy('created_at', 'desc')
                               ->paginate(20);

            // Transformar datos para incluir foto de perfil
            $aspirantes->getCollection()->transform(function ($aspirante) {
                $fotoUrl = null;
                if ($aspirante->fotoPerfilUsuario && $aspirante->fotoPerfilUsuario->documentosFotoPerfil->count() > 0) {
                    $fotoUrl = asset('storage/' . $aspirante->fotoPerfilUsuario->documentosFotoPerfil->first()->archivo);
                }

                return [
                    'id' => $aspirante->id,
                    'nombre_completo' => trim("{$aspirante->primer_nombre} {$aspirante->segundo_nombre} {$aspirante->primer_apellido} {$aspirante->segundo_apellido}"),
                    'tipo_identificacion' => $aspirante->tipo_identificacion,
                    'numero_identificacion' => $aspirante->numero_identificacion,
                    'email' => $aspirante->email,
                    'genero' => $aspirante->genero,
                    'fecha_nacimiento' => $aspirante->fecha_nacimiento,
                    'estado_civil' => $aspirante->estado_civil,
                    'municipio' => $aspirante->municipioUsuarios ? $aspirante->municipioUsuarios->nombre_municipio : null,
                    'departamento' => $aspirante->municipioUsuarios && $aspirante->municipioUsuarios->departamentoMunicipio
                        ? $aspirante->municipioUsuarios->departamentoMunicipio->nombre_departamento
                        : null,
                    'foto_perfil_url' => $fotoUrl,
                    'aval_rectoria' => $aspirante->aval_rectoria,
                    'aval_rectoria_by' => $aspirante->aval_rectoria_by,
                    'aval_rectoria_at' => $aspirante->aval_rectoria_at,
                    'aval_vicerrectoria' => $aspirante->aval_vicerrectoria,
                    'aval_vicerrectoria_by' => $aspirante->aval_vicerrectoria_by,
                    'aval_vicerrectoria_at' => $aspirante->aval_vicerrectoria_at,
                    'fecha_registro' => $aspirante->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'aspirantes' => $aspirantes
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener aspirantes: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al obtener la lista de aspirantes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener información completa de un aspirante específico
     */
    public function obtenerAspirantePorId($id)
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





            // Construir URLs de foto de perfil
            $fotoUrl = null;
            if ($aspirante->fotoPerfilUsuario && $aspirante->fotoPerfilUsuario->documentosFotoPerfil->count() > 0) {
                $fotoUrl = asset('storage/' . $aspirante->fotoPerfilUsuario->documentosFotoPerfil->first()->archivo);
            }

            // Construir URLs de documentos
            $documentos = $aspirante->documentosUser->map(function($doc) {
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
                    'rectoria' => [
                        'estado' => $aspirante->aval_rectoria,
                        'aprobado_por' => $aspirante->aval_rectoria_by,
                        'fecha' => $aspirante->aval_rectoria_at,
                    ],
                    'vicerrectoria' => [
                        'estado' => $aspirante->aval_vicerrectoria,
                        'aprobado_por' => $aspirante->aval_vicerrectoria_by,
                        'fecha' => $aspirante->aval_vicerrectoria_at,
                    ],
                ],
            ];

            return response()->json(['aspirante' => $data], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener aspirante: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al obtener información del aspirante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener información limitada de un aspirante para Talento Humano
     * Devuelve solo los campos necesarios (evita exponer datos sensibles)
     */
    public function obtenerAspiranteParaTalentoHumano($id)
    {
        try {
            $aspirante = User::role('Aspirante')
                ->with([
                    'fotoPerfilUsuario.documentosFotoPerfil',
                    'informacionContactoUsuario',
                    'documentosUser'
                ])
                ->findOrFail($id);

            $fotoUrl = null;
            if ($aspirante->fotoPerfilUsuario && $aspirante->fotoPerfilUsuario->documentosFotoPerfil->count() > 0) {
                $fotoUrl = asset('storage/' . $aspirante->fotoPerfilUsuario->documentosFotoPerfil->first()->archivo);
            }

            $documentos = $aspirante->documentosUser->map(function($doc) {
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
                    'email' => $aspirante->email,
                    'foto_perfil_url' => $fotoUrl,
                ],
                'informacion_contacto' => $aspirante->informacionContactoUsuario ? [
                    'telefono' => $aspirante->informacionContactoUsuario->telefono_movil ?? null,
                    'celular' => $aspirante->informacionContactoUsuario->celular_alternativo ?? null,
                ] : null,
                'documentos' => $documentos,
                'avales' => [
                    'rectoria' => [
                        'estado' => $aspirante->aval_rectoria,
                        'fecha' => $aspirante->aval_rectoria_at,
                    ],
                    'vicerrectoria' => [
                        'estado' => $aspirante->aval_vicerrectoria,
                        'fecha' => $aspirante->aval_vicerrectoria_at,
                    ],
                ],
            ];

            return response()->json(['aspirante' => $data], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener aspirante (talento humano): ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al obtener información del aspirante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dar aval a un aspirante (Rectoria o Vicerrectoria)
     */
    public function darAval(Request $request, $id)
    {
        $request->validate([
            'tipo_aval' => 'required|in:rectoria,vicerrectoria',
            'estado' => 'required|in:Aprobado,Rechazado',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $aspirante = User::role('Aspirante')->findOrFail($id);
            $usuario = Auth::user();
            $tipoAval = $request->tipo_aval;

            // Verificar que el usuario tenga el rol correcto (comprobación robusta)
            if ($tipoAval === 'rectoria' && ! $this->userHasRole($usuario, 'Rectoria')) {
                return response()->json([
                    'mensaje' => 'No tienes permisos para dar aval de rectoría'
                ], 403);
            }

            if ($tipoAval === 'vicerrectoria' && ! $this->userHasRole($usuario, 'Vicerrectoria')) {
                return response()->json([
                    'mensaje' => 'No tienes permisos para dar aval de vicerrectoría'
                ], 403);
            }

            // Actualizar el aval correspondiente
            if ($tipoAval === 'rectoria') {
                $aspirante->aval_rectoria = $request->estado;
                $aspirante->aval_rectoria_by = $usuario->id;
                $aspirante->aval_rectoria_at = now();
            } else {
                $aspirante->aval_vicerrectoria = $request->estado;
                $aspirante->aval_vicerrectoria_by = $usuario->id;
                $aspirante->aval_vicerrectoria_at = now();
            }

            $aspirante->save();

            DB::commit();

            return response()->json([
                'mensaje' => 'Aval registrado exitosamente',
                'aspirante' => [
                    'id' => $aspirante->id,
                    'nombre_completo' => "{$aspirante->primer_nombre} {$aspirante->primer_apellido}",
                    'aval_rectoria' => $aspirante->aval_rectoria,
                    'aval_vicerrectoria' => $aspirante->aval_vicerrectoria,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al dar aval: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al registrar el aval',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar hoja de vida en PDF
     */
    public function descargarHojaDeVida($id)
    {
        try {
            $aspirante = User::role('Aspirante')->findOrFail($id);
            return $this->generadorPDFService->generar($id);

        } catch (\Exception $e) {
            Log::error('Error al generar PDF: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al generar la hoja de vida',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de aspirantes
     */
    public function obtenerEstadisticas()
    {
        try {
            $totalAspirantes = User::role('Aspirante')->count();
            $conAvalRectoria = User::role('Aspirante')->where('aval_rectoria', 'Aprobado')->count();
            $conAvalVicerrectoria = User::role('Aspirante')->where('aval_vicerrectoria', 'Aprobado')->count();
            $sinAvales = User::role('Aspirante')
                ->whereNull('aval_rectoria')
                ->whereNull('aval_vicerrectoria')
                ->count();

            return response()->json([
                'estadisticas' => [
                    'total_aspirantes' => $totalAspirantes,
                    'con_aval_rectoria' => $conAvalRectoria,
                    'con_aval_vicerrectoria' => $conAvalVicerrectoria,
                    'sin_avales' => $sinAvales,
                    'rechazados_rectoria' => User::role('Aspirante')->where('aval_rectoria', 'Rechazado')->count(),
                    'rechazados_vicerrectoria' => User::role('Aspirante')->where('aval_vicerrectoria', 'Rechazado')->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
