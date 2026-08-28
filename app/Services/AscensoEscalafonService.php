<?php

namespace App\Services;

use App\Constants\ConstTalentoHumano\TipoContratacion;
use App\Exceptions\AscensoEscalafonException;
use App\Http\Controllers\TalentoHumano\NotificacionController;
use App\Models\EscalonDocente;
use App\Models\HistorialEscalonDocente;
use App\Models\PeriodoAscenso;
use App\Models\TalentoHumano\Contratacion;
use App\Models\Usuario\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ejecuta lo que mueve a un docente en el escalafón: ingreso, ascenso y reversión.
 *
 * Es el **único** camino que escribe `historial_escalon_docente`. Antes no existía nada parecido:
 * la categoría la otorgaba el motor cuando el propio docente pedía su evaluación
 * (`GET /docente/evaluar-puntaje`), así que el docente se autoascendía y una categoría se caía sola
 * si el Administrador subía un requisito. El nuevo reglamento exige un acto de Apoyo Profesoral, y
 * eso es lo que hace esta clase.
 *
 * Con una excepción deliberada: el **ingreso no es un acto de nadie**. Lo dispara la contratación
 * de planta a través de `ContratacionObserver`, sin firma y sin formulario, porque ser docente de
 * planta ya es la decisión. Ascender y revertir sí quedan firmados por quien los ejecuta.
 *
 * Mismo patrón que `AvalProduccionService`: la decisión se aplica dentro de una transacción y la
 * notificación al docente es best-effort —un fallo del correo no puede tumbar un acto que ya está
 * escrito y confirmado en base de datos.
 */
class AscensoEscalafonService
{
    public function __construct(private MotorEscalafonDocenteService $motor)
    {
    }

    /**
     * Mete al docente al escalafón si su contratación lo acredita como de planta.
     *
     * **No lo ejecuta nadie.** Lo dispara `ContratacionObserver` cada vez que se crea o se edita una
     * contratación: ser docente de planta *es* el ingreso al escalafón, así que no tiene sentido que
     * además alguien tenga que registrarlo a mano. Antes había un formulario de Apoyo Profesoral
     * (`POST /escalafon/docentes/{id}/ingreso`) y con él la posibilidad de que un docente de planta
     * se quedara indefinidamente fuera del escalafón porque nadie pulsó el botón, sin que ninguna
     * pantalla lo delatara.
     *
     * Por eso **no lanza excepciones ni devuelve errores**: no hay un usuario esperando una
     * respuesta. Cuando no procede —no es de planta, o ya está dentro— devuelve `null` y no toca
     * nada. Es idempotente a propósito: una renovación de contrato vuelve a dispararlo y no puede
     * abrir un segundo tramo ni mover el `desde` del que ya existe.
     *
     * El escalón **no se elige**: todo docente entra por el primero del escalafón (hoy, Auxiliar) y
     * sube desde ahí por ascensos. No pasa por un periodo de ascenso: no es un ascenso, es el punto
     * de partida. Sin este tramo el motor no tiene contra qué medir la antigüedad ni desde cuándo
     * contar la producción, así que un docente sin él no es elegible para nada.
     *
     * `otorgado_por` queda en null, que es lo que significa "esto no lo firmó nadie". El `motivo`
     * lo dice en palabras para quien lea el expediente.
     *
     * @return HistorialEscalonDocente|null El tramo creado, o null si el docente no era candidato.
     */
    public function ingresar(User $docente): ?HistorialEscalonDocente
    {
        return DB::transaction(function () use ($docente) {
            if ($this->tramoAbierto($docente)) {
                return null;
            }

            $desde = $this->inicioComoPlanta($docente);

            if ($desde === null) {
                return null;
            }

            $escalon = $this->motor->escalonInicial();

            if (!$escalon) {
                // Catálogo mal configurado. No es culpa de la contratación que se acaba de guardar,
                // así que no se tumba: queda el rastro para que el Administrador lo corrija.
                Log::error(
                    "No hay ningún escalón activo: el docente {$docente->id} no pudo ingresar al escalafón "
                    . 'pese a tener contratación de planta vigente.'
                );

                return null;
            }

            $tramo = HistorialEscalonDocente::create([
                'user_id' => $docente->id,
                'escalon_id' => $escalon->id_escalon,
                'periodo_ascenso_id' => null,
                'desde' => $desde,
                'hasta' => null,
                'via' => HistorialEscalonDocente::VIA_INGRESO,
                'motivo' => 'Ingreso automático al registrarse la contratación de planta.',
                'otorgado_por' => null,
            ]);

            $this->sincronizarCache($docente, $escalon->nombre);

            return $tramo;
        });
    }

    /**
     * Asciende al docente al escalón que le corresponde según el periodo indicado.
     *
     * El periodo tiene que estar **cerrado**: los requisitos se congelan en su `fecha_cierre`, así
     * que ascender antes sería otorgar sobre una proyección —antigüedad que todavía no se ha
     * cumplido, producción que aún puede llegar—. Mientras el periodo sigue abierto, la bandeja y
     * la consulta del docente muestran precisamente esa proyección, que es información útil, pero
     * no un acto ejecutable.
     *
     * La elegibilidad se **revalida aquí dentro**, contra la base de datos y no contra lo que vio
     * la bandeja: entre que Apoyo Profesoral cargó la lista y pulsó el botón, a otro funcionario le
     * pudo dar tiempo de rechazar un documento.
     *
     * @throws AscensoEscalafonException si el periodo sigue abierto o el docente no es elegible.
     */
    public function ascender(
        User $docente,
        PeriodoAscenso $periodo,
        User $ejecutor,
        ?string $motivo = null
    ): HistorialEscalonDocente {
        return DB::transaction(function () use ($docente, $periodo, $ejecutor, $motivo) {
            if (!$periodo->estaCerrado()) {
                throw new AscensoEscalafonException(
                    "El periodo «{$periodo->nombre}» todavía no ha cerrado (cierra el {$periodo->fecha_cierre->toDateString()}). "
                    . 'Los ascensos se ejecutan una vez pasada la fecha de cierre.'
                );
            }

            $docente = $this->recargarParaEvaluar($docente);
            $resultado = $this->motor->evaluarAscenso($docente, $periodo);

            if (!$resultado['elegible']) {
                // El detalle por criterio —qué se pedía, qué tiene— ya está calculado; sin pasarlo
                // aquí, la pantalla solo recibiría la frase resumen y no podría mostrar el desglose.
                throw new AscensoEscalafonException($resultado['razon'], [
                    'escalon_vigente' => $resultado['escalon_vigente'],
                    'escalon_objetivo' => $resultado['escalon_objetivo'],
                    'fecha_corte' => $resultado['fecha_corte'],
                    'faltantes' => $resultado['faltantes'],
                ]);
            }

            $abierto = $this->tramoAbierto($docente);

            if (!$abierto) {
                throw new AscensoEscalafonException('El docente no tiene un escalón vigente registrado.');
            }

            $objetivo = EscalonDocente::activos()->where('nombre', $resultado['escalon_objetivo'])->firstOrFail();

            // El tramo anterior cierra y el nuevo arranca **el mismo día de la fecha de cierre**, no
            // el día en que se firma. Así la ventana de producción del escalón nuevo empieza justo
            // donde termina la del anterior, sin huecos por los que se pierda un producto ni
            // traslapes que lo dejen contar dos veces.
            $abierto->update(['hasta' => $periodo->fecha_cierre->toDateString()]);

            $tramo = HistorialEscalonDocente::create([
                'user_id' => $docente->id,
                'escalon_id' => $objetivo->id_escalon,
                'periodo_ascenso_id' => $periodo->id_periodo_ascenso,
                'desde' => $periodo->fecha_cierre->toDateString(),
                'hasta' => null,
                'via' => $resultado['via'],
                'motivo' => $motivo,
                'otorgado_por' => $ejecutor->id,
            ]);

            $this->sincronizarCache($docente, $objetivo->nombre);
            $this->notificarAscenso($docente, $objetivo->nombre);

            return $tramo;
        });
    }

    /**
     * Deshace un acto: marca el tramo como revertido y reabre el anterior.
     *
     * El tramo **no se borra**. Un ascenso que se otorgó y luego se deshizo tiene que seguir
     * visible en el expediente junto con quién lo revirtió y por qué; borrarlo dejaría el historial
     * contando una versión de los hechos que no ocurrió.
     *
     * Rechazar el documento que sostenía un requisito no degrada al docente por sí solo: hace falta
     * este acto. Es la contrapartida de que el ascenso también sea un acto.
     *
     * @throws AscensoEscalafonException si el tramo ya estaba revertido.
     */
    public function revertir(HistorialEscalonDocente $tramo, User $ejecutor, string $motivo): HistorialEscalonDocente
    {
        return DB::transaction(function () use ($tramo, $ejecutor, $motivo) {
            if ($tramo->estaRevertido()) {
                throw new AscensoEscalafonException('Este tramo del escalafón ya estaba revertido.');
            }

            $tramo->update([
                'revertido_en' => now(),
                'revertido_por' => $ejecutor->id,
                'motivo_reversion' => $motivo,
            ]);

            // El tramo que estaba vigente antes de este: el de `hasta` más reciente entre los que
            // siguen contando. Se reabre para que el docente vuelva a donde estaba.
            $anterior = HistorialEscalonDocente::where('user_id', $tramo->user_id)
                ->whereKeyNot($tramo->getKey())
                ->noRevertidos()
                ->whereNotNull('hasta')
                ->orderByDesc('hasta')
                ->orderByDesc('id_historial_escalon')
                ->first();

            $anterior?->update(['hasta' => null]);

            $docente = $tramo->docente;
            $this->sincronizarCache($docente, $anterior?->escalon?->nombre);
            $this->notificarReversion($docente, $tramo->escalon?->nombre, $anterior?->escalon?->nombre, $motivo, $ejecutor);

            return $tramo->refresh();
        });
    }

    // ---------------------------------------------------------------
    // Interno
    // ---------------------------------------------------------------

    /**
     * ¿Desde cuándo es docente de planta? Null si hoy no lo es.
     *
     * Responde de una vez las dos preguntas del ingreso —si procede y con qué fecha— porque salen
     * de la misma consulta.
     *
     * **Si procede** se decide contra **hoy**: tiene que haber una contratación de planta vigente
     * ahora mismo. El escalafón aplica solo a docentes de planta, y este es el único punto del
     * sistema donde esa regla se exige. En el motor estuvo (`evaluar()` cortaba si `tipo_contrato`
     * no era 'planta') y se quitó con razón: dejaba a cualquier docente sin contrato cargado con
     * categoría "Ninguna" y puntaje 0 sin mirar un requisito. Consultar el propio avance no puede
     * depender de que Talento Humano tenga el papeleo al día; entrar al escalafón sí, porque es
     * precisamente la contratación la que lo acredita.
     *
     * **La fecha** es la `fecha_inicio` más antigua entre sus contratos de planta, no la del que
     * esté vigente hoy. Una vinculación de años se registra como una cadena de contratos renovados,
     * y quedarse con el último borraría de un plumazo la antigüedad acumulada: un docente de planta
     * desde 2015 con el contrato renovado en 2025 volvería a arrancar de cero. La contrapartida es
     * que a quien fue planta, se fue y volvió se le cuenta también el hueco; se acepta porque va en
     * la dirección que favorece al docente y porque los tramos del historial ya se acumulan igual
     * pese a las interrupciones.
     *
     * Ojo con `fecha_fin`: la columna es NOT NULL, así que no existe el contrato indefinido. Un
     * docente de planta cuya renovación nadie registró deja de ser candidato hasta que Talento
     * Humano actualice el contrato —y como ya está dentro para entonces, eso no lo saca del
     * escalafón: solo la reversión, que sí es un acto firmado. El `whereNull` está por si la columna
     * se vuelve nullable más adelante.
     */
    private function inicioComoPlanta(User $docente): ?string
    {
        $hoy = now()->toDateString();

        $plantas = Contratacion::where('user_id', $docente->id)
            // Case-insensitive como lo hacía la guarda vieja del motor: `tipo_contrato` es un string
            // libre en base de datos, no una FK a un catálogo.
            ->whereRaw('LOWER(TRIM(tipo_contrato)) = ?', [mb_strtolower(TipoContratacion::PLANTA)])
            // Un contrato que todavía no arranca no acredita nada, ni para entrar ni como fecha.
            ->where('fecha_inicio', '<=', $hoy);

        $vigente = (clone $plantas)
            ->where(fn ($query) => $query->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $hoy))
            ->exists();

        if (!$vigente) {
            return null;
        }

        $inicio = $plantas->min('fecha_inicio');

        return $inicio ? Carbon::parse($inicio)->toDateString() : null;
    }

    private function tramoAbierto(User $docente): ?HistorialEscalonDocente
    {
        return HistorialEscalonDocente::where('user_id', $docente->id)
            ->vigentes()
            ->lockForUpdate()
            ->first();
    }

    /**
     * Recarga al docente con todo lo que consume el motor, para que la revalidación mire la base de
     * datos y no una colección cargada antes de entrar a la transacción.
     */
    private function recargarParaEvaluar(User $docente): User
    {
        return User::with([
            'estudiosUsuario.documentosEstudio',
            'idiomasUsuario.documentosIdioma',
            'experienciasUsuario.documentosExperiencia',
            'produccionAcademicaUsuario.documentosProduccionAcademica',
            'evaluacionDocenteUsuario',
            'historialEscalonUsuario.escalon',
        ])->findOrFail($docente->id);
    }

    /**
     * Mantiene `puntajes` al día. Es solo una caché para que los listados no tengan que unir contra
     * el historial fila por fila; la fuente de verdad es `historial_escalon_docente`.
     *
     * `puntaje_total` se recalcula en vez de arrastrarse porque depende de la ventana del escalón:
     * al ascender vuelve a cero, y al revertir tiene que volver al valor de la ventana que se
     * reabre. Escribir un número viejo dejaría la caché mintiendo. La columna es NOT NULL, así que
     * tampoco basta con no tocarla.
     */
    private function sincronizarCache(User $docente, ?string $nombreEscalon): void
    {
        $docente->puntajeUsuario()->updateOrCreate(
            ['user_id' => $docente->id],
            [
                'categoria_lograda' => $nombreEscalon,
                'puntaje_total' => $this->motor->evaluarAscenso($this->recargarParaEvaluar($docente))['puntaje_total'],
            ]
        );

        $docente->unsetRelation('puntajeUsuario');
    }

    private function notificarAscenso(User $docente, string $escalon): void
    {
        try {
            NotificacionController::escalonOtorgado($docente, $escalon);
        } catch (\Throwable $e) {
            Log::error("Error al notificar el ascenso del docente {$docente->id}: " . $e->getMessage());
        }
    }

    private function notificarReversion(
        User $docente,
        ?string $escalonRevertido,
        ?string $escalonRestituido,
        string $motivo,
        User $ejecutor
    ): void {
        try {
            NotificacionController::escalonRevertido(
                $docente,
                $escalonRevertido,
                $escalonRestituido,
                $motivo,
                $ejecutor->getRoleNames()->first()
            );
        } catch (\Throwable $e) {
            Log::error("Error al notificar la reversión de escalafón del docente {$docente->id}: " . $e->getMessage());
        }
    }
}
