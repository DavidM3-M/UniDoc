<?php

namespace App\Http\Controllers\TalentoHumano;

use App\Http\Requests\RequestTalentoHumano\RequestContratacion\CrearContratacionRequest;
use App\Http\Requests\RequestTalentoHumano\RequestContratacion\ActualizarContratacionRequest;
use App\Models\Usuario\User;
use App\Models\TalentoHumano\Contratacion;
use App\Models\TalentoHumano\ContratacionBitacora;
use App\Models\TalentoHumano\Convocatoria;
use App\Models\TalentoHumano\ConvocatoriaAval;
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
     *
     * Inyecta los servicios encargados de la aprobación y reversión de documentos.
     * - `AprobarDocumentosService`: se utiliza para aprobar documentos académicos u otros tipos relacionados.
     * - `RevertirDocumentosService`: se encarga de revertir el estado de los documentos previamente aprobados o rechazados.
     *
     * @param AprobarDocumentosService $aprobarDocumentosService Servicio para aprobar documentos.
     * @param RevertirDocumentosService $revertirDocumentosService Servicio para revertir documentos.
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
     *
     * Este método registra una contratación para un usuario específico, siempre y cuando no tenga
     * una contratación existente. La operación se realiza dentro de una transacción para garantizar
     * la consistencia de los datos. Además, se actualiza el rol del usuario a "Docente" y se aprueban
     * automáticamente todos sus documentos mediante el servicio `AprobarDocumentosService`.
     * En caso de que ya exista una contratación o se produzca un error durante el proceso, se lanza
     * una excepción con el código correspondiente.
     *
     * @param CrearContratacionRequest $request Solicitud validada con los datos de contratación.
     * @param int $user_id ID del usuario al que se le va a crear la contratación.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
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
                }

                $datosContratacion['user_id']       = $user_id;
                $datosContratacion['tipo_proceso']   = $datosContratacion['tipo_proceso']   ?? TipoProceso::CONTRATACION;
                $datosContratacion['tipo_vinculacion'] = $datosContratacion['tipo_vinculacion'] ?? TipoVinculacion::DOCENTE;

                $usuario = User::findOrFail($user_id);

                // La doble contratación (ascenso, cambio de cargo o segundo contrato) está permitida.
                // Solo en la primera contratación (tipo_proceso = 'Contratacion') se cambia el rol.
                $contratacionCreada = Contratacion::create($datosContratacion);

                if ($datosContratacion['tipo_proceso'] === TipoProceso::CONTRATACION) {
                    // Asignar rol según tipo de vinculación indicado por Talento Humano
                    $nuevoRol = $datosContratacion['tipo_vinculacion'] === TipoVinculacion::ADMINISTRATIVO
                        ? 'Administrativo'
                        : 'Docente';
                    $usuario->syncRoles([$nuevoRol]);

                    // Solo se aprueban documentos académicos para docentes
                    if ($nuevoRol === 'Docente') {
                        $this->aprobarDocumentosService->aprobarDocumentosDeUsuario($usuario);
                    }
                }

                // Registrar en bitácora legal
                ContratacionBitacora::create([
                    'contratacion_id'  => $contratacionCreada->id_contratacion,
                    'user_modifico_id' => Auth::id(),
                    'tipo_modificacion' => 'creacion',
                    'datos_anteriores' => null,
                    'datos_nuevos'     => $contratacionCreada->toArray(),
                    'motivo'           => null,
                ]);
            });

            // Notificar al usuario que ha sido contratado
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
     *
     * Este método permite modificar los datos de una contratación ya registrada, identificada por su ID.
     * La operación se realiza dentro de una transacción para garantizar la consistencia de los datos durante la actualización.
     * En caso de que no se encuentre la contratación o se produzca un error durante el proceso,
     * se captura la excepción y se retorna una respuesta con el mensaje de error correspondiente.
     *
     * @param ActualizarContratacionRequest $request Solicitud validada con los nuevos datos de la contratación.
     * @param int $id_contratacion ID de la contratación que se desea actualizar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function actualizarContratacion(ActualizarContratacionRequest $request, $id_contratacion)
    {
        try {
            DB::transaction(function () use ($request, $id_contratacion) {
                $contratacion = Contratacion::findOrFail($id_contratacion);

                // Capturar snapshot antes del cambio para bitácora legal
                $datosAnteriores = $contratacion->toArray();

                $datosActualizar = $request->validated();
                $motivo          = $datosActualizar['motivo'];
                unset($datosActualizar['motivo']); // No persiste en la tabla de contratos

                $contratacion->update($datosActualizar);

                // Registrar modificación en bitácora legal
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
     *
     * Este método elimina una contratación existente identificada por su ID.
     * Una vez eliminada, si el usuario asociado existe, su rol se revierte a "Aspirante"
     * y todos sus documentos aprobados o gestionados previamente son revertidos
     * mediante el servicio `RevertirDocumentosService`.
     * La operación se realiza dentro de una transacción para asegurar la coherencia del sistema.
     * En caso de error, se captura la excepción y se retorna un mensaje adecuado.
     *
     * @param int $id ID de la contratación que se desea eliminar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function eliminarContratacion($id)
    {
        // El motivo de eliminación se requiere como query param para trazabilidad legal
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

                // Snapshot antes de eliminar
                $datosAnteriores = $contratacion->toArray();

                // Registrar en bitácora ANTES de eliminar (para mantener la FK válida)
                ContratacionBitacora::create([
                    'contratacion_id'   => $contratacion->id_contratacion,
                    'user_modifico_id'  => Auth::id(),
                    'tipo_modificacion' => 'eliminacion',
                    'datos_anteriores'  => $datosAnteriores,
                    'datos_nuevos'      => null,
                    'motivo'            => $motivo,
                ]);

                $contratacion->delete();

                // Solo revertir rol si el usuario ya no tiene más contratos activos
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
     * Incluye quién modificó, cuándo, el tipo de operación y el motivo.
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
     *
     * Este método recupera todas las contrataciones almacenadas en la base de datos,
     * incluyendo la información del usuario relacionado con cada una de ellas.
     * Las contrataciones se ordenan de forma descendente según su fecha de inicio.
     * En caso de error durante la consulta, se captura una excepción y se retorna una respuesta con el mensaje correspondiente.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de contrataciones o mensaje de error.
     */
    public function obtenerTodasLasContrataciones()
    {
        try {
            $contrataciones = Contratacion::with('UsuarioContratacion') // obtener las contrataciones
                ->orderBy('fecha_inicio', 'desc') // ordenar por fecha de inicio
                ->get();

            return response()->json([ // Respuesta exitosa
                'message' => 'Contrataciones obtenidas correctamente.',
                'contrataciones' => $contrataciones
            ], 200);
        } catch (\Exception $e) { // Manejo de excepciones
            return response()->json([
                'message' => 'Error al obtener las contrataciones.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una contratación específica por su ID.
     *
     * Este método busca y devuelve la información de una contratación determinada,
     * incluyendo los datos del usuario relacionado mediante la relación `UsuarioContratacion`.
     * Si la contratación no existe, se lanza una excepción y se responde con un mensaje de error adecuado.
     *
     * @param int $id_contratacion ID de la contratación que se desea consultar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los datos de la contratación o mensaje de error.
     */
    public function obtenerContratacionPorId($id_contratacion)
    {
        try {

            $contratacion = Contratacion::with('UsuarioContratacion') // Si tienes relación con el modelo User
                ->findOrFail($id_contratacion); // Buscar la contratación por su ID

            return response()->json([ // Respuesta exitosa
                'message' => 'Información de contratación obtenida correctamente.',
                'contratacion' => $contratacion
            ], 200);
        } catch (\Exception $e) { // Manejo de excepciones
            return response()->json([
                'message' => 'Error al obtener la información de la contratación.',
                'error' => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }



    // public function obtenerContratacionesPorUsuario($user_id)
    // {
    //     try {
    //         $contrataciones = Contratacion::where('user_id', $user_id)
    //             ->orderBy('fecha_inicio', 'desc')
    //             ->get();

    //         if ($contrataciones->isEmpty()) {
    //             return response()->json([
    //                 'message' => 'No se encontraron contrataciones para este usuario.'
    //             ], 404);
    //         }

    //         return response()->json([
    //             'message' => 'Contrataciones obtenidas correctamente.',
    //             'contrataciones' => $contrataciones
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Error al obtener las contrataciones del usuario.',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    /**
     * Obtener las contrataciones del usuario autenticado.
     *
     * Este método consulta y retorna todas las contrataciones asociadas al usuario actualmente autenticado,
     * ordenadas por fecha de inicio en orden descendente. Si el usuario no tiene contrataciones registradas,
     * se lanza una excepción con código 404. En caso de error durante el proceso de consulta, se captura
     * la excepción y se retorna una respuesta con el mensaje correspondiente.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las contrataciones del usuario o mensaje de error.
     */
    public function obtenerContratacionUsuario()
    {
        try {
            $usuario = Auth::user(); // Obtener el usuario autenticado

            $contrataciones = Contratacion::where('user_id', $usuario->id) // Filtrar por el ID del usuario autenticado
                ->orderBy('fecha_inicio', 'desc') // Ordenar por fecha de inicio
                ->get();

            if ($contrataciones->isEmpty()) {
                throw new  \Exception('No se encontraron contrataciones para el usuario autenticado.', 404);
            }

            return response()->json([
                'message' => 'Contrataciones del usuario autenticado obtenidas correctamente.',
                'contrataciones' => $contrataciones
            ], 200);
        } catch (\Exception $e) { // Manejo de excepciones
            return response()->json([
                'message' => 'Error al obtener las contrataciones del usuario autenticado.',
                'error' => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }
}
