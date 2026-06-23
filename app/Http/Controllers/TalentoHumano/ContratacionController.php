<?php

namespace App\Http\Controllers\TalentoHumano;

use App\Http\Requests\RequestTalentoHumano\RequestContratacion\CrearContratacionRequest;
use App\Http\Requests\RequestTalentoHumano\RequestContratacion\ActualizarContratacionRequest;
use App\Models\Usuario\User;
use App\Models\TalentoHumano\Contratacion;
use App\Models\TalentoHumano\ContratacionBitacora;
use App\Models\TalentoHumano\Convocatoria;
use App\Models\TalentoHumano\ConvocatoriaAval;
use App\Constants\ConstTalentoHumano\Aprobaciones;
use App\Constants\ConstTalentoHumano\TipoProceso;
use App\Constants\ConstTalentoHumano\TipoVinculacion;
use App\Services\AprobarDocumentosService;
use App\Services\RevertirDocumentosService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContratacionController
{
    protected $aprobarDocumentosService;
    protected $revertirDocumentosService;

    /**
     * Constructor del controlador.
     */
    public function __construct(AprobarDocumentosService $aprobarDocumentosService, RevertirDocumentosService $revertirDocumentosService)
    {
        $this->aprobarDocumentosService = $aprobarDocumentosService;
        $this->revertirDocumentosService = $revertirDocumentosService;
    }

    private function normalizarAval(string $aval): string
    {
        return match ($aval) {
            'Talento Humano', 'talento humano', 'talento_humano' => 'talento_humano',
            'Coordinador', 'Coordinación', 'coordinacion', 'coordinador' => 'coordinador',
            'Vicerrectoría', 'Vicerrectoria', 'vicerrectoria' => 'vicerrectoria',
            'Rectoría', 'Rectoria', 'rectoria' => 'rectoria',
            default => $aval,
        };
    }

    private function avalAliases(string $aval): array
    {
        return match ($aval) {
            'talento_humano' => ['talento_humano', 'Talento Humano', 'talento humano'],
            'coordinador'    => ['coordinador', 'Coordinador', 'Coordinación', 'coordinacion'],
            'vicerrectoria'  => ['vicerrectoria', 'Vicerrectoria', 'Vicerrectoría'],
            'rectoria'       => ['rectoria', 'Rectoria', 'Rectoría'],
            default          => [$aval],
        };
    }

    /**
     * Crear una contratación para un usuario y asignarle el rol de docente.
     */
    public function crearContratacion(CrearContratacionRequest $request, $user_id)
    {
        try {
            $contratacionCreada = null;

            DB::transaction(function () use ($request, $user_id, &$contratacionCreada) {
                $datosContratacion = $request->validated();

                // Verificar avales de convocatoria si aplica
                if (!empty($datosContratacion['convocatoria_id'])) {
                    $conv = Convocatoria::find($datosContratacion['convocatoria_id']);
                    if ($conv && !empty($conv->avales_establecidos)) {
                        $faltantes = [];
                        foreach ($conv->avales_establecidos as $avalRequerido) {
                            $avalNormalizado = $this->normalizarAval((string) $avalRequerido);
                            $aprobado = ConvocatoriaAval::where('convocatoria_id', $conv->id_convocatoria)
                                ->where('user_id', $user_id)
                                ->whereIn('aval', $this->avalAliases($avalNormalizado))
                                ->where('estado', 'aprobado')
                                ->exists();
                            if (!$aprobado) $faltantes[] = $avalNormalizado;
                        }
                        if (!empty($faltantes)) {
                            throw new \Exception('Faltan avales necesarios: ' . implode(', ', $faltantes), 403);
                        }
                    }

                    $yaExiste = Contratacion::where('user_id', $user_id)
                        ->where('convocatoria_id', $datosContratacion['convocatoria_id'])
                        ->exists();

                    if ($yaExiste) {
                        throw new \Exception('Ya existe una contratación para este usuario y convocatoria.', 409);
                    }
                }

                $datosContratacion['user_id']         = $user_id;
                $datosContratacion['tipo_proceso']     = $datosContratacion['tipo_proceso']     ?? TipoProceso::CONTRATACION;
                $datosContratacion['tipo_vinculacion'] = $datosContratacion['tipo_vinculacion'] ?? TipoVinculacion::DOCENTE;

                $usuario = User::findOrFail($user_id);

                $contratacionCreada = Contratacion::create($datosContratacion);

                if ($datosContratacion['tipo_proceso'] === TipoProceso::CONTRATACION) {
                    $nuevoRol = $datosContratacion['tipo_vinculacion'] === TipoVinculacion::ADMINISTRATIVO
                        ? 'Administrativo'
                        : 'Docente';
                    $usuario->syncRoles([$nuevoRol]);

                    if ($nuevoRol === 'Docente') {
                        $this->aprobarDocumentosService->aprobarDocumentosDeUsuario($usuario);
                    }
                }

                ContratacionBitacora::create([
                    'contratacion_id'   => $contratacionCreada->id_contratacion,
                    'user_modifico_id'  => Auth::id(),
                    'tipo_modificacion' => 'creacion',
                    'datos_anteriores'  => null,
                    'datos_nuevos'      => $contratacionCreada->toArray(),
                    'motivo'            => null,
                ]);
            });

            try {
                $usuario = User::find($user_id);
                if ($usuario) {
                    NotificacionController::nuevaContratacion($usuario);
                }
            } catch (\Exception $notifEx) {
                Log::error('Error al notificar nueva contratación: ' . $notifEx->getMessage());
            }

            $tipoProceso = $request->validated()['tipo_proceso'] ?? TipoProceso::CONTRATACION;
            return response()->json([
                'message' => 'Contratación registrada correctamente. Proceso: ' . $tipoProceso,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error',
                'error'   => $e->getMessage()
            ], is_numeric($e->getCode()) && $e->getCode() >= 400 ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Actualizar una contratación existente.
     */
    public function actualizarContratacion(ActualizarContratacionRequest $request, $id_contratacion)
    {
        try {
            DB::transaction(function () use ($request, $id_contratacion) {
                $contratacion = Contratacion::findOrFail($id_contratacion);

                $datosAnteriores = $contratacion->toArray();

                $datosActualizar = $request->validated();
                $motivo          = $datosActualizar['motivo'];
                unset($datosActualizar['motivo']);

                $contratacion->update($datosActualizar);

                ContratacionBitacora::create([
                    'contratacion_id'   => $contratacion->id_contratacion,
                    'user_modifico_id'  => Auth::id(),
                    'tipo_modificacion' => 'actualizacion',
                    'datos_anteriores'  => $datosAnteriores,
                    'datos_nuevos'      => $contratacion->fresh()->toArray(),
                    'motivo'            => $motivo,
                ]);
            });

            return response()->json([
                'message' => 'Contratación actualizada correctamente.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la contratación.',
                'error'   => $e->getMessage()
            ], is_numeric($e->getCode()) && $e->getCode() >= 400 ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Eliminar una contratación y revertir el estado del usuario.
     */
    public function eliminarContratacion($id)
    {
        $motivo = request()->input('motivo');
        if (empty($motivo) || strlen(trim($motivo)) < 5) {
            return response()->json([
                'message' => 'Debe indicar el motivo de la eliminación (mínimo 5 caracteres) por cumplimiento legal.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($id, $motivo) {
                $contratacion = Contratacion::findOrFail($id);
                $usuario      = $contratacion->usuarioContratacion;

                $datosAnteriores = $contratacion->toArray();

                ContratacionBitacora::create([
                    'contratacion_id'   => $contratacion->id_contratacion,
                    'user_modifico_id'  => Auth::id(),
                    'tipo_modificacion' => 'eliminacion',
                    'datos_anteriores'  => $datosAnteriores,
                    'datos_nuevos'      => null,
                    'motivo'            => $motivo,
                ]);

                $contratacion->delete();

                if ($usuario) {
                    $tieneOtrosContratos = Contratacion::where('user_id', $usuario->id)->exists();
                    if (!$tieneOtrosContratos) {
                        $usuario->syncRoles(['Aspirante']);
                        $this->revertirDocumentosService->revertirDocumentosDeUsuario($usuario);
                    }
                }
            });

            return response()->json([
                'message' => 'Contratación eliminada. El usuario ha sido revertido a Aspirante si no tenía más contratos.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la contratación.',
                'error'   => $e->getMessage()
            ], is_numeric($e->getCode()) && $e->getCode() >= 400 ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Obtener la bitácora de cambios de un contrato específico.
     */
    public function obtenerBitacora($id_contratacion)
    {
        try {
            $bitacora = ContratacionBitacora::with([
                'usuarioQueModifico:id,primer_nombre,primer_apellido,email',
            ])
                ->where('contratacion_id', $id_contratacion)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'message'  => 'Bitácora obtenida correctamente.',
                'bitacora' => $bitacora,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la bitácora.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todas las contrataciones registradas.
     */
    public function obtenerTodasLasContrataciones()
    {
        try {
            $contrataciones = Contratacion::with('UsuarioContratacion')
                ->orderBy('fecha_inicio', 'desc')
                ->get();

            return response()->json([
                'message' => 'Contrataciones obtenidas correctamente.',
                'contrataciones' => $contrataciones
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las contrataciones.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una contratación específica por su ID.
     */
    public function obtenerContratacionPorId($id_contratacion)
    {
        try {
            $contratacion = Contratacion::with('UsuarioContratacion')
                ->findOrFail($id_contratacion);

            return response()->json([
                'message' => 'Información de contratación obtenida correctamente.',
                'contratacion' => $contratacion
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la información de la contratación.',
                'error' => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Obtener las contrataciones del usuario autenticado.
     */
    public function obtenerContratacionUsuario()
    {
        try {
            $usuario = Auth::user();

            $contrataciones = Contratacion::where('user_id', $usuario->id)
                ->orderBy('fecha_inicio', 'desc')
                ->get();

            if ($contrataciones->isEmpty()) {
                throw new \Exception('No se encontraron contrataciones para el usuario autenticado.', 404);
            }

            return response()->json([
                'message' => 'Contrataciones del usuario autenticado obtenidas correctamente.',
                'contrataciones' => $contrataciones
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las contrataciones del usuario autenticado.',
                'error' => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }
}
