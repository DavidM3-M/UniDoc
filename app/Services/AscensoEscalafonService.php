<?php

namespace App\Services;

use App\Constants\ConstTalentoHumano\TipoContratacion;
use App\Exceptions\AscensoEscalafonException;
use App\Http\Controllers\TalentoHumano\NotificacionController;
use App\Models\EscalonDocente;
use App\Models\HistorialEscalonBitacora;
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
 * Con una excepción deliberada: el **ingreso ordinario no es un acto de nadie**. Lo dispara la
 * contratación de planta a través de `ContratacionObserver`, sin firma y sin formulario, porque ser
 * docente de planta ya es la decisión. Ascender y revertir sí quedan firmados por quien los ejecuta.
 *
 * A eso se suman dos actos exclusivos del **Administrador**, que no existían y que son la única
 * forma de arreglar un expediente sin falsearlo: `ingresarManual()`, para el docente que llega con
 * una categoría ya reconocida o al que hay que reingresar tras una reversión, y `corregirTramo()`,
 * para el escalón o la fecha que se cargaron mal. Los dos exigen motivo y quedan además en
 * `historial_escalon_bitacoras`, porque un tramo puede corregirse más de una vez y el propio tramo
 * solo puede retratar el último estado.
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
            $this->notificarAscenso($docente, $objetivo->nombre, $ejecutor);

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
            $tramo = $this->bloquear($tramo);

            if ($tramo->estaRevertido()) {
                throw new AscensoEscalafonException('Este tramo del escalafón ya estaba revertido.');
            }

            $tramo->update([
                'revertido_en' => now(),
                'revertido_por' => $ejecutor->id,
                'motivo_reversion' => $motivo,
            ]);

            // El tramo que estaba vigente antes de este: el de `hasta` más reciente entre los que
            // siguen contando. Se reabre para que el docente vuelva a donde estaba. Va bloqueado
            // porque reabrirlo es lo que puede dejar al docente con dos tramos abiertos si otra
            // reversión de la misma cadena lo está mirando a la vez.
            $anterior = HistorialEscalonDocente::where('user_id', $tramo->user_id)
                ->whereKeyNot($tramo->getKey())
                ->noRevertidos()
                ->whereNotNull('hasta')
                ->orderByDesc('hasta')
                ->orderByDesc('id_historial_escalon')
                ->lockForUpdate()
                ->first();

            $anterior?->update(['hasta' => null]);

            $docente = $tramo->docente;
            $this->sincronizarCache($docente, $anterior?->escalon?->nombre);
            $this->notificarReversion($docente, $tramo->escalon?->nombre, $anterior?->escalon?->nombre, $motivo, $ejecutor);

            return $tramo->refresh();
        });
    }

    // ---------------------------------------------------------------
    // Correcciones del Administrador
    // ---------------------------------------------------------------

    /**
     * Mete al docente al escalafón en el escalón y con la fecha que indica el Administrador.
     *
     * Es la contrapartida manual de `ingresar()`, y existe porque el automático solo sabe hacer una
     * cosa: meter al docente en el **primer** escalón con la fecha de su contrato de planta más
     * antiguo. Eso no cubre al docente que llega con una categoría ya reconocida, ni al que hay que
     * reingresar tras una reversión en el escalón que le corresponde, ni la carga de expedientes
     * anteriores al sistema.
     *
     * Sigue exigiendo **contratación de planta vigente**, igual que el automático: el escalafón es
     * de los docentes de planta y esa regla no se relaja porque quien registra sea el Administrador.
     * Lo que cambia es que el escalón y la fecha los elige él en vez de deducirse. Cuando lo que
     * está mal es una fecha que el automático ya escribió, la herramienta no es esta sino
     * `corregirTramo()`.
     *
     * A diferencia de `ingresar()`, este sí lanza excepciones: hay un funcionario esperando una
     * respuesta y necesita saber por qué no se pudo.
     *
     * @throws AscensoEscalafonException si el docente ya está dentro, no acredita planta vigente,
     *         el escalón está inactivo o el usuario no es docente.
     */
    public function ingresarManual(
        User $docente,
        EscalonDocente $escalon,
        Carbon $desde,
        User $ejecutor,
        string $motivo
    ): HistorialEscalonDocente {
        return DB::transaction(function () use ($docente, $escalon, $desde, $ejecutor, $motivo) {
            // Un tramo sobre alguien sin rol Docente no aparecería nunca en la bandeja, que filtra
            // por `User::role('Docente')`: quedaría en la tabla sin pantalla que lo muestre.
            if (!$docente->hasRole('Docente')) {
                throw new AscensoEscalafonException(
                    'Solo se puede ingresar al escalafón a un usuario con rol Docente.'
                );
            }

            if ($this->tramoAbierto($docente)) {
                throw new AscensoEscalafonException(
                    'El docente ya tiene un escalón vigente. Para cambiarlo, corrija el tramo o revierta el acto que lo otorgó.'
                );
            }

            if (!$escalon->activo) {
                throw new AscensoEscalafonException(
                    "El escalón «{$escalon->nombre}» está inactivo y no puede ser el escalón vigente de un docente."
                );
            }

            if ($this->inicioComoPlanta($docente) === null) {
                throw new AscensoEscalafonException(
                    'El docente no tiene una contratación de planta vigente, que es lo que acredita el ingreso al '
                    . 'escalafón. Registre primero la contratación en Talento Humano.'
                );
            }

            $tramo = HistorialEscalonDocente::create([
                'user_id' => $docente->id,
                'escalon_id' => $escalon->id_escalon,
                'periodo_ascenso_id' => null,
                'desde' => $desde->toDateString(),
                'hasta' => null,
                'via' => HistorialEscalonDocente::VIA_INGRESO,
                'motivo' => $motivo,
                // La firma es lo que distingue este ingreso del automático, que deja `otorgado_por`
                // en null precisamente porque no lo decide nadie. Por eso no hace falta una `via`
                // nueva: el par (via = 'ingreso', otorgado_por != null) ya lo identifica.
                'otorgado_por' => $ejecutor->id,
            ]);

            $this->registrarBitacora(
                $tramo,
                HistorialEscalonBitacora::TIPO_CREACION,
                null,
                $this->instantanea($tramo),
                $ejecutor,
                $motivo
            );

            $this->sincronizarCache($docente, $escalon->nombre);

            return $tramo;
        });
    }

    /**
     * Corrige el escalón y/o las fechas de un tramo ya registrado.
     *
     * Es lo único que permite arreglar un expediente sin falsearlo. Antes, la única forma de tocar
     * un tramo era revertirlo, y revertir un ingreso deja al docente fuera del escalafón: para
     * corregir una fecha de entrada mal cargada había que sacarlo y volverlo a meter, perdiendo por
     * el camino el acto original.
     *
     * Lo que **no** se puede tocar y por qué: `user_id` (mover un tramo de docente es trasplantar
     * antigüedad entre expedientes), `periodo_ascenso_id` (es el ancla de auditoría: contra qué
     * corte y qué reglas se otorgó), `via` (es cómo se llegó al escalón, lo decidió el motor; un
     * `via = 'correccion'` destruiría la distinción entre requisitos y excepción, que no está
     * guardada en ningún otro sitio), `otorgado_por` y los campos de reversión (son firmas; la de
     * quien corrige vive en la bitácora).
     *
     * Un tramo revertido tampoco se corrige: es el registro de algo que se deshizo, y editarlo
     * dejaría el expediente diciendo que lo que se deshizo era otra cosa.
     *
     * **Los tramos vecinos no se ajustan en cascada.** Una cascada reescribiría actos firmados por
     * otras personas que nadie pidió tocar, y como los huecos entre tramos son legales —el docente
     * pudo retirarse y volver— no existe una única cascada correcta. Mover una frontera se hace en
     * dos pasos: primero se encoge el tramo que estorba, lo que abre un hueco, y después se estira
     * el otro.
     *
     * @param array $cambios Subconjunto de `['escalon_id' => int, 'desde' => string, 'hasta' => ?string]`.
     *        La clave `hasta` presente con valor null significa "reabrir"; ausente significa "no tocar".
     * @throws AscensoEscalafonException si el tramo está revertido o el resultado rompe una invariante.
     */
    public function corregirTramo(
        HistorialEscalonDocente $tramo,
        array $cambios,
        User $ejecutor,
        string $motivo
    ): HistorialEscalonDocente {
        return DB::transaction(function () use ($tramo, $cambios, $ejecutor, $motivo) {
            $tramo = $this->bloquear($tramo);

            if ($tramo->estaRevertido()) {
                throw new AscensoEscalafonException(
                    'Este tramo está revertido: es el registro de un acto que se deshizo y no se corrige. '
                    . 'Registre el tramo que corresponda en su lugar.'
                );
            }

            // Bloquea el resto de tramos del docente antes de decidir nada: las invariantes se
            // comprueban contra la cadena entera, no contra el tramo aislado.
            $vecinos = $this->tramosBloqueados($tramo->user_id, $tramo->id_historial_escalon);
            $antes = $this->instantanea($tramo);

            $escalon = array_key_exists('escalon_id', $cambios)
                ? EscalonDocente::findOrFail($cambios['escalon_id'])
                : $tramo->escalon;

            $desde = array_key_exists('desde', $cambios)
                ? Carbon::parse($cambios['desde'])->startOfDay()
                : $tramo->desde->copy();

            // Distinguir "no envío `hasta`" de "envío `hasta: null`" solo se puede por la presencia
            // de la clave: las dos llegan aquí con el mismo valor si se leen con `?? null`.
            $hasta = array_key_exists('hasta', $cambios)
                ? ($cambios['hasta'] === null ? null : Carbon::parse($cambios['hasta'])->startOfDay())
                : $tramo->hasta?->copy();

            $this->validarCorreccion($tramo, $vecinos, $escalon, $desde, $hasta);

            $tramo->update([
                'escalon_id' => $escalon->id_escalon,
                'desde' => $desde->toDateString(),
                'hasta' => $hasta?->toDateString(),
            ]);

            $tramo->refresh()->load('escalon');

            $this->registrarBitacora(
                $tramo,
                HistorialEscalonBitacora::TIPO_ACTUALIZACION,
                $antes,
                $this->instantanea($tramo),
                $ejecutor,
                $motivo
            );

            // El vigente se relee de la base, no de una relación cargada antes de la corrección:
            // la escritura que se acaba de hacer puede haberlo cambiado.
            $docente = $tramo->docente;
            $vigente = HistorialEscalonDocente::where('user_id', $tramo->user_id)
                ->vigentes()
                ->with('escalon')
                ->first();

            $this->sincronizarCache($docente, $vigente?->escalon?->nombre);
            $this->notificarCorreccion($docente, $antes, $this->instantanea($tramo), $motivo, $ejecutor);

            return $tramo;
        });
    }

    /**
     * Las invariantes que la cadena de tramos de un docente tiene que seguir cumpliendo.
     *
     * @param \Illuminate\Support\Collection<int, HistorialEscalonDocente> $vecinos Tramos no
     *        revertidos del mismo docente, sin contar el que se corrige.
     * @throws AscensoEscalafonException
     */
    private function validarCorreccion(
        HistorialEscalonDocente $tramo,
        $vecinos,
        EscalonDocente $escalon,
        Carbon $desde,
        ?Carbon $hasta
    ): void {
        if ($hasta !== null && $desde->greaterThan($hasta)) {
            throw new AscensoEscalafonException(
                'La fecha de inicio no puede ser posterior a la de fin.'
            );
        }

        // Un escalón inactivo sigue siendo un destino legítimo para un tramo **histórico** —por eso
        // la FK es `restrictOnDelete` en vez de borrarse en cascada—, pero no para el vigente: el
        // motor resuelve la categoría con `EscalonDocente::activos()` y dejaría al docente con un
        // escalón que ningún listado sabe interpretar.
        if ($hasta === null && !$escalon->activo) {
            throw new AscensoEscalafonException(
                "El escalón «{$escalon->nombre}» está inactivo y no puede ser el escalón vigente de un docente. "
                . 'Sí puede asignarse a un tramo ya cerrado.'
            );
        }

        // Cerrar el único tramo abierto dejaría al docente dentro de la tabla pero fuera del
        // escalafón —desaparece de la bandeja, `escalonVigente()` pasa a null— en silencio y sin
        // motivo de reversión. Ese estado existe, pero se llega a él por `revertir()`, que sí lo
        // firma y sí avisa al docente.
        if ($hasta !== null && $tramo->hasta === null) {
            throw new AscensoEscalafonException(
                'Cerrar el único tramo vigente sacaría al docente del escalafón sin dejar rastro del acto. '
                . 'Use la reversión.'
            );
        }

        if ($hasta === null && $tramo->hasta !== null) {
            $abierto = $vecinos->firstWhere('hasta', null);

            if ($abierto) {
                throw new AscensoEscalafonException(
                    "No se puede reabrir este tramo: el docente ya tiene abierto el #{$abierto->id_historial_escalon} "
                    . "({$abierto->escalon?->nombre}, desde {$abierto->desde?->toDateString()}). Ciérrelo primero."
                );
            }
        }

        $this->validarSinSolape($vecinos, $desde, $hasta);
    }

    /**
     * Ningún tramo puede solaparse con otro del mismo docente.
     *
     * Los intervalos son **semiabiertos** `[desde, hasta)`, y eso no es un detalle: `ascender()`
     * cierra el tramo anterior y abre el siguiente **el mismo día** —`anterior.hasta` y
     * `nuevo.desde` valen los dos `periodo.fecha_cierre`— para que la ventana de producción del
     * escalón nuevo empiece justo donde acaba la del viejo. Comparar con `<=` en vez de `<` daría
     * por solapada toda cadena de ascensos legítima.
     *
     * Un `hasta` nulo se trata como infinito.
     *
     * @param \Illuminate\Support\Collection<int, HistorialEscalonDocente> $vecinos
     * @throws AscensoEscalafonException
     */
    private function validarSinSolape($vecinos, Carbon $desde, ?Carbon $hasta): void
    {
        foreach ($vecinos as $vecino) {
            $empiezaAntesDeQueElOtroTermine = $vecino->hasta === null || $desde->lessThan($vecino->hasta);
            $elOtroEmpiezaAntesDeQueEsteTermine = $hasta === null || $vecino->desde->lessThan($hasta);

            if ($empiezaAntesDeQueElOtroTermine && $elOtroEmpiezaAntesDeQueEsteTermine) {
                $fin = $vecino->hasta?->toDateString() ?? 'vigente';

                throw new AscensoEscalafonException(
                    "El periodo indicado se solapa con el tramo #{$vecino->id_historial_escalon} "
                    . "({$vecino->escalon?->nombre}, {$vecino->desde?->toDateString()} → {$fin})."
                );
            }
        }
    }

    /**
     * Relee el tramo con `SELECT ... FOR UPDATE` antes de comprobar nada sobre él.
     *
     * El controlador carga el tramo **fuera** de la transacción y lo pasa ya materializado. Sin
     * volver a leerlo con el candado puesto, N peticiones simultáneas sobre el mismo tramo ven todas
     * la misma instancia sin revertir —bajo READ COMMITTED cada transacción lee el estado confirmado
     * al empezar— y las N pasan la guarda de `estaRevertido()`: seis reversiones se daban por buenas
     * y `revertido_por`, `revertido_en` y `motivo_reversion` se sobrescribían seis veces, dejando en
     * la auditoría solo el último motivo y disparando seis notificaciones por un único acto.
     *
     * El índice único parcial de `historial_escalon_docente` impedía que la carrera llegara a dejar
     * al docente con dos escalones vigentes, pero eso es la última línea de defensa: aborta la
     * transacción con un error de base de datos que el usuario recibe como «Server Error». El
     * candado es lo que convierte la carrera en el 409 que corresponde.
     */
    private function bloquear(HistorialEscalonDocente $tramo): HistorialEscalonDocente
    {
        return HistorialEscalonDocente::whereKey($tramo->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Tramos que cuentan del docente, bloqueados para el resto de la transacción.
     *
     * @return \Illuminate\Support\Collection<int, HistorialEscalonDocente>
     */
    private function tramosBloqueados(int $userId, ?int $exceptoId = null)
    {
        return HistorialEscalonDocente::where('user_id', $userId)
            ->noRevertidos()
            ->when($exceptoId, fn ($query) => $query->whereKeyNot($exceptoId))
            ->orderBy('desde')
            ->lockForUpdate()
            ->get()
            ->each(fn (HistorialEscalonDocente $tramo) => $tramo->load('escalon:id_escalon,nombre'));
    }

    /** Retrato del tramo para la bitácora: solo lo que el Administrador puede haber cambiado. */
    private function instantanea(HistorialEscalonDocente $tramo): array
    {
        return [
            'escalon_id' => $tramo->escalon_id,
            'escalon' => $tramo->escalon?->nombre,
            'desde' => $tramo->desde?->toDateString(),
            'hasta' => $tramo->hasta?->toDateString(),
            'via' => $tramo->via,
            'periodo_ascenso_id' => $tramo->periodo_ascenso_id,
        ];
    }

    private function registrarBitacora(
        HistorialEscalonDocente $tramo,
        string $tipo,
        ?array $antes,
        ?array $despues,
        User $ejecutor,
        string $motivo
    ): void {
        HistorialEscalonBitacora::create([
            'historial_escalon_id' => $tramo->id_historial_escalon,
            'docente_id' => $tramo->user_id,
            'user_modifico_id' => $ejecutor->id,
            'tipo_modificacion' => $tipo,
            'datos_anteriores' => $antes,
            'datos_nuevos' => $despues,
            'motivo' => $motivo,
        ]);
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

    /**
     * El rol viaja hasta el correo porque el ascenso dejó de ser exclusivo de Apoyo Profesoral: las
     * rutas de `/admin` ejecutan este mismo acto. Sin él, el mensaje seguiría diciendo que lo
     * registró Apoyo Profesoral incluso cuando lo firmó el Administrador.
     */
    private function notificarAscenso(User $docente, string $escalon, User $ejecutor): void
    {
        try {
            NotificacionController::escalonOtorgado($docente, $escalon, $ejecutor->getRoleNames()->first());
        } catch (\Throwable $e) {
            Log::error("Error al notificar el ascenso del docente {$docente->id}: " . $e->getMessage());
        }
    }

    /**
     * Avisa al docente de una corrección administrativa sobre su historial.
     *
     * Corregir el escalón le cambia la categoría, y corregir el `desde` le cambia la antigüedad y la
     * ventana de producción que puntúa: las dos cosas que usa para saber cuándo puede ascender. Que
     * cambien sin avisar convertiría la corrección en algo que descubre por su cuenta al mirar la
     * pantalla y no puede explicarse.
     */
    private function notificarCorreccion(
        User $docente,
        array $antes,
        array $despues,
        string $motivo,
        User $ejecutor
    ): void {
        try {
            NotificacionController::escalonCorregido(
                $docente,
                $antes,
                $despues,
                $motivo,
                $ejecutor->getRoleNames()->first()
            );
        } catch (\Throwable $e) {
            Log::error("Error al notificar la corrección de escalafón del docente {$docente->id}: " . $e->getMessage());
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
