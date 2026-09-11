<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AscensoEscalafonException;
use App\Http\Requests\RequestAdmin\RequestEscalafonHistorial\CorregirTramoEscalafonRequest;
use App\Http\Requests\RequestAdmin\RequestEscalafonHistorial\IngresoManualEscalafonRequest;
use App\Models\EscalonDocente;
use App\Models\HistorialEscalonBitacora;
use App\Models\HistorialEscalonDocente;
use App\Models\PeriodoAscenso;
use App\Models\Usuario\User;
use App\Services\AscensoEscalafonService;
use App\Services\MotorEscalafonDocenteService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Intervenciones manuales del Administrador sobre `historial_escalon_docente`.
 *
 * Es lo único de esta área que **no** comparte con Apoyo Profesoral. Los periodos, la bandeja, el
 * ascenso y la reversión son el mismo acto para los dos roles y los atiende el mismo controlador
 * (`ApoyoProfesoral\EscalafonDocenteController`, montado también bajo `/admin`). Lo que vive aquí
 * son las dos capacidades que solo tiene el Administrador, y están en una clase aparte a propósito:
 * si estuvieran junto a los métodos compartidos, bastaría un `Route::post()` escrito por descuido en
 * `routes/apoyo_profesoral.php` para abrirlas a un rol que no debe tenerlas. La barrera de
 * autorización de este proyecto es la ruta, y la ruta es justo lo más fácil de añadir sin pensar.
 *
 * No confundir con `Admin\EscalonDocenteController`, que administra el **catálogo** de escalones y
 * sus requisitos. Aquel define las reglas; este corrige el expediente de un docente concreto.
 *
 * Todo lo que se escribe aquí queda en `historial_escalon_bitacoras`, con motivo obligatorio y
 * snapshot antes/después. Ver `AscensoEscalafonService::corregirTramo()` para las invariantes.
 */
class EscalafonHistorialController
{
    public function __construct(
        private readonly AscensoEscalafonService $ascensos,
        private readonly MotorEscalafonDocenteService $motor,
    ) {
    }

    /**
     * Registra el ingreso de un docente al escalafón en el escalón y la fecha indicados.
     *
     * El ingreso ordinario lo dispara `ContratacionObserver` y siempre entra por el primer escalón.
     * Esta ruta cubre lo que aquel no sabe hacer: el docente que llega con una categoría ya
     * reconocida, el reingreso tras una reversión, y la carga de expedientes anteriores al sistema.
     * Sigue exigiendo contratación de planta vigente, que es lo que acredita el ingreso.
     */
    public function ingresarManual(IngresoManualEscalafonRequest $request, $userId)
    {
        try {
            $docente = User::find($userId);

            if (!$docente) {
                return response()->json(['status' => 'error', 'message' => 'Docente no encontrado.'], 404);
            }

            $datos = $request->validated();
            $escalon = EscalonDocente::findOrFail($datos['escalon_id']);

            $tramo = $this->ascensos->ingresarManual(
                $docente,
                $escalon,
                Carbon::parse($datos['desde']),
                $request->user(),
                $datos['motivo']
            );

            return response()->json([
                'status' => 'success',
                'message' => "Ingreso manual registrado: el docente queda como {$escalon->nombre}.",
                'data' => $this->tramoComoArray($tramo->load('escalon', 'otorgante:id,email')),
            ], 201);
        } catch (AscensoEscalafonException $e) {
            return response()->json(array_merge(
                ['status' => 'error', 'message' => $e->getMessage()],
                $e->contexto()
            ), 409);
        } catch (\Exception $e) {
            Log::error('Error al registrar el ingreso manual al escalafón: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al registrar el ingreso manual al escalafón.',
            ], 500);
        }
    }

    /**
     * Corrige el escalón y/o las fechas de un tramo ya registrado.
     *
     * La respuesta lleva un bloque `impacto` con la antigüedad y el puntaje antes y después, porque
     * las consecuencias de mover una fecha no son evidentes desde el formulario: adelantar `desde`
     * descarta producción académica que hasta ese momento puntuaba, y retrasarlo puede no dar ni un
     * mes de antigüedad si no hay experiencia uniautónoma documentada que cubra ese periodo —el
     * motor **intersecta** las dos series, no se queda con el historial—. Verlo en la respuesta
     * evita la corrección repetida a ciegas.
     */
    public function corregir(CorregirTramoEscalafonRequest $request, $id)
    {
        try {
            $tramo = $this->buscarTramo($id);

            if (!$tramo) {
                return response()->json(['status' => 'error', 'message' => 'Tramo de escalafón no encontrado.'], 404);
            }

            $corte = $this->corteDeConsulta();
            $antes = $this->fotoDelExpediente($tramo->docente, $corte);

            $this->ascensos->corregirTramo(
                $tramo,
                $request->validated(),
                $request->user(),
                $request->validated('motivo')
            );

            $docente = $tramo->docente->fresh();
            $despues = $this->fotoDelExpediente($docente, $corte);

            return response()->json([
                'status' => 'success',
                'message' => $this->resumenDelCambio($antes, $despues),
                'data' => [
                    'tramo' => $this->tramoComoArray($tramo->fresh()->load('escalon', 'otorgante:id,email')),
                    'escalon_vigente' => $despues['escalon_vigente'],
                    'impacto' => [
                        'escalon_vigente' => ['antes' => $antes['escalon_vigente'], 'despues' => $despues['escalon_vigente']],
                        'meses_en_escalon' => ['antes' => $antes['meses_en_escalon'], 'despues' => $despues['meses_en_escalon']],
                        'puntaje_total' => ['antes' => $antes['puntaje_total'], 'despues' => $despues['puntaje_total']],
                        // Se devuelve la fecha para poder decirla en pantalla: son cifras medidas
                        // a un corte, no «ahora mismo», y sin nombrarlo no se pueden comparar con
                        // nada.
                        'fecha_corte' => $corte->toDateString(),
                    ],
                    // La cadena completa resultante: una corrección puede dejar un orden de
                    // escalones que sorprenda, y es más honesto enseñarlo que prohibirlo.
                    'historial' => $this->historialComoArray($docente),
                ],
            ], 200);
        } catch (AscensoEscalafonException $e) {
            return response()->json(array_merge(
                ['status' => 'error', 'message' => $e->getMessage()],
                $e->contexto()
            ), 409);
        } catch (\Exception $e) {
            Log::error('Error al corregir el tramo de escalafón: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al corregir el tramo de escalafón.',
            ], 500);
        }
    }

    /**
     * Bitácora de intervenciones manuales sobre el historial de un docente.
     *
     * Va por docente y no por tramo porque la pregunta que se hace quien audita un expediente es
     * "¿qué se ha tocado a mano aquí?", y un tramo corregido puede además haber desaparecido.
     * Los ascensos y las reversiones no salen aquí: van firmados en el propio tramo y los devuelve
     * `GET /admin/escalafon/docentes/{userId}`.
     */
    public function bitacora($userId)
    {
        try {
            $docente = User::find($userId);

            if (!$docente) {
                return response()->json(['status' => 'error', 'message' => 'Docente no encontrado.'], 404);
            }

            $bitacora = HistorialEscalonBitacora::where('docente_id', $docente->id)
                ->with('usuarioQueModifico:id,email')
                ->orderByDesc('created_at')
                ->orderByDesc('id_bitacora')
                ->get()
                ->map(fn (HistorialEscalonBitacora $fila) => [
                    'id_bitacora' => $fila->id_bitacora,
                    'historial_escalon_id' => $fila->historial_escalon_id,
                    'tipo_modificacion' => $fila->tipo_modificacion,
                    'datos_anteriores' => $fila->datos_anteriores,
                    'datos_nuevos' => $fila->datos_nuevos,
                    'motivo' => $fila->motivo,
                    'modificado_por' => $fila->usuarioQueModifico?->email,
                    'fecha' => $fila->created_at?->toDateTimeString(),
                ]);

            return response()->json(['status' => 'success', 'data' => $bitacora], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener la bitácora del escalafón: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener la bitácora del escalafón.',
            ], 500);
        }
    }

    // ---------------------------------------------------------------
    // Interno
    // ---------------------------------------------------------------

    /**
     * `historial_escalon_docente` usa bigIncrements, así que no aplica el tope de
     * `ClavePrimaria::fueraDeRango()`, pensado para las claves `smallint` de los catálogos. Mismo
     * criterio que `EscalafonDocenteController::revertir()`.
     */
    private function buscarTramo($id): ?HistorialEscalonDocente
    {
        if (!is_numeric($id) || $id < 1) {
            return null;
        }

        return HistorialEscalonDocente::with('escalon', 'docente')->find($id);
    }

    /**
     * Fecha contra la que se mide el impacto de una corrección.
     *
     * Es la misma que usa la bandeja y el detalle del docente —el periodo vigente, o el último que
     * cerró— y no `now()`, aunque `now()` fuera más barato de calcular. El operador está mirando
     * una pantalla que dice «4 meses» medidos al cierre del periodo: si el impacto respondiera «0
     * → 3» medido a hoy, los dos números correctos se contradirían en la misma ventana y ninguno
     * de los dos serviría para decidir.
     */
    private function corteDeConsulta(): Carbon
    {
        $periodo = PeriodoAscenso::vigente() ?? PeriodoAscenso::ultimo();

        return $periodo ? $periodo->fecha_cierre->copy()->endOfDay() : now();
    }

    /**
     * Los tres números que una corrección puede mover.
     *
     * No se usa `evaluarAscenso()`: recorre el expediente entero y se llama a sí mismo una segunda
     * vez por su bandera `$compararConHoy`, y aquí solo hacen falta tres cifras. Se piden por
     * separado al motor, contra el mismo corte.
     *
     * El puntaje tampoco se lee de la caché `puntajes`: esa se calcula siempre contra hoy —así la
     * escribe `sincronizarCache()`— y volvería a mezclar dos cortes distintos en la misma tarjeta.
     */
    private function fotoDelExpediente(User $docente, Carbon $corte): array
    {
        $docente->load(['historialEscalonUsuario.escalon', 'experienciasUsuario.documentosExperiencia']);

        $tramo = $this->motor->tramoVigente($docente);
        $escalon = $tramo?->escalon;

        return [
            'escalon_vigente' => $escalon?->nombre,
            'meses_en_escalon' => $escalon
                ? $this->motor->mesesEnEscalon($docente, $escalon, $corte)
                : 0,
            // La ventana de producción arranca en el `desde` del tramo vigente: es lo que
            // implementa que el puntaje no se acumule entre escalones.
            'puntaje_total' => $tramo
                ? $this->motor->calcularPuntaje($docente, $tramo->desde->copy()->startOfDay(), $corte)
                : 0,
        ];
    }

    /**
     * Dice en voz alta cuando la corrección cambió la categoría vigente.
     *
     * Corregir el escalón del tramo abierto es, de hecho, otorgar una categoría sin pasar por el
     * motor ni por un periodo de ascenso. Es una capacidad legítima del Administrador —es como se
     * arregla un expediente mal cargado— pero no debe pasar desapercibida en la respuesta.
     */
    private function resumenDelCambio(array $antes, array $despues): string
    {
        if ($antes['escalon_vigente'] === $despues['escalon_vigente']) {
            return 'Tramo corregido. La categoría vigente del docente no cambia.';
        }

        $anterior = $antes['escalon_vigente'] ?? 'sin escalón';
        $nuevo = $despues['escalon_vigente'] ?? 'sin escalón';

        return "Tramo corregido. La categoría vigente del docente pasó de {$anterior} a {$nuevo} "
            . 'sin evaluación del motor ni periodo de ascenso.';
    }

    private function historialComoArray(User $docente): array
    {
        return $docente->load('historialEscalonUsuario.escalon')
            ->historialEscalonUsuario
            ->map(fn (HistorialEscalonDocente $tramo) => $this->tramoComoArray($tramo))
            ->values()
            ->all();
    }

    private function tramoComoArray(HistorialEscalonDocente $tramo): array
    {
        return [
            'id_historial_escalon' => $tramo->id_historial_escalon,
            'escalon' => $tramo->escalon?->nombre,
            'desde' => $tramo->desde?->toDateString(),
            'hasta' => $tramo->hasta?->toDateString(),
            'via' => $tramo->via,
            'motivo' => $tramo->motivo,
            'otorgado_por' => $tramo->otorgante?->email,
            'revertido_en' => $tramo->revertido_en?->toDateTimeString(),
        ];
    }
}
