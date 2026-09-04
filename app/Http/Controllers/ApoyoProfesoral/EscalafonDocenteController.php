<?php

namespace App\Http\Controllers\ApoyoProfesoral;

use App\Constants\ClavePrimaria;
use App\Exceptions\AscensoEscalafonException;
use App\Http\Requests\RequestApoyoProfesoral\RequestEscalafon\ActualizarPeriodoAscensoRequest;
use App\Http\Requests\RequestApoyoProfesoral\RequestEscalafon\AscenderEscalonRequest;
use App\Http\Requests\RequestApoyoProfesoral\RequestEscalafon\CrearPeriodoAscensoRequest;
use App\Http\Requests\RequestApoyoProfesoral\RequestEscalafon\RevertirEscalonRequest;
use App\Models\HistorialEscalonDocente;
use App\Models\PeriodoAscenso;
use App\Models\Usuario\User;
use App\Services\AscensoEscalafonService;
use App\Services\MotorEscalafonDocenteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Bandeja de ascensos del escalafón docente y los actos que la acompañan.
 *
 * Es la contrapartida del cambio de fondo del nuevo reglamento: el ascenso dejó de ser algo que el
 * motor otorgaba solo cuando el docente pedía su evaluación y pasó a ser un acto administrativo.
 * Aquí se ve quién es elegible, se ejecutan los ascensos y se revierten los que hubo que deshacer.
 *
 * **Lo usan dos roles.** Vive en el espacio de Apoyo Profesoral porque es quien lo estrenó, pero
 * `routes/admin.php` monta las mismas acciones bajo `/admin/escalafon` para el Administrador. Es el
 * mismo acto con las mismas reglas —se revalida contra el motor, se exige periodo cerrado y se firma
 * con el ejecutor— así que tener dos copias solo garantizaría que se separen con el tiempo. Al
 * escribir aquí, tener presente que el `$request->user()` puede ser cualquiera de los dos.
 *
 * Lo que **no** está aquí y es exclusivo del Administrador son las correcciones del expediente
 * —ingreso manual y edición de tramos—, que viven en `Admin\EscalafonHistorialController`.
 * Separadas físicamente a propósito: si estuvieran en esta clase, bastaría un `Route::post()`
 * escrito por descuido en `routes/apoyo_profesoral.php` para dárselas a quien no debe tenerlas.
 *
 * El ingreso ordinario al escalafón no es un acto de nadie: `ContratacionObserver` mete al docente
 * en el primer escalón en cuanto Talento Humano le registra la contratación de planta.
 *
 * La administración de los escalones y sus requisitos NO vive aquí: eso es del rol Administrador
 * (`Admin\EscalonDocenteController`). Este controlador aplica las reglas, no las define.
 */
class EscalafonDocenteController
{
    public function __construct(
        private readonly MotorEscalafonDocenteService $motor,
        private readonly AscensoEscalafonService $ascensos,
    ) {
    }

    // ---------------------------------------------------------------
    // Periodos de ascenso
    // ---------------------------------------------------------------

    public function listarPeriodos()
    {
        try {
            $periodos = PeriodoAscenso::orderByDesc('fecha_cierre')->get()
                ->map(fn (PeriodoAscenso $periodo) => array_merge(
                    $periodo->only(['id_periodo_ascenso', 'nombre', 'fecha_cierre', 'cerrado_en']),
                    ['cerrado' => $periodo->estaCerrado()]
                ));

            return response()->json(['status' => 'success', 'data' => $periodos], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar los periodos de ascenso: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al listar los periodos de ascenso.',
            ], 500);
        }
    }

    public function crearPeriodo(CrearPeriodoAscensoRequest $request)
    {
        try {
            $periodo = PeriodoAscenso::create(array_merge(
                $request->validated(),
                ['creado_por' => $request->user()->id]
            ));

            return response()->json([
                'status' => 'success',
                'message' => 'Periodo de ascenso creado correctamente.',
                'data' => $periodo,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear el periodo de ascenso: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear el periodo de ascenso.',
            ], 500);
        }
    }

    public function actualizarPeriodo(ActualizarPeriodoAscensoRequest $request, $id)
    {
        try {
            $periodo = $this->buscarPeriodo($id);

            if (!$periodo) {
                return response()->json(['status' => 'error', 'message' => 'Periodo de ascenso no encontrado.'], 404);
            }

            // Un periodo cerrado es el corte con el que ya se evaluaron —y posiblemente otorgaron—
            // ascensos reales. Moverle la fecha cambiaría retroactivamente el expediente con el que
            // se tomaron esas decisiones.
            if ($periodo->estaCerrado()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede modificar un periodo que ya cerró: su fecha de cierre es el corte con el que se evaluaron los ascensos.',
                ], 409);
            }

            $periodo->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Periodo de ascenso actualizado correctamente.',
                'data' => $periodo->fresh(),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al actualizar el periodo de ascenso: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al actualizar el periodo de ascenso.',
            ], 500);
        }
    }

    /**
     * Cierre anticipado.
     *
     * Marca `cerrado_en` pero NO toca `fecha_cierre`: el corte de los requisitos sigue siendo la
     * fecha anunciada. Adelantar el cierre administrativo no puede cambiarle el expediente a nadie
     * que ya contaba con presentar documentos hasta esa fecha.
     */
    public function cerrarPeriodo($id)
    {
        try {
            $periodo = $this->buscarPeriodo($id);

            if (!$periodo) {
                return response()->json(['status' => 'error', 'message' => 'Periodo de ascenso no encontrado.'], 404);
            }

            if ($periodo->estaCerrado()) {
                return response()->json(['status' => 'error', 'message' => 'Este periodo ya está cerrado.'], 409);
            }

            $periodo->update(['cerrado_en' => now()]);

            return response()->json([
                'status' => 'success',
                'message' => 'Periodo de ascenso cerrado.',
                'data' => $periodo->fresh(),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al cerrar el periodo de ascenso: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al cerrar el periodo de ascenso.',
            ], 500);
        }
    }

    // ---------------------------------------------------------------
    // Bandeja
    // ---------------------------------------------------------------

    /**
     * Docentes del escalafón con su escalón vigente, su antigüedad y si son elegibles para ascender.
     *
     * Solo los que están **dentro** del escalafón, que son los de planta: `enEscalafon()` filtra por
     * tramo abierto. Antes salían todos los docentes, y los de cátedra u ocasionales llenaban la
     * bandeja de filas sin categoría y sin ascenso posible.
     *
     * `?estado_antiguedad=` filtra por el semáforo (ver las constantes de
     * `MotorEscalafonDocenteService`), que es lo que evita revisar estudios e idiomas de quien
     * todavía no tiene los años. `?periodo_ascenso_id=` permite mirar contra un periodo distinto
     * del que se usa por defecto.
     */
    public function listarDocentes(Request $request)
    {
        try {
            $periodo = $this->periodoDeConsulta($request);

            $docentes = User::role('Docente')
                ->enEscalafon()
                ->with($this->relacionesParaEvaluar())
                ->get();

            $filtro = $request->query('estado_antiguedad');

            $data = $docentes
                ->map(function (User $docente) use ($periodo) {
                    $resultado = $this->motor->evaluarAscenso($docente, $periodo);

                    return array_merge([
                        'id' => $docente->id,
                        'nombre_completo' => $this->nombreCompleto($docente),
                        'email' => $docente->email,
                        'numero_identificacion' => $docente->numero_identificacion,
                    ], $resultado);
                })
                ->when($filtro, fn ($lista) => $lista->where('estado_antiguedad', $filtro))
                ->values();

            return response()->json(['status' => 'success', 'data' => $data], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar la bandeja de escalafón: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al listar los docentes del escalafón.',
            ], 500);
        }
    }

    /**
     * Detalle de un docente: su evaluación de ascenso más el historial completo de sus tramos.
     *
     * Comprueba el rol igual que lo hacen la bandeja (`User::role('Docente')`) y el ingreso manual:
     * el escalafón es de los docentes, y sin el filtro esta ruta contestaba con el nombre de
     * cualquier usuario del sistema —el propio Administrador incluido— y una evaluación fabricada
     * sobre una ficha que no significa nada.
     */
    public function verDocente(Request $request, $userId)
    {
        try {
            if (!$this->idDeUsuarioValido($userId)) {
                return response()->json(['status' => 'error', 'message' => 'Docente no encontrado.'], 404);
            }

            $docente = User::with($this->relacionesParaEvaluar())->find($userId);

            if (!$docente || !$docente->hasRole('Docente')) {
                return response()->json(['status' => 'error', 'message' => 'Docente no encontrado.'], 404);
            }

            $periodo = $this->periodoDeConsulta($request);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $docente->id,
                    'nombre_completo' => $this->nombreCompleto($docente),
                    'evaluacion' => $this->motor->evaluarAscenso($docente, $periodo),
                    // Se muestran también los tramos revertidos: el expediente tiene que dejar ver
                    // que un ascenso existió y se deshizo, no solo el estado final.
                    'historial' => $docente->historialEscalonUsuario->map(fn (HistorialEscalonDocente $tramo) => [
                        'id_historial_escalon' => $tramo->id_historial_escalon,
                        'escalon' => $tramo->escalon?->nombre,
                        'desde' => $tramo->desde?->toDateString(),
                        'hasta' => $tramo->hasta?->toDateString(),
                        'via' => $tramo->via,
                        'motivo' => $tramo->motivo,
                        'otorgado_por' => $tramo->otorgante?->email,
                        'revertido_en' => $tramo->revertido_en?->toDateTimeString(),
                        'revertido_por' => $tramo->revisorReversion?->email,
                        'motivo_reversion' => $tramo->motivo_reversion,
                        // Un tramo corregido a mano ya no es exactamente lo que produjo el acto
                        // original. El detalle de qué cambió está en
                        // `GET /admin/escalafon/docentes/{userId}/bitacora`.
                        'corregido' => ($tramo->bitacoras_count ?? 0) > 0,
                    ])->values(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener el escalafón del docente: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener el escalafón del docente.',
            ], 500);
        }
    }

    // ---------------------------------------------------------------
    // Actos
    // ---------------------------------------------------------------

    // El ingreso al escalafón no se expone: no es un acto de Apoyo Profesoral. `ContratacionObserver`
    // mete al docente en el primer escalón en cuanto su contratación lo acredita como de planta, así
    // que aquí solo quedan el ascenso y la reversión, que sí requieren una firma.

    public function ascender(AscenderEscalonRequest $request, $userId)
    {
        try {
            if (!$this->idDeUsuarioValido($userId)) {
                return response()->json(['status' => 'error', 'message' => 'Docente no encontrado.'], 404);
            }

            $docente = User::find($userId);

            if (!$docente) {
                return response()->json(['status' => 'error', 'message' => 'Docente no encontrado.'], 404);
            }

            $periodo = PeriodoAscenso::findOrFail($request->validated('periodo_ascenso_id'));

            $tramo = $this->ascensos->ascender(
                $docente,
                $periodo,
                $request->user(),
                $request->validated('motivo')
            );

            return response()->json([
                'status' => 'success',
                'message' => "Ascenso registrado: el docente queda como {$tramo->escalon->nombre}.",
                'data' => $tramo->load('escalon:id_escalon,nombre'),
            ], 201);
        } catch (AscensoEscalafonException $e) {
            // El contexto lleva el desglose por requisito y la fecha de corte cuando el rechazo es
            // por expediente. Va aparte del mensaje para que la pantalla pueda listarlo en vez de
            // tener que interpretar una frase.
            return response()->json(array_merge(
                ['status' => 'error', 'message' => $e->getMessage()],
                $e->contexto()
            ), 409);
        } catch (\Exception $e) {
            Log::error('Error al ejecutar el ascenso: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al ejecutar el ascenso.',
            ], 500);
        }
    }

    public function revertir(RevertirEscalonRequest $request, $id)
    {
        try {
            // `historial_escalon_docente` usa bigIncrements, así que no aplica el tope de
            // `ClavePrimaria::fueraDeRango()`, pensado para las claves `smallint` de los catálogos.
            if (!is_numeric($id) || $id < 1) {
                return response()->json(['status' => 'error', 'message' => 'Tramo de escalafón no encontrado.'], 404);
            }

            $tramo = HistorialEscalonDocente::with('escalon', 'docente')->find($id);

            if (!$tramo) {
                return response()->json(['status' => 'error', 'message' => 'Tramo de escalafón no encontrado.'], 404);
            }

            $this->ascensos->revertir($tramo, $request->user(), $request->validated('motivo'));

            $vigente = $this->motor->escalonVigente($tramo->docente->fresh()->load('historialEscalonUsuario.escalon'));

            return response()->json([
                'status' => 'success',
                'message' => $vigente
                    ? "Acto revertido: el docente queda como {$vigente->nombre}."
                    : 'Acto revertido: el docente queda fuera del escalafón.',
            ], 200);
        } catch (AscensoEscalafonException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 409);
        } catch (\Exception $e) {
            Log::error('Error al revertir el acto de escalafón: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al revertir el acto de escalafón.',
            ], 500);
        }
    }

    // ---------------------------------------------------------------
    // Interno
    // ---------------------------------------------------------------

    private function buscarPeriodo($id): ?PeriodoAscenso
    {
        return ClavePrimaria::fueraDeRango($id) ? null : PeriodoAscenso::find($id);
    }

    /**
     * ¿Puede este ID de ruta corresponder a algún usuario?
     *
     * `users.id` es `bigIncrements`, así que no aplica el tope de `ClavePrimaria::fueraDeRango()`,
     * pensado para las claves `smallint` de los catálogos. Lo que sí hay que descartar es lo que la
     * columna no sabe comparar: un `find('abc')` contra un `bigint` aborta la consulta con
     * SQLSTATE 22P02 y la excepción salía por el `catch` genérico como un 500 en vez del 404 que
     * corresponde. Mismo criterio que `revertir()`.
     */
    private function idDeUsuarioValido($id): bool
    {
        return is_numeric($id) && $id >= 1;
    }

    /**
     * Periodo contra el que se muestra la bandeja.
     *
     * Por defecto el vigente —lo que da una proyección: "así quedaría el expediente al cierre"— y,
     * si no hay ninguno abierto, el último que cerró, que es el que Apoyo Profesoral está
     * procesando en ese momento.
     */
    private function periodoDeConsulta(Request $request): ?PeriodoAscenso
    {
        $id = $request->query('periodo_ascenso_id');

        if ($id !== null && !ClavePrimaria::fueraDeRango($id)) {
            return PeriodoAscenso::find($id);
        }

        return PeriodoAscenso::vigente() ?? PeriodoAscenso::ultimo();
    }

    /** Todo lo que consume el motor, cargado de una vez para no disparar consultas N+1. */
    private function relacionesParaEvaluar(): array
    {
        return [
            'estudiosUsuario.documentosEstudio',
            'idiomasUsuario.documentosIdioma',
            'experienciasUsuario.documentosExperiencia',
            'produccionAcademicaUsuario.documentosProduccionAcademica',
            'evaluacionDocenteUsuario',
            // El conteo de bitácora es lo que permite marcar un tramo como corregido a mano. Se
            // carga también para Apoyo Profesoral, que es precisamente quien necesita enterarse de
            // que el Administrador tocó un expediente que él está evaluando.
            'historialEscalonUsuario' => fn ($query) => $query->withCount('bitacoras'),
            'historialEscalonUsuario.escalon',
            'historialEscalonUsuario.otorgante:id,email',
            'historialEscalonUsuario.revisorReversion:id,email',
        ];
    }

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
