<?php

namespace App\Http\Controllers\TalentoHumano;

use App\Constants\ConstAgregarExperiencia\TiposExperiencia;
use App\Constants\ConstAgregarIdioma\NivelIdioma;
use App\Constants\ConstTalentoHumano\PerfilesProfesionales\PerfilesProfesionales;
use App\Constants\ConstAgregarEstudio\TiposEstudio;
use App\Constants\ConstTalentoHumano\EstadoPostulacion;
use App\Models\TalentoHumano\Postulacion;
use App\Models\TalentoHumano\Convocatoria;
use App\Models\TalentoHumano\ConvocatoriaAval;
use App\Models\Usuario\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\GeneradorHojaDeVidaPDFService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Http\Controllers\TalentoHumano\NotificacionController;
use App\Services\PuntajeAspiranteService;
use App\Constants\ConstTalentoHumano\Aprobaciones;

class PostulacionController
{
    protected $generadorHojaDeVidaPDFService;
    protected $puntajeService;

    public function __construct(
        GeneradorHojaDeVidaPDFService $generadorHojaDeVidaPDFService,
        PuntajeAspiranteService $puntajeService
    ) {
        $this->generadorHojaDeVidaPDFService = $generadorHojaDeVidaPDFService;
        $this->puntajeService = $puntajeService;
    }

    /**
     * Crear una postulaci├│n del usuario autenticado a una convocatoria.
     *
     * Este m├®todo permite que un usuario autenticado se postule a una convocatoria espec├¡fica.
     * La operaci├│n se ejecuta dentro de una transacci├│n para garantizar la integridad de los datos.
     * Se valida que:
     * - La convocatoria exista.
     * - La convocatoria est├® abierta (no cerrada).
     * - El usuario no se haya postulado previamente a la misma convocatoria.
     *
     * Si esta correcto, se registra la postulaci├│n con estado inicial "Enviada".
     * En caso de errores (convocatoria cerrada, duplicidad de postulaci├│n u otros),
     * se lanza una excepci├│n y se retorna una respuesta con el mensaje adecuado.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @param int $convocatoriaId ID de la convocatoria a la que el usuario desea postularse.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de ├®xito o mensaje de error.
     */
    public function crearPostulacion(Request $request, $convocatoriaId)
    {
        try {
            $convocatoria = Convocatoria::findOrFail($convocatoriaId);
            // Verificar si la convocatoria est├í cerrada
            if ($convocatoria->estado_convocatoria === 'Cerrada') {
                return response()->json([
                    'mensaje' => 'Esta convocatoria ya est├í cerrada'
                ], 403);
            }

            // Verificar si la fecha de cierre ya pas├│
            if (now()->greaterThan($convocatoria->fecha_cierre)) {
                return response()->json([
                    'mensaje' => 'La fecha de cierre de esta convocatoria ya ha pasado'
                ], 403);
            }

            DB::transaction(function () use ($request, $convocatoriaId) { // Validar el ID de la convocatoria
                $user = $request->user()->load(['experienciasUsuario', 'estudiosUsuario', 'idiomasUsuario', 'facultades']); // Obtener el usuario autenticado con todas las relaciones necesarias

                $convocatoria = Convocatoria::with(['tipoCargo', 'experienciaRequerida', 'perfilProfesional', 'facultad'])->findOrFail($convocatoriaId); // Verificar si la convocatoria existe

                if ($convocatoria->estado_convocatoria === 'Cerrada') { // Verificar si la convocatoria est├í cerrada
                    throw new \Exception('Esta convocatoria est├í cerrada y no admite m├ís postulaciones.', 403); // Lanzar excepci├│n si la convocatoria est├í cerrada
                }

                $existe = Postulacion::where('user_id', $user->id) // Verificar si el usuario ya est├í postulado
                    ->where('convocatoria_id', $convocatoriaId)
                    ->exists();

                if ($existe) {
                    throw new \Exception('Ya te has postulado a esta convocatoria', 409);
                }

                // Verificar requisitos de la convocatoria
                $this->verificarRequisitosConvocatoria($user, $convocatoria);

                Postulacion::create([ // Crear la postulaci├│n
                    'user_id' => $user->id,
                    'convocatoria_id' => $convocatoriaId,
                    'estado_postulacion' => 'Enviada'
                ]);

                // Crear registros de avales pendientes para este postulante si la convocatoria los requiere
                if (!empty($convocatoria->avales_establecidos) && is_array($convocatoria->avales_establecidos)) {
                    foreach ($convocatoria->avales_establecidos as $avalRequerido) {
                        $avalClave = Aprobaciones::toDatabaseKey($avalRequerido) ?? $avalRequerido;

                        ConvocatoriaAval::updateOrCreate(
                            [
                                'convocatoria_id' => $convocatoriaId,
                                'user_id' => $user->id,
                                'aval' => $avalClave,
                            ],
                            [
                                'estado' => 'pending'
                            ]
                        );
                    }
                }
            });

            // Notificar a los administradores de Talento Humano sobre la nueva postulaci├│n
            try {
                $admins = User::role('Talento Humano')->get();
                if ($admins->isNotEmpty()) {
                    NotificacionController::nuevaPostulacion($admins, $request->user());
                }
            } catch (\Exception $notifEx) {
                Log::error('Error al notificar nueva postulaci├│n: ' . $notifEx->getMessage());
            }

            // Confirmar al postulante que su postulaci├│n fue recibida
            try {
                NotificacionController::confirmacionPostulacion($request->user(), $convocatoria);
            } catch (\Throwable $notifEx) {
                Log::error('Error al confirmar postulaci├│n al postulante: ' . $notifEx->getMessage());
            }

            return response()->json([ // Crear la respuesta JSON
                'message' => 'Postulaci├│n enviada correctamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurri├│ un error al crear la postulaci├│n.',
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Obtener todas las postulaciones registradas en el sistema.
     *
     * Este m├®todo recupera todas las postulaciones realizadas por los usuarios, incluyendo
     * la informaci├│n del usuario postulante (`usuarioPostulacion`) y de la convocatoria
     * correspondiente (`convocatoriaPostulacion`). Las postulaciones se ordenan de forma
     * descendente seg├║n su fecha de creaci├│n.
     * En caso de producirse un error durante la consulta, se captura la excepci├│n y se
     * retorna una respuesta adecuada con el mensaje de error.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de postulaciones o mensaje de error.
     */
    public function obtenerPostulaciones()
    {
        try {
            $postulaciones = Postulacion::with('usuarioPostulacion', 'convocatoriaPostulacion')
                ->orderBy('created_at', 'desc')
                ->get();

            $puntajeService = $this->puntajeService;
            $postulaciones->each(function ($p) use ($puntajeService) {
                $avales = ConvocatoriaAval::where('convocatoria_id', $p->convocatoria_id)
                    ->where('user_id', $p->user_id)
                    ->get();

                $estaAprobado = function (array $nombres) use ($avales): bool {
                    return $avales->contains(function ($a) use ($nombres) {
                        return in_array($a->aval, $nombres) && $a->estado === 'aprobado';
                    });
                };

                // Asignar directamente a la postulaci├│n, NO a usuarioPostulacion
                $p->aval_talento_humano = $estaAprobado(['talento_humano', 'Talento Humano', 'talento humano']);
                $p->aval_coordinador = $estaAprobado(['coordinador', 'Coordinador', 'Coordinaci├│n', 'coordinacion']);
                $p->aval_vicerrectoria = $estaAprobado(['vicerrectoria', 'Vicerrectoria', 'Vicerrector├¡a']);
                $p->aval_rectoria = $estaAprobado(['rectoria', 'Rectoria', 'Rector├¡a']);

                if ($p->usuarioPostulacion) {
                    // Esto s├¡ es seguro: el puntaje es del usuario, no depende de la convocatoria
                    $p->usuarioPostulacion->puntaje_aspirante = $puntajeService->calcular((int) $p->user_id)['total'];
                }
            });

            return response()->json(['postulaciones' => $postulaciones], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurri├│ un error al obtener las postulaciones.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener las postulaciones asociadas a una convocatoria espec├¡fica.
     *
     * Este m├®todo recupera todas las postulaciones realizadas a una convocatoria determinada,
     * identificada por su ID. Cada postulaci├│n incluye la informaci├│n del usuario postulante
     * gracias a la relaci├│n `usuarioPostulacion`.
     * En caso de error durante la consulta, se captura una excepci├│n y se retorna una respuesta adecuada.
     *
     * @param int $idConvocatoria ID de la convocatoria cuyas postulaciones se desean consultar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de postulaciones o mensaje de error.
     */
    // public function obtenerPorConvocatoria($idConvocatoria)
    // {
    //     try {
    //         $postulaciones = Postulacion::where('convocatoria_id', $idConvocatoria) // Obtener las postulaciones por ID de convocatoria
    //             ->with('usuarioPostulacion') // Incluir la relaci├│n con el usuario postulante
    //             ->get();

    //         return response()->json(['postulaciones' => $postulaciones], 200); // Retornar las postulaciones en formato JSON

    //     } catch (\Exception $e) { // Manejar excepciones
    //         return response()->json([
    //             'message' => 'Ocurri├│ un error al obtener las postulaciones por convocatoria.', // Retornar un mensaje de error
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * Obtener las postulaciones del usuario autenticado.
     *
     * Este m├®todo recupera todas las postulaciones realizadas por el usuario que ha iniciado sesi├│n.
     * Cada postulaci├│n incluye la informaci├│n relacionada con la convocatoria a la que se postul├│,
     * gracias a la relaci├│n `convocatoriaPostulacion`.
     * En caso de error durante la consulta, se captura una excepci├│n y se retorna una respuesta adecuada.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de postulaciones del usuario o mensaje de error.
     */
    public function obtenerPostulacionesUsuario(Request $request)
    {
        try {
            $postulaciones = Postulacion::where('user_id', $request->user()->id) // Obtener las postulaciones del usuario autenticado
                ->with('convocatoriaPostulacion') // Incluir la relaci├│n con la convocatoria
                ->get();

            return response()->json(['postulaciones' => $postulaciones], 200); // Retornar las postulaciones en formato JSON

        } catch (\Exception $e) { // Manejar excepciones
            return response()->json([ // Retornar un mensaje de error
                'message' => 'Ocurri├│ un error al obtener las postulaciones del usuario.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar la hoja de vida en PDF de un usuario postulado a una convocatoria espec├¡fica.
     *
     * Este m├®todo verifica que el usuario est├® postulado a la convocatoria indicada. Si la postulaci├│n existe,
     * se utiliza el servicio `GeneradorHojaDeVidaPDFService` para generar el PDF de la hoja de vida.
     * Si el usuario no est├í postulado a la convocatoria, se retorna una respuesta con c├│digo 404.
     * En caso de error durante el proceso, se captura la excepci├│n y se responde con un mensaje adecuado.
     *
     * @param int $idConvocatoria ID de la convocatoria.
     * @param int $idUsuario ID del usuario cuya hoja de vida se desea generar.
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     * Respuesta JSON con mensaje de error o archivo PDF generado exitosamente.
     */
    public function generarHojaDeVidaPDF($idConvocatoria, $idUsuario)
    {
        try {
            $postulacion = Postulacion::where('convocatoria_id', $idConvocatoria) // Obtener la postulaci├│n del usuario a la convocatoria
                ->where('user_id', $idUsuario) // Verificar que el usuario est├® postulado a la convocatoria
                ->first();

            if (!$postulacion) {
                return response()->json([ // Retornar un mensaje de error si el usuario no est├í postulado
                    'message' => 'El usuario no est├í postulado a esta convocatoria.'
                ], 404);
            }

            return $this->generadorHojaDeVidaPDFService->generar($idUsuario); // Generar la hoja de vida en PDF

        } catch (\Exception $e) { // Manejar excepciones
            return response()->json([
                'message' => 'Ocurri├│ un error al generar la hoja de vida.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar el estado de una postulaci├│n.
     *
     * Este m├®todo permite modificar el estado de una postulaci├│n espec├¡fica, validando primero que el nuevo estado
     * est├® dentro de los valores definidos en la enumeraci├│n `EstadoPostulacion`.
     * La operaci├│n se realiza dentro de una transacci├│n para asegurar la integridad de los datos.
     * Si la postulaci├│n no existe, se lanza una excepci├│n con c├│digo 404.
     * En caso de error durante la validaci├│n o actualizaci├│n, se captura la excepci├│n y se retorna una respuesta adecuada.
     *
     * @param Request $request Solicitud HTTP que contiene el nuevo estado de la postulaci├│n.
     * @param int $idPostulacion ID de la postulaci├│n que se desea actualizar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de ├®xito o mensaje de error.
     */
    public function actualizarEstadoPostulacion(Request $request, $idPostulacion)
    {
        try {
            $request->validate([
                'estado_postulacion' => ['required', 'string', Rule::in(EstadoPostulacion::all())],
                'motivo_rechazo' => ['nullable', 'string', 'max:1000'],
            ]);

            // El motivo es obligatorio cuando se rechaza la postulaci├│n
            if ($request->estado_postulacion === EstadoPostulacion::RECHAZADA && empty($request->motivo_rechazo)) {
                return response()->json([
                    'message' => 'El motivo de rechazo es obligatorio cuando se rechaza una postulaci├│n.',
                ], 422);
            }

            DB::transaction(function () use ($request, $idPostulacion) {
                $postulacion = Postulacion::find($idPostulacion);

                if (!$postulacion) {
                    throw new \Exception('No se encontr├│ una postulaci├│n.', 404);
                }

                $postulacion->estado_postulacion = $request->estado_postulacion;

                if ($request->estado_postulacion === EstadoPostulacion::RECHAZADA) {
                    $postulacion->motivo_rechazo = $request->motivo_rechazo;
                    $postulacion->rechazado_por = $request->user()->getRoleNames()->first() ?? 'Talento Humano';
                } else {
                    // Limpiar el motivo si el estado vuelve a uno no rechazado
                    $postulacion->motivo_rechazo = null;
                    $postulacion->rechazado_por = null;
                }

                $postulacion->save();
            });

            // Notificar al usuario postulante sobre el cambio de estado
            try {
                $postulacion = Postulacion::with('usuarioPostulacion')->find($idPostulacion);
                if ($postulacion && $postulacion->usuarioPostulacion) {
                    if ($request->estado_postulacion === EstadoPostulacion::RECHAZADA) {
                        NotificacionController::postulacionRechazada(
                            $postulacion->usuarioPostulacion,
                            $request->motivo_rechazo,
                            $postulacion->rechazado_por
                        );
                    } else {
                        NotificacionController::cambioEstadoPostulacion(
                            $postulacion->usuarioPostulacion,
                            $request->estado_postulacion
                        );
                    }
                }
            } catch (\Exception $notifEx) {
                Log::error('Error al notificar cambio de estado de postulaci├│n: ' . $notifEx->getMessage());
            }

            return response()->json([
                'message' => 'Estado de postulaci├│n actualizado correctamente.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurri├│ un error al actualizar el estado de la postulaci├│n.',
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Eliminar una postulaci├│n espec├¡fica.
     *
     * Este m├®todo permite eliminar una postulaci├│n del sistema, identificada por su ID.
     * La operaci├│n se ejecuta dentro de una transacci├│n para asegurar la integridad de los datos.
     * Si la postulaci├│n no existe, se lanza una excepci├│n con c├│digo 404.
     * En caso de ocurrir un error durante el proceso de eliminaci├│n, se captura la excepci├│n
     * y se retorna una respuesta con el mensaje de error correspondiente.
     *
     * @param int $idPostulacion ID de la postulaci├│n que se desea eliminar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de ├®xito o mensaje de error.
     */
    public function eliminarPostulacion($idPostulacion)
    {
        try {
            DB::transaction(function () use ($idPostulacion) { // Iniciar una transacci├│n para garantizar la integridad de los datos
                $postulacion = Postulacion::find($idPostulacion); // Buscar la postulaci├│n por su ID

                if (!$postulacion) { // Verificar si la postulaci├│n existe
                    throw new \Exception('Postulaci├│n no encontrada.', 404);
                }

                $postulacion->delete(); // Eliminar la postulaci├│n
            });

            return response()->json([ // Retornar un mensaje de ├®xito
                'message' => 'Postulaci├│n eliminada correctamente.'
            ]);

        } catch (\Exception $e) { // Manejar excepciones
            return response()->json([ // Retornar un mensaje de error
                'message' => 'Ocurri├│ un error al eliminar la postulaci├│n.',
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Eliminar una postulaci├│n realizada por el usuario autenticado.
     *
     * Este m├®todo permite que un usuario elimine su propia postulaci├│n, identificada por su ID.
     * Se valida que la postulaci├│n exista y que pertenezca al usuario autenticado para evitar accesos no autorizados.
     * Si la validaci├│n es exitosa, se elimina la postulaci├│n. En caso contrario, se lanza una excepci├│n con el c├│digo correspondiente.
     * Si ocurre cualquier error durante el proceso, se retorna una respuesta con el mensaje adecuado.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @param int $id ID de la postulaci├│n que se desea eliminar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de ├®xito o mensaje de error.
     */
    public function eliminarPostulacionUsuario(Request $request, $id)
    {
        try {
            $postulacion = Postulacion::find($id); // Buscar la postulaci├│n por su ID

            if (!$postulacion) {
                throw new \Exception('Postulaci├│n no encontrada.', 404);
            }

            if ($postulacion->user_id !== $request->user()->id) { // Verificar si el usuario autenticado es el propietario de la postulaci├│n
                throw new \Exception('No tienes permiso para eliminar esta postulaci├│n.', 403);
            }

            $postulacion->delete(); // Eliminar la postulaci├│n

            return response()->json([ // Retornar un mensaje de ├®xito
                'message' => 'Postulaci├│n eliminada correctamente.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurri├│ un error al eliminar la postulaci├│n del usuario.',
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }
    /**
     * Generar la hoja de vida en PDF de un usuario (para Rector├¡a/Vicerrector├¡a).
     *
     * Este m├®todo genera el PDF de la hoja de vida de un usuario sin necesidad de verificar
     * una postulaci├│n espec├¡fica. Es utilizado por roles administrativos como Rector├¡a.
     *
     * @param int $idUsuario ID del usuario cuya hoja de vida se desea generar.
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     * Respuesta JSON con mensaje de error o archivo PDF generado exitosamente.
     */
    public function generarHojaDeVidaPDFSimple($idUsuario)
    {
        try {
            return $this->generadorHojaDeVidaPDFService->generar($idUsuario);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurri├│ un error al generar la hoja de vida.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar si el usuario cumple con los requisitos de la convocatoria.
     *
     * @param \App\Models\Usuario\User $user
     * @param \App\Models\TalentoHumano\Convocatoria $convocatoria
     * @throws \Exception
     */
    private function verificarRequisitosConvocatoria($user, $convocatoria)
    {
        // Verificar experiencia requerida general
        if ($convocatoria->experienciaRequerida) {
            $experienciaRequerida = $convocatoria->experienciaRequerida;
            $esAdministrativo = $convocatoria->tipoCargo ? $convocatoria->tipoCargo->es_administrativo : false;

            // Calcular experiencia total del usuario
            $experienciaTotalHoras = $this->calcularExperienciaUsuario($user, $esAdministrativo);

            if ($experienciaTotalHoras < $experienciaRequerida->horas_minimas) {
                $anosRequeridos = $experienciaRequerida->anos_equivalentes
                    ? number_format($experienciaRequerida->anos_equivalentes, 1)
                    : null;
                $totalDiasUsuario = $this->calcularTotalDiasExperiencia($user->experienciasUsuario, null);
                $anosUsuario = number_format($totalDiasUsuario / 365.25, 1);
                $anosReqStr = $anosRequeridos ? " ({$anosRequeridos} a├▒os equivalentes)" : '';
                throw new \Exception(
                    "No cumples con la experiencia requerida. "
                    . "Se requieren {$experienciaRequerida->horas_minimas} horas{$anosReqStr} "
                    . "y tienes {$experienciaTotalHoras} horas ({$anosUsuario} a├▒os).",
                    403
                );
            }
        }

        // Verificar requisito de experiencia basado en fecha (si la convocatoria define una fecha)
        if (!empty($convocatoria->experiencia_requerida_fecha)) {
            try {
                $fechaReq = \Carbon\Carbon::parse($convocatoria->experiencia_requerida_fecha);
            } catch (\Exception $e) {
                throw new \Exception('Fecha de experiencia requerida inv├ílida en la convocatoria.', 400);
            }

            $experienciasUsuario = $user->experienciasUsuario;
            $cumpleFecha = false;

            foreach ($experienciasUsuario as $exp) {
                // Si la experiencia est├í vigente (sin fecha_finalizacion) se considera v├ílida
                if (empty($exp->fecha_finalizacion)) {
                    $cumpleFecha = true;
                    break;
                }

                try {
                    $fechaFin = \Carbon\Carbon::parse($exp->fecha_finalizacion);
                } catch (\Exception $e) {
                    continue; // si la fecha del registro es inv├ílida, omitir esa experiencia
                }

                // Si la experiencia finaliz├│ en o despu├®s de la fecha requerida, cumple
                if ($fechaFin->greaterThanOrEqualTo($fechaReq)) {
                    $cumpleFecha = true;
                    break;
                }
            }

            if (!$cumpleFecha) {
                // Calcular a├▒os totales que tiene el usuario
                $totalDiasUsuario = $this->calcularTotalDiasExperiencia($user->experienciasUsuario, null);
                $anosUsuario = number_format($totalDiasUsuario / 365.25, 1);

                // Encontrar la fecha de finalizaci├│n m├ís reciente para orientar al usuario
                $fechaMasReciente = null;
                foreach ($user->experienciasUsuario as $exp) {
                    if (!empty($exp->fecha_finalizacion)) {
                        try {
                            $fechaFin = \Carbon\Carbon::parse($exp->fecha_finalizacion);
                            if ($fechaMasReciente === null || $fechaFin->greaterThan($fechaMasReciente)) {
                                $fechaMasReciente = $fechaFin;
                            }
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }

                $detalleExp = $fechaMasReciente
                    ? " Tu experiencia m├ís reciente finaliz├│ el {$fechaMasReciente->toDateString()}."
                    : ' No tienes experiencias registradas.';

                throw new \Exception(
                    "No cumples con la experiencia requerida. "
                    . "Se requiere haber tenido experiencia vigente hasta el {$fechaReq->toDateString()} "
                    . "y cuentas con {$anosUsuario} a├▒os de experiencia acumulada."
                    . $detalleExp,
                    403
                );
            }
        }

        // Verificar requisitos espec├¡ficos de tipos de experiencia
        if ($convocatoria->requisitos_experiencia) {
            $this->verificarRequisitosExperienciaEspecifica($user, $convocatoria->requisitos_experiencia);
        }

        // Verificar requisitos de idiomas
        if ($convocatoria->requisitos_idiomas) {
            $this->verificarRequisitosIdiomas($user, $convocatoria->requisitos_idiomas);
        }

        // Verificar perfil profesional
        if ($convocatoria->perfilProfesional) {
            $this->verificarPerfilProfesional($user, $convocatoria->perfilProfesional);
        }

        // Verificar experiencia por cantidad + unidad (ej: 2 A├▒os, 6 Meses)
        if (!empty($convocatoria->cantidad_experiencia) && !empty($convocatoria->unidad_experiencia)) {
            $this->verificarExperienciaCantidadUnidad($user, $convocatoria);
        }

        // Verificar experiencia por a├▒os + tipo (ej: 3 a├▒os de experiencia docente)
        if (!empty($convocatoria->anos_experiencia_requerida) && !empty($convocatoria->tipo_experiencia_requerida)) {
            $this->verificarAniosExperienciaPorTipo($user, $convocatoria);
        }

        // Verificar facultad: si la convocatoria especifica una Facultad relacionada, exigir pertenencia.
        // Si la convocatoria define `facultad_otro` (texto libre), no se exige pertenencia autom├íticamente.
        if ($convocatoria->facultad) {
            $this->verificarFacultadUsuario($user, $convocatoria->facultad);
        }

        // Aqu├¡ se pueden agregar m├ís verificaciones seg├║n requisitos_adicionales
    }

    /**
     * Verificar que el usuario cumpla la experiencia requerida en cantidad + unidad.
     * Soporta unidades: A├▒os, Meses, Semanas.
     * Si la convocatoria define tipo_experiencia_requerida, solo se contabilizan
     * las experiencias de ese tipo.
     */
    private function verificarExperienciaCantidadUnidad($user, $convocatoria)
    {
        $cantidadRequerida = $convocatoria->cantidad_experiencia;
        $unidad = strtolower(trim($convocatoria->unidad_experiencia));
        $tipoFiltro = $convocatoria->tipo_experiencia_requerida ?? null;

        $totalDias = $this->calcularTotalDiasExperiencia($user->experienciasUsuario, $tipoFiltro);

        // Convertir el requisito a d├¡as para comparar en la misma unidad
        $diasRequeridos = match (true) {
            str_starts_with($unidad, 'a├▒o') => $cantidadRequerida * 365.25,
            str_starts_with($unidad, 'mes') => $cantidadRequerida * 30.44,
            str_starts_with($unidad, 'sem') => $cantidadRequerida * 7,
            default => $cantidadRequerida * 365.25, // default a├▒os
        };

        if ($totalDias < $diasRequeridos) {
            $tieneStr = $this->diasATexto($totalDias, $unidad);
            $requiereStr = "{$cantidadRequerida} {$convocatoria->unidad_experiencia}";
            $tipoStr = $tipoFiltro ? " de experiencia en {$tipoFiltro}" : '';
            throw new \Exception(
                "No cumples con la experiencia requerida{$tipoStr}. Se requieren {$requiereStr} y tienes {$tieneStr}.",
                403
            );
        }
    }

    /**
     * Verificar que el usuario cumpla los a├▒os de experiencia requeridos por tipo.
     */
    private function verificarAniosExperienciaPorTipo($user, $convocatoria)
    {
        $anosRequeridos = $convocatoria->anos_experiencia_requerida;
        $tipoRequerido = $convocatoria->tipo_experiencia_requerida;

        $totalDias = $this->calcularTotalDiasExperiencia($user->experienciasUsuario, $tipoRequerido);
        $anosUsuario = round($totalDias / 365.25, 1);

        if ($anosUsuario < $anosRequeridos) {
            throw new \Exception(
                "No cumples con los a├▒os de experiencia requeridos en {$tipoRequerido}. " .
                "Se requieren {$anosRequeridos} a├▒os y tienes {$anosUsuario} a├▒os.",
                403
            );
        }
    }

    /**
     * Calcula el total de d├¡as de experiencia del usuario.
     * Si $tipoFiltro no es null, solo cuenta experiencias de ese tipo.
     * Incluye experiencias activas (trabajo_actual / sin fecha_finalizacion).
     *
     * @param \Illuminate\Database\Eloquent\Collection $experiencias
     * @param string|null $tipoFiltro
     * @return float Total en d├¡as
     */
    private function calcularTotalDiasExperiencia($experiencias, ?string $tipoFiltro): float
    {
        $totalDias = 0.0;

        foreach ($experiencias as $exp) {
            // Filtrar por tipo si se especific├│
            if ($tipoFiltro !== null) {
                if (strtolower(trim($exp->tipo_experiencia)) !== strtolower(trim($tipoFiltro))) {
                    continue;
                }
            }

            if (empty($exp->fecha_inicio)) {
                continue;
            }

            try {
                $inicio = \Carbon\Carbon::parse($exp->fecha_inicio);
                // Trabajo actual o sin fecha de fin ÔåÆ usar hoy como fin
                $fin = (!empty($exp->fecha_finalizacion))
                    ? \Carbon\Carbon::parse($exp->fecha_finalizacion)
                    : \Carbon\Carbon::today();

                if ($fin->lessThan($inicio)) {
                    continue; // Datos inconsistentes, omitir
                }

                $totalDias += $inicio->diffInDays($fin);
            } catch (\Exception $e) {
                continue;
            }
        }

        return $totalDias;
    }

    /**
     * Convierte d├¡as a un texto representativo en la unidad dada.
     */
    private function diasATexto(float $dias, string $unidad): string
    {
        if (str_starts_with($unidad, 'a├▒o')) {
            return round($dias / 365.25, 1) . ' a├▒os';
        }
        if (str_starts_with($unidad, 'mes')) {
            return round($dias / 30.44, 1) . ' meses';
        }
        if (str_starts_with($unidad, 'sem')) {
            return round($dias / 7, 1) . ' semanas';
        }
        return round($dias / 365.25, 1) . ' a├▒os';
    }

    /**
     * Calcular la experiencia total del usuario en horas.
     *
     * @param \App\Models\Usuario\User $user
     * @param bool $esAdministrativo
     * @return int
     */
    private function calcularExperienciaUsuario($user, $esAdministrativo)
    {
        $experiencias = $user->experienciasUsuario;

        $totalHoras = 0;

        foreach ($experiencias as $experiencia) {
            // Asumir que tipo_experiencia indica si es docente o administrativo
            $esExperienciaAdministrativa = strtolower($experiencia->tipo_experiencia) === 'administrativo' ||
                strtolower($experiencia->tipo_experiencia) === 'administrativa';

            if ($esAdministrativo && !$esExperienciaAdministrativa) {
                continue; // Si la convocatoria es administrativa, solo contar experiencia administrativa
            }

            if (!$esAdministrativo && $esExperienciaAdministrativa) {
                continue; // Si la convocatoria es docente, no contar experiencia administrativa
            }

            $totalHoras += $experiencia->intensidad_horaria ?? 0;
        }

        return $totalHoras;
    }

    /**
     * Verificar requisitos espec├¡ficos de tipos de experiencia.
     * Usa las constantes definidas para asegurar consistencia.
     *
     * @param \App\Models\Usuario\User $user
     * @param array $requisitosExperiencia
     * @throws \Exception
     */
    private function verificarRequisitosExperienciaEspecifica($user, $requisitosExperiencia)
    {
        $experienciasUsuario = $user->experienciasUsuario;

        // Sumar todos los a├▒os requeridos a trav├®s de todos los tipos
        $totalAnosRequeridos = array_sum(array_values($requisitosExperiencia));

        // Sumar todos los d├¡as de experiencia del usuario sin filtrar por tipo
        $totalDias = $this->calcularTotalDiasExperiencia($experienciasUsuario, null);
        $totalAnosUsuario = round($totalDias / 365.25, 1);

        if ($totalAnosUsuario < $totalAnosRequeridos) {
            throw new \Exception(
                "No cumples con los a├▒os de experiencia requeridos. " .
                "Se requieren {$totalAnosRequeridos} a├▒os en total y tienes {$totalAnosUsuario} a├▒os.",
                403
            );
        }
    }

    /**
     * Calcular a├▒os de experiencia por tipo espec├¡fico.
     *
     * @param \Illuminate\Database\Eloquent\Collection $experiencias
     * @param string $tipoExperiencia
     * @return float
     */
    private function calcularAniosExperienciaPorTipo($experiencias, $tipoExperiencia)
    {
        $totalAnios = 0;

        foreach ($experiencias as $experiencia) {
            if (strtolower($experiencia->tipo_experiencia) === strtolower(str_replace('_', ' ', $tipoExperiencia))) {
                if ($experiencia->fecha_inicio) {
                    $fechaInicio = \Carbon\Carbon::parse($experiencia->fecha_inicio);
                    // Si no tiene fecha de finalizaci├│n (trabajo actual), usar hoy
                    $fechaFin = !empty($experiencia->fecha_finalizacion)
                        ? \Carbon\Carbon::parse($experiencia->fecha_finalizacion)
                        : \Carbon\Carbon::today();
                    $diferencia = $fechaInicio->diffInDays($fechaFin);
                    $totalAnios += $diferencia / 365.25; // Convertir d├¡as a a├▒os
                }
            }
        }

        return round($totalAnios, 1);
    }

    /**
     * Verificar requisitos de idiomas.
     * Usa las constantes definidas para asegurar consistencia en niveles MCER.
     *
     * @param \App\Models\Usuario\User $user
     * @param array $requisitosIdiomas
     * @throws \Exception
     */
    private function verificarRequisitosIdiomas($user, $requisitosIdiomas)
    {
        // Si no hay requisitos de idiomas, pasar la validaci├│n
        if (empty($requisitosIdiomas) || !is_array($requisitosIdiomas)) {
            return;
        }

        $idiomasUsuario = $user->idiomasUsuario;

        // Detectar si lleg├│ como array indexado (lista de niveles m├¡nimos, ej. ['A2', 'B1'])
        $esIndexado = array_keys($requisitosIdiomas) === range(0, count($requisitosIdiomas) - 1);

        if ($esIndexado) {
            // El usuario debe tener al menos un idioma con nivel >= al requerido para cada entrada
            foreach ($requisitosIdiomas as $nivelRequerido) {
                if (!in_array(strtoupper($nivelRequerido), NivelIdioma::all())) {
                    throw new \Exception("Nivel de idioma no v├ílido: {$nivelRequerido}. Los niveles v├ílidos son: " . implode(', ', NivelIdioma::all()) . ".", 400);
                }

                $cumpleRequisito = false;
                foreach ($idiomasUsuario as $idiomaUsuario) {
                    if ($this->compararNivelesIdioma($idiomaUsuario->nivel, $nivelRequerido)) {
                        $cumpleRequisito = true;
                        break;
                    }
                }

                if (!$cumpleRequisito) {
                    throw new \Exception("No cumples con el requisito de certificaci├│n de idioma nivel {$nivelRequerido} o superior.", 403);
                }
            }
            return;
        }

        foreach ($requisitosIdiomas as $idiomaRequerido => $nivelRequerido) {
            // Verificar que el nivel requerido sea v├ílido
            if (!in_array(strtoupper($nivelRequerido), NivelIdioma::all())) {
                throw new \Exception("Nivel de idioma no v├ílido: {$nivelRequerido}. Los niveles v├ílidos son: " . implode(', ', NivelIdioma::all()) . ".", 400);
            }

            $cumpleRequisito = false;

            // Normalizar idioma requerido (remover tildes, espacios)
            $idiomaRequeridoNormalizado = $this->normalizarIdioma((string) $idiomaRequerido);

            foreach ($idiomasUsuario as $idiomaUsuario) {
                // Normalizar idioma del usuario
                $idiomaUsuarioNormalizado = $this->normalizarIdioma((string) $idiomaUsuario->idioma);

                Log::info('DEBUG verificarRequisitosIdiomas - Comparando idiomas:', [
                    'idioma_requerido_original' => $idiomaRequerido,
                    'idioma_requerido_normalizado' => $idiomaRequeridoNormalizado,
                    'idioma_usuario_original' => $idiomaUsuario->idioma,
                    'idioma_usuario_normalizado' => $idiomaUsuarioNormalizado,
                    'nivel_usuario' => $idiomaUsuario->nivel,
                    'nivel_requerido' => $nivelRequerido,
                    'coinciden_idiomas' => $idiomaUsuarioNormalizado === $idiomaRequeridoNormalizado,
                ]);

                if ($idiomaUsuarioNormalizado === $idiomaRequeridoNormalizado) {
                    if ($this->compararNivelesIdioma($idiomaUsuario->nivel, $nivelRequerido)) {
                        $cumpleRequisito = true;
                        break;
                    }
                }
            }

            if (!$cumpleRequisito) {
                $idiomaFormateado = ucfirst($idiomaRequerido);
                throw new \Exception("No cumples con el requisito de idioma {$idiomaFormateado} nivel {$nivelRequerido}.", 403);
            }
        }
    }

    /**
     * Normalizar nombre de idioma: remover tildes, espacios extra, convertir a min├║sculas
     */
    private function normalizarIdioma(string $idioma): string
    {
        // Remover tildes y caracteres especiales
        $idioma = strtolower(trim($idioma));
        $idioma = str_replace(['├í', '├®', '├¡', '├│', '├║', '├▒'], ['a', 'e', 'i', 'o', 'u', 'n'], $idioma);
        // Remover espacios m├║ltiples
        $idioma = preg_replace('/\s+/', ' ', $idioma);
        return $idioma;
    }

    /**
     * Comparar niveles de idioma seg├║n el MCER.
     * Usa las constantes definidas para asegurar jerarqu├¡a correcta.
     *
     * @param string $nivelUsuario
     * @param string $nivelRequerido
     * @return bool
     */
    private function compararNivelesIdioma($nivelUsuario, $nivelRequerido)
    {
        $jerarquiaNiveles = NivelIdioma::all();

        $posicionUsuario = array_search(strtoupper($nivelUsuario), $jerarquiaNiveles);
        $posicionRequerido = array_search(strtoupper($nivelRequerido), $jerarquiaNiveles);

        // Si alguno de los niveles no est├í en la jerarqu├¡a, devolver false
        if ($posicionUsuario === false || $posicionRequerido === false) {
            return false;
        }

        return $posicionUsuario >= $posicionRequerido;
    }

    /**
     * Verificar que el usuario tenga el perfil profesional requerido.
     * Validaci├│n robusta que considera:
     * - Palabras clave espec├¡ficas del perfil en t├¡tulos de estudio
     * - Nivel acad├®mico m├¡nimo requerido para el perfil
     * - Compatibilidad de tipos de estudio
     *
     * @param \App\Models\Usuario\User $user
     * @param \App\Models\PerfilProfesional $perfilRequerido
     * @throws \Exception
     */
    private function verificarPerfilProfesional($user, $perfilRequerido)
    {
        $estudiosUsuario = $user->estudiosUsuario;

        // Verificar que el perfil requerido existe y tiene nombre
        if (!$perfilRequerido || !isset($perfilRequerido->nombre_perfil)) {
            throw new \Exception("Perfil profesional requerido no v├ílido.", 400);
        }

        $nombrePerfil = $perfilRequerido->nombre_perfil;

        // Verificar que el perfil est├® definido en las constantes
        if (!in_array($nombrePerfil, PerfilesProfesionales::all())) {
            // Si no est├í en constantes, usar validaci├│n b├ísica
            $this->validacionBasicaPerfil($estudiosUsuario, $nombrePerfil);
            return;
        }

        // Validaci├│n robusta usando constantes
        $this->validacionRobustaPerfil($estudiosUsuario, $nombrePerfil);
    }

    /**
     * Validaci├│n b├ísica de perfil (para perfiles no definidos en constantes)
     */
    private function validacionBasicaPerfil($estudiosUsuario, $nombrePerfil)
    {
        $estudiosRelacionados = $estudiosUsuario->filter(function ($estudio) use ($nombrePerfil) {
            $tituloEstudio = strtolower($estudio->titulo_obtenido ?? '');
            $perfilLower = strtolower($nombrePerfil);

            return str_contains($tituloEstudio, $perfilLower) ||
                str_contains($perfilLower, $tituloEstudio);
        });

        if ($estudiosRelacionados->isEmpty()) {
            throw new \Exception("No cumples con el perfil profesional requerido: {$nombrePerfil}.", 403);
        }
    }

    /**
     * Validaci├│n robusta de perfil usando constantes y l├│gica avanzada
     */
    private function validacionRobustaPerfil($estudiosUsuario, $nombrePerfil)
    {
        $palabrasClave = PerfilesProfesionales::getPalabrasClavePerfil($nombrePerfil);
        $nivelesMinimos = PerfilesProfesionales::getNivelMinimoEstudio($nombrePerfil);

        $estudioCompatible = false;
        $nivelAdecuado = false;

        foreach ($estudiosUsuario as $estudio) {
            $tituloEstudio = strtolower($estudio->titulo_obtenido ?? '');
            $tipoEstudio = $estudio->tipo_estudio ?? '';

            // 1. Verificar palabras clave en el t├¡tulo
            $contienePalabrasClave = false;
            foreach ($palabrasClave as $palabra) {
                if (str_contains($tituloEstudio, strtolower($palabra))) {
                    $contienePalabrasClave = true;
                    break;
                }
            }

            // 2. Verificar nivel acad├®mico
            $nivelValido = in_array($tipoEstudio, $nivelesMinimos);

            // 3. Verificar si es un t├¡tulo relacionado (l├│gica adicional)
            $tituloRelacionado = $this->esTituloRelacionado($tituloEstudio, $nombrePerfil);

            if (($contienePalabrasClave || $tituloRelacionado) && $nivelValido) {
                $estudioCompatible = true;
                $nivelAdecuado = true;
                break;
            }
        }

        if (!$estudioCompatible) {
            throw new \Exception("No tienes estudios compatibles con el perfil profesional requerido: {$nombrePerfil}. Se requieren estudios en ├íreas relacionadas con al menos nivel " . $this->getNivelMinimoTexto($nivelesMinimos) . ".", 403);
        }

        if (!$nivelAdecuado) {
            throw new \Exception("Tu nivel de estudios no cumple con el m├¡nimo requerido para el perfil {$nombrePerfil}. Se requiere al menos: " . $this->getNivelMinimoTexto($nivelesMinimos) . ".", 403);
        }
    }

    /**
     * Verifica si un t├¡tulo est├í relacionado con un perfil profesional
     * L├│gica adicional m├ís flexible que las palabras clave exactas
     */
    private function esTituloRelacionado($tituloEstudio, $nombrePerfil): bool
    {
        // Normalizar textos
        $titulo = $this->normalizarTexto($tituloEstudio);
        $perfil = $this->normalizarTexto($nombrePerfil);

        // Verificar coincidencias parciales m├ís flexibles
        $palabrasPerfil = explode(' ', $perfil);
        $coincidencias = 0;

        foreach ($palabrasPerfil as $palabra) {
            if (str_contains($titulo, $palabra) && strlen($palabra) > 3) {
                $coincidencias++;
            }
        }

        // Si al menos el 50% de las palabras clave del perfil est├ín en el t├¡tulo
        return $coincidencias >= ceil(count($palabrasPerfil) * 0.5);
    }

    /**
     * Normaliza texto para comparaci├│n (quita acentos, caracteres especiales)
     */
    private function normalizarTexto($texto): string
    {
        $texto = strtolower($texto);
        $texto = str_replace(['├í', '├®', '├¡', '├│', '├║', '├▒'], ['a', 'e', 'i', 'o', 'u', 'n'], $texto);
        $texto = preg_replace('/[^a-z0-9\s]/', '', $texto);
        return trim($texto);
    }

    /**
     * Convierte array de niveles de estudio a texto legible
     */
    private function getNivelMinimoTexto($niveles): string
    {
        $nombres = [
            TiposEstudio::TECNICO => 'T├®cnico',
            TiposEstudio::TECNOLOGICO => 'Tecnol├│gico',
            TiposEstudio::PREGRADO => 'Pregrado',
            TiposEstudio::ESPECIALIZACION => 'Especializaci├│n',
            TiposEstudio::MAESTRIA => 'Maestr├¡a',
            TiposEstudio::DOCTORADO => 'Doctorado',
            TiposEstudio::POSTDOCTORADO => 'Postdoctorado',
        ];

        $nivelesTexto = array_map(function ($nivel) use ($nombres) {
            return $nombres[$nivel] ?? $nivel;
        }, $niveles);

        return implode(', ', $nivelesTexto);
    }

    /**
     * Verificar que el usuario pertenezca a la facultad requerida.
     *
     * @param \App\Models\Usuario\User $user
     * @param \App\Models\Facultad $facultadRequerida
     * @throws \Exception
     */
    private function verificarFacultadUsuario($user, $facultadRequerida)
    {
        // Verificar que la facultad requerida existe y tiene nombre
        if (!$facultadRequerida || !isset($facultadRequerida->id_facultad) || !isset($facultadRequerida->nombre_facultad)) {
            throw new \Exception("Facultad requerida no v├ílida.", 400);
        }

        $facultadesUsuario = $user->facultades()->where('is_active', true)->get();

        $perteneceFacultad = $facultadesUsuario->contains(function ($facultad) use ($facultadRequerida) {
            return $facultad->id_facultad === $facultadRequerida->id_facultad;
        });

        if (!$perteneceFacultad) {
            throw new \Exception("No perteneces a la facultad requerida: {$facultadRequerida->nombre_facultad}.", 403);
        }
    }
}
