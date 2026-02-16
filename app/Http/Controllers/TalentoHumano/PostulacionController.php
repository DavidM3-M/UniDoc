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
use Illuminate\Validation\Rule;

class PostulacionController
{
    protected $generadorHojaDeVidaPDFService;
    /**
     * constructor del controlador.
     * se utilizar para inyectar el servicio de generador de hoja de vida a PDF.
     */
    public function __construct(GeneradorHojaDeVidaPDFService $generadorHojaDeVidaPDFService)
    {
        $this->generadorHojaDeVidaPDFService = $generadorHojaDeVidaPDFService;
    }

    /**
     * Crear una postulación del usuario autenticado a una convocatoria.
     *
     * Este método permite que un usuario autenticado se postule a una convocatoria específica.
     * La operación se ejecuta dentro de una transacción para garantizar la integridad de los datos.
     * Se valida que:
     * - La convocatoria exista.
     * - La convocatoria esté abierta (no cerrada).
     * - El usuario no se haya postulado previamente a la misma convocatoria.
     *
     * Si esta correcto, se registra la postulación con estado inicial "Enviada".
     * En caso de errores (convocatoria cerrada, duplicidad de postulación u otros),
     * se lanza una excepción y se retorna una respuesta con el mensaje adecuado.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @param int $convocatoriaId ID de la convocatoria a la que el usuario desea postularse.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function crearPostulacion(Request $request, $convocatoriaId)
    {
        try {
            $convocatoria = Convocatoria::findOrFail($convocatoriaId);
             // Verificar si la convocatoria está cerrada
            if ($convocatoria->estado_convocatoria === 'Cerrada') {
                return response()->json([
                    'mensaje' => 'Esta convocatoria ya está cerrada'
                ], 403);
            }

            // Verificar si la fecha de cierre ya pasó
            if (now()->greaterThan($convocatoria->fecha_cierre)) {
                return response()->json([
                    'mensaje' => 'La fecha de cierre de esta convocatoria ya ha pasado'
                ], 403);
            }
            
            DB::transaction(function () use ($request, $convocatoriaId) { // Validar el ID de la convocatoria
                $user = $request->user()->load(['experienciasUsuario', 'estudiosUsuario', 'idiomasUsuario', 'facultades']); // Obtener el usuario autenticado con todas las relaciones necesarias

                $convocatoria = Convocatoria::with(['tipoCargo', 'experienciaRequerida', 'perfilProfesional', 'facultad'])->findOrFail($convocatoriaId); // Verificar si la convocatoria existe

                if ($convocatoria->estado_convocatoria === 'Cerrada') { // Verificar si la convocatoria está cerrada
                    throw new \Exception('Esta convocatoria está cerrada y no admite más postulaciones.', 403); // Lanzar excepción si la convocatoria está cerrada
                }

                $existe = Postulacion::where('user_id', $user->id) // Verificar si el usuario ya está postulado
                    ->where('convocatoria_id', $convocatoriaId)
                    ->exists();

                if ($existe) {
                    throw new \Exception('Ya te has postulado a esta convocatoria', 409);
                }

                // Verificar requisitos de la convocatoria
                $this->verificarRequisitosConvocatoria($user, $convocatoria);

                Postulacion::create([ // Crear la postulación
                    'user_id' => $user->id,
                    'convocatoria_id' => $convocatoriaId,
                    'estado_postulacion' => 'Enviada'
                ]);

                // Crear registros de avales pendientes para este postulante si la convocatoria los requiere
                if (!empty($convocatoria->avales_establecidos) && is_array($convocatoria->avales_establecidos)) {
                    foreach ($convocatoria->avales_establecidos as $avalRequerido) {
                        ConvocatoriaAval::updateOrCreate(
                            [
                                'convocatoria_id' => $convocatoriaId,
                                'user_id' => $user->id,
                                'aval' => $avalRequerido,
                            ],
                            [
                                'estado' => 'pending'
                            ]
                        );
                    }
                }
            });

            return response()->json([ // Crear la respuesta JSON
                'message' => 'Postulación enviada correctamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error al crear la postulación.',
                'error' => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Obtener todas las postulaciones registradas en el sistema.
     *
     * Este método recupera todas las postulaciones realizadas por los usuarios, incluyendo
     * la información del usuario postulante (`usuarioPostulacion`) y de la convocatoria
     * correspondiente (`convocatoriaPostulacion`). Las postulaciones se ordenan de forma
     * descendente según su fecha de creación.
     * En caso de producirse un error durante la consulta, se captura la excepción y se
     * retorna una respuesta adecuada con el mensaje de error.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de postulaciones o mensaje de error.
     */
    public function obtenerPostulaciones()
    {
        try {
            $postulaciones = Postulacion::with('usuarioPostulacion', 'convocatoriaPostulacion') // Obtener todas las postulaciones
                ->orderBy('created_at', 'desc') // Ordenar por fecha de creación
                ->get();

            return response()->json(['postulaciones' => $postulaciones], 200); // Retornar las postulaciones en formato JSON

        } catch (\Exception $e) {
            return response()->json([ // Manejar excepciones
                'message' => 'Ocurrió un error al obtener las postulaciones.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener las postulaciones asociadas a una convocatoria específica.
     *
     * Este método recupera todas las postulaciones realizadas a una convocatoria determinada,
     * identificada por su ID. Cada postulación incluye la información del usuario postulante
     * gracias a la relación `usuarioPostulacion`.
     * En caso de error durante la consulta, se captura una excepción y se retorna una respuesta adecuada.
     *
     * @param int $idConvocatoria ID de la convocatoria cuyas postulaciones se desean consultar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de postulaciones o mensaje de error.
     */
    // public function obtenerPorConvocatoria($idConvocatoria)
    // {
    //     try {
    //         $postulaciones = Postulacion::where('convocatoria_id', $idConvocatoria) // Obtener las postulaciones por ID de convocatoria
    //             ->with('usuarioPostulacion') // Incluir la relación con el usuario postulante
    //             ->get();

    //         return response()->json(['postulaciones' => $postulaciones], 200); // Retornar las postulaciones en formato JSON

    //     } catch (\Exception $e) { // Manejar excepciones
    //         return response()->json([
    //             'message' => 'Ocurrió un error al obtener las postulaciones por convocatoria.', // Retornar un mensaje de error
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * Obtener las postulaciones del usuario autenticado.
     *
     * Este método recupera todas las postulaciones realizadas por el usuario que ha iniciado sesión.
     * Cada postulación incluye la información relacionada con la convocatoria a la que se postuló,
     * gracias a la relación `convocatoriaPostulacion`.
     * En caso de error durante la consulta, se captura una excepción y se retorna una respuesta adecuada.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de postulaciones del usuario o mensaje de error.
     */
    public function obtenerPostulacionesUsuario(Request $request)
    {
        try {
            $postulaciones = Postulacion::where('user_id', $request->user()->id) // Obtener las postulaciones del usuario autenticado
                ->with('convocatoriaPostulacion') // Incluir la relación con la convocatoria
                ->get();

            return response()->json(['postulaciones' => $postulaciones], 200); // Retornar las postulaciones en formato JSON

        } catch (\Exception $e) { // Manejar excepciones
            return response()->json([ // Retornar un mensaje de error
                'message' => 'Ocurrió un error al obtener las postulaciones del usuario.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar la hoja de vida en PDF de un usuario postulado a una convocatoria específica.
     *
     * Este método verifica que el usuario esté postulado a la convocatoria indicada. Si la postulación existe,
     * se utiliza el servicio `GeneradorHojaDeVidaPDFService` para generar el PDF de la hoja de vida.
     * Si el usuario no está postulado a la convocatoria, se retorna una respuesta con código 404.
     * En caso de error durante el proceso, se captura la excepción y se responde con un mensaje adecuado.
     *
     * @param int $idConvocatoria ID de la convocatoria.
     * @param int $idUsuario ID del usuario cuya hoja de vida se desea generar.
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     * Respuesta JSON con mensaje de error o archivo PDF generado exitosamente.
     */
    public function generarHojaDeVidaPDF($idConvocatoria, $idUsuario)
    {
        try {
            $postulacion = Postulacion::where('convocatoria_id', $idConvocatoria) // Obtener la postulación del usuario a la convocatoria
                ->where('user_id', $idUsuario) // Verificar que el usuario esté postulado a la convocatoria
                ->first();

            if (!$postulacion) {
                return response()->json([ // Retornar un mensaje de error si el usuario no está postulado
                    'message' => 'El usuario no está postulado a esta convocatoria.'
                ], 404);
            }

            return $this->generadorHojaDeVidaPDFService->generar($idUsuario); // Generar la hoja de vida en PDF

        } catch (\Exception $e) { // Manejar excepciones
            return response()->json([
                'message' => 'Ocurrió un error al generar la hoja de vida.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar el estado de una postulación.
     *
     * Este método permite modificar el estado de una postulación específica, validando primero que el nuevo estado
     * esté dentro de los valores definidos en la enumeración `EstadoPostulacion`.
     * La operación se realiza dentro de una transacción para asegurar la integridad de los datos.
     * Si la postulación no existe, se lanza una excepción con código 404.
     * En caso de error durante la validación o actualización, se captura la excepción y se retorna una respuesta adecuada.
     *
     * @param Request $request Solicitud HTTP que contiene el nuevo estado de la postulación.
     * @param int $idPostulacion ID de la postulación que se desea actualizar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function actualizarEstadoPostulacion(Request $request, $idPostulacion)
    {
        try {
            $request->validate([
                'estado_postulacion' => ['required', 'string', Rule::in(EstadoPostulacion::all())], // Validar el estado de la postulación
            ]);
            DB::transaction(function () use ($request, $idPostulacion) { // Iniciar una transacción para garantizar la integridad de los datos
                $postulacion = Postulacion::find($idPostulacion); // Buscar la postulación por su ID

                if (!$postulacion) { // Verificar si la postulación existe
                    throw new \Exception('No se encontro una postulación.', 404);
                }

                $postulacion->estado_postulacion = $request->estado_postulacion; // Actualizar el estado de la postulación
                $postulacion->save(); // Guardar los cambios en la base de datos

            });

            return response()->json([
                'message' => 'Estado de postulación actualizado correctamente.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error al actualizar el estado de la postulación.',
                'error' => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Eliminar una postulación específica.
     *
     * Este método permite eliminar una postulación del sistema, identificada por su ID.
     * La operación se ejecuta dentro de una transacción para asegurar la integridad de los datos.
     * Si la postulación no existe, se lanza una excepción con código 404.
     * En caso de ocurrir un error durante el proceso de eliminación, se captura la excepción
     * y se retorna una respuesta con el mensaje de error correspondiente.
     *
     * @param int $idPostulacion ID de la postulación que se desea eliminar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function eliminarPostulacion($idPostulacion)
    {
        try {
            DB::transaction(function () use ($idPostulacion) { // Iniciar una transacción para garantizar la integridad de los datos
                $postulacion = Postulacion::find($idPostulacion); // Buscar la postulación por su ID

                if (!$postulacion) { // Verificar si la postulación existe
                    throw new \Exception('Postulación no encontrada.', 404);
                }

                $postulacion->delete(); // Eliminar la postulación
            });

            return response()->json([ // Retornar un mensaje de éxito
                'message' => 'Postulación eliminada correctamente.'
            ]);

        } catch (\Exception $e) { // Manejar excepciones
            return response()->json([ // Retornar un mensaje de error
                'message' => 'Ocurrió un error al eliminar la postulación.',
                'error' => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Eliminar una postulación realizada por el usuario autenticado.
     *
     * Este método permite que un usuario elimine su propia postulación, identificada por su ID.
     * Se valida que la postulación exista y que pertenezca al usuario autenticado para evitar accesos no autorizados.
     * Si la validación es exitosa, se elimina la postulación. En caso contrario, se lanza una excepción con el código correspondiente.
     * Si ocurre cualquier error durante el proceso, se retorna una respuesta con el mensaje adecuado.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @param int $id ID de la postulación que se desea eliminar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function eliminarPostulacionUsuario(Request $request, $id)
    {
        try {
            $postulacion = Postulacion::find($id); // Buscar la postulación por su ID

            if (!$postulacion) {
                throw new \Exception('Postulación no encontrada.', 404);
            }

            if ($postulacion->user_id !== $request->user()->id) { // Verificar si el usuario autenticado es el propietario de la postulación
                throw new \Exception('No tienes permiso para eliminar esta postulación.', 403);
            }

            $postulacion->delete(); // Eliminar la postulación

            return response()->json([ // Retornar un mensaje de éxito
                'message' => 'Postulación eliminada correctamente.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error al eliminar la postulación del usuario.',
                'error' => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }
    /**
 * Generar la hoja de vida en PDF de un usuario (para Rectoría/Vicerrectoría).
 *
 * Este método genera el PDF de la hoja de vida de un usuario sin necesidad de verificar
 * una postulación específica. Es utilizado por roles administrativos como Rectoría.
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
            'message' => 'Ocurrió un error al generar la hoja de vida.',
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
                throw new \Exception("No cumples con la experiencia requerida. Se requieren {$experienciaRequerida->horas_minimas} horas y tienes {$experienciaTotalHoras} horas.", 403);
            }
        }

        // Verificar requisito de experiencia basado en fecha (si la convocatoria define una fecha)
        if (!empty($convocatoria->experiencia_requerida_fecha)) {
            try {
                $fechaReq = \Carbon\Carbon::parse($convocatoria->experiencia_requerida_fecha);
            } catch (\Exception $e) {
                throw new \Exception('Fecha de experiencia requerida inválida en la convocatoria.', 400);
            }

            $experienciasUsuario = $user->experienciasUsuario;
            $cumpleFecha = false;

            foreach ($experienciasUsuario as $exp) {
                // Si la experiencia está vigente (sin fecha_finalizacion) se considera válida
                if (empty($exp->fecha_finalizacion)) {
                    $cumpleFecha = true;
                    break;
                }

                try {
                    $fechaFin = \Carbon\Carbon::parse($exp->fecha_finalizacion);
                } catch (\Exception $e) {
                    continue; // si la fecha del registro es inválida, omitir esa experiencia
                }

                // Si la experiencia finalizó en o después de la fecha requerida, cumple
                if ($fechaFin->greaterThanOrEqualTo($fechaReq)) {
                    $cumpleFecha = true;
                    break;
                }
            }

            if (!$cumpleFecha) {
                throw new \Exception("No cumples con la experiencia requerida hasta la fecha {$fechaReq->toDateString()}.", 403);
            }
        }

        // Verificar requisitos específicos de tipos de experiencia
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

        // Verificar facultad: si la convocatoria especifica una Facultad relacionada, exigir pertenencia.
        // Si la convocatoria define `facultad_otro` (texto libre), no se exige pertenencia automáticamente.
        if ($convocatoria->facultad) {
            $this->verificarFacultadUsuario($user, $convocatoria->facultad);
        }

        // Aquí se pueden agregar más verificaciones según requisitos_adicionales
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
     * Verificar requisitos específicos de tipos de experiencia.
     * Usa las constantes definidas para asegurar consistencia.
     *
     * @param \App\Models\Usuario\User $user
     * @param array $requisitosExperiencia
     * @throws \Exception
     */
    private function verificarRequisitosExperienciaEspecifica($user, $requisitosExperiencia)
    {
        $experienciasUsuario = $user->experienciasUsuario;

        foreach ($requisitosExperiencia as $tipoExperiencia => $anosRequeridos) {
            // Verificar que el tipo de experiencia esté definido en constantes
            if (!in_array($tipoExperiencia, TiposExperiencia::all())) {
                throw new \Exception("Tipo de experiencia no válido: {$tipoExperiencia}.", 400);
            }

            $anosUsuario = $this->calcularAniosExperienciaPorTipo($experienciasUsuario, $tipoExperiencia);

            if ($anosUsuario < $anosRequeridos) {
                $tipoExperienciaFormateado = ucfirst(str_replace('_', ' ', $tipoExperiencia));
                throw new \Exception("No cumples con los años de experiencia requeridos en {$tipoExperienciaFormateado}. Se requieren {$anosRequeridos} años y tienes {$anosUsuario} años.", 403);
            }
        }
    }

    /**
     * Calcular años de experiencia por tipo específico.
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
                if ($experiencia->fecha_inicio && $experiencia->fecha_finalizacion) {
                    $fechaInicio = \Carbon\Carbon::parse($experiencia->fecha_inicio);
                    $fechaFin = \Carbon\Carbon::parse($experiencia->fecha_finalizacion);
                    $diferencia = $fechaInicio->diffInDays($fechaFin);
                    $totalAnios += $diferencia / 365.25; // Convertir días a años
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
        $idiomasUsuario = $user->idiomasUsuario;

        foreach ($requisitosIdiomas as $idiomaRequerido => $nivelRequerido) {
            // Verificar que el nivel requerido sea válido
            if (!in_array(strtoupper($nivelRequerido), NivelIdioma::all())) {
                throw new \Exception("Nivel de idioma no válido: {$nivelRequerido}. Los niveles válidos son: " . implode(', ', NivelIdioma::all()) . ".", 400);
            }

            $cumpleRequisito = false;

            foreach ($idiomasUsuario as $idiomaUsuario) {
                if (strtolower($idiomaUsuario->idioma) === strtolower($idiomaRequerido)) {
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
     * Comparar niveles de idioma según el MCER.
     * Usa las constantes definidas para asegurar jerarquía correcta.
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

        // Si alguno de los niveles no está en la jerarquía, devolver false
        if ($posicionUsuario === false || $posicionRequerido === false) {
            return false;
        }

        return $posicionUsuario >= $posicionRequerido;
    }

    /**
     * Verificar que el usuario tenga el perfil profesional requerido.
     * Validación robusta que considera:
     * - Palabras clave específicas del perfil en títulos de estudio
     * - Nivel académico mínimo requerido para el perfil
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
            throw new \Exception("Perfil profesional requerido no válido.", 400);
        }

        $nombrePerfil = $perfilRequerido->nombre_perfil;

        // Verificar que el perfil esté definido en las constantes
        if (!in_array($nombrePerfil, PerfilesProfesionales::all())) {
            // Si no está en constantes, usar validación básica
            $this->validacionBasicaPerfil($estudiosUsuario, $nombrePerfil);
            return;
        }

        // Validación robusta usando constantes
        $this->validacionRobustaPerfil($estudiosUsuario, $nombrePerfil);
    }

    /**
     * Validación básica de perfil (para perfiles no definidos en constantes)
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
     * Validación robusta de perfil usando constantes y lógica avanzada
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

            // 1. Verificar palabras clave en el título
            $contienePalabrasClave = false;
            foreach ($palabrasClave as $palabra) {
                if (str_contains($tituloEstudio, strtolower($palabra))) {
                    $contienePalabrasClave = true;
                    break;
                }
            }

            // 2. Verificar nivel académico
            $nivelValido = in_array($tipoEstudio, $nivelesMinimos);

            // 3. Verificar si es un título relacionado (lógica adicional)
            $tituloRelacionado = $this->esTituloRelacionado($tituloEstudio, $nombrePerfil);

            if (($contienePalabrasClave || $tituloRelacionado) && $nivelValido) {
                $estudioCompatible = true;
                $nivelAdecuado = true;
                break;
            }
        }

        if (!$estudioCompatible) {
            throw new \Exception("No tienes estudios compatibles con el perfil profesional requerido: {$nombrePerfil}. Se requieren estudios en áreas relacionadas con al menos nivel " . $this->getNivelMinimoTexto($nivelesMinimos) . ".", 403);
        }

        if (!$nivelAdecuado) {
            throw new \Exception("Tu nivel de estudios no cumple con el mínimo requerido para el perfil {$nombrePerfil}. Se requiere al menos: " . $this->getNivelMinimoTexto($nivelesMinimos) . ".", 403);
        }
    }

    /**
     * Verifica si un título está relacionado con un perfil profesional
     * Lógica adicional más flexible que las palabras clave exactas
     */
    private function esTituloRelacionado($tituloEstudio, $nombrePerfil): bool
    {
        // Normalizar textos
        $titulo = $this->normalizarTexto($tituloEstudio);
        $perfil = $this->normalizarTexto($nombrePerfil);

        // Verificar coincidencias parciales más flexibles
        $palabrasPerfil = explode(' ', $perfil);
        $coincidencias = 0;

        foreach ($palabrasPerfil as $palabra) {
            if (str_contains($titulo, $palabra) && strlen($palabra) > 3) {
                $coincidencias++;
            }
        }

        // Si al menos el 50% de las palabras clave del perfil están en el título
        return $coincidencias >= ceil(count($palabrasPerfil) * 0.5);
    }

    /**
     * Normaliza texto para comparación (quita acentos, caracteres especiales)
     */
    private function normalizarTexto($texto): string
    {
        $texto = strtolower($texto);
        $texto = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $texto);
        $texto = preg_replace('/[^a-z0-9\s]/', '', $texto);
        return trim($texto);
    }

    /**
     * Convierte array de niveles de estudio a texto legible
     */
    private function getNivelMinimoTexto($niveles): string
    {
        $nombres = [
            TiposEstudio::TECNICO => 'Técnico',
            TiposEstudio::TECNOLOGICO => 'Tecnológico',
            TiposEstudio::PREGRADO => 'Pregrado',
            TiposEstudio::ESPECIALIZACION => 'Especialización',
            TiposEstudio::MAESTRIA => 'Maestría',
            TiposEstudio::DOCTORADO => 'Doctorado',
            TiposEstudio::POSTDOCTORADO => 'Postdoctorado',
        ];

        $nivelesTexto = array_map(function($nivel) use ($nombres) {
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
            throw new \Exception("Facultad requerida no válida.", 400);
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
