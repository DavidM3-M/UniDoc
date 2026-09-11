<?php

namespace App\Services;

use App\Models\EscalonDocente;
use App\Models\HistorialEscalonDocente;
use App\Models\NivelFormacionAcademica;
use App\Models\PeriodoAscenso;
use App\Models\ReglaExcepcionEscalon;
use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use App\Models\Usuario\User;
use Carbon\Carbon;

/**
 * Motor del escalafón docente. Responde **"¿este docente es elegible para ascender?"**, no
 * "¿qué categoría tiene?": la categoría vigente vive en `historial_escalon_docente` y solo la
 * otorga Apoyo Profesoral a través de `AscensoEscalafonService`.
 *
 * Ese es el cambio de fondo frente a la versión anterior, donde `evaluar()` calculaba la categoría
 * y `EscalafonDocenteService` (ya eliminado) la persistía cuando el propio docente pedía su
 * evaluación: el docente se autoascendía. Con el ascenso como acto administrativo, el motor no
 * escribe nada y una categoría otorgada no se cae sola cuando cambian los requisitos.
 *
 * Sigue siendo data-driven: los escalones y sus requisitos salen de `escalones_docente`
 * (administrable), las excepciones —ej. "tiene Doctorado aprobado → mínimo Asociado"— de
 * `reglas_excepcion_escalon`, y el puntaje por ámbito de `ambito_divulgacion.puntaje`.
 *
 * Tres reglas del reglamento que conviene tener presentes al leer el código:
 *
 * 1. **La antigüedad es tiempo en el escalón anterior**, no meses totales en la Universidad, y solo
 *    cuenta si está respaldada por experiencia `es_uniautonoma` con documento aprobado. Ver
 *    `mesesEnEscalon()`.
 * 2. **La producción académica no es acumulable entre escalones**: solo puntúa la divulgada y
 *    subida mientras el docente estaba en su escalón actual. Ver `calcularPuntaje()`.
 * 3. **Todo se congela en `periodo_ascenso.fecha_cierre`**, para que dos docentes con el mismo
 *    expediente no dependan de qué día alcanzó a firmar Apoyo Profesoral.
 *
 * Los ascensos son de uno en uno (`orden` inmediatamente superior). La única forma de saltar
 * escalones es una regla de excepción, que omite todos los requisitos —antigüedad incluida— pero
 * no el calendario: igual espera el periodo de ascenso.
 */
class MotorEscalafonDocenteService
{
    /** Valor numérico de cada nivel MCER, para comparar "al menos tal nivel". */
    private const NIVELES_MCER = [
        'A1' => 1, 'A2' => 2, 'B1' => 3, 'B2' => 4, 'C1' => 5, 'C2' => 6,
    ];

    /**
     * Estados del semáforo que ordena la bandeja de Apoyo Profesoral.
     *
     * No bloquean nada —cualquier documento se puede revisar en cualquier momento— pero evitan
     * perder tiempo revisando estudios e idiomas de quien todavía no tiene los años, que era el
     * problema que motivó el semáforo. Bloquear de verdad se descartó porque habría anulado las
     * excepciones: el doctorado, que es la puerta de escape, nunca se habría podido aprobar.
     */
    public const SIN_EXPERIENCIA_SUFICIENTE = 'sin_experiencia_suficiente';
    public const POR_VERIFICAR_EXPERIENCIA = 'por_verificar_experiencia';
    public const ANTIGUEDAD_CUMPLIDA = 'antiguedad_cumplida';
    public const ELEGIBLE = 'elegible';

    /**
     * Posición de un escalón en el escalafón, por nombre. Mayor número, escalón superior.
     * 0 si el nombre no corresponde a ningún escalón activo (incluye null).
     */
    public static function rangoCategoria(?string $nombreEscalon): int
    {
        if ($nombreEscalon === null) {
            return 0;
        }

        return (int) (EscalonDocente::activos()->where('nombre', $nombreEscalon)->value('orden') ?? 0);
    }

    // ---------------------------------------------------------------
    // Escalón vigente
    // ---------------------------------------------------------------

    /**
     * El tramo abierto del historial: el escalón que el docente tiene hoy.
     *
     * Null si nunca ingresó al escalafón, y entonces no es elegible para nada: sin un punto de
     * partida no hay contra qué medir la antigüedad ni desde cuándo contar la producción. Lo que
     * falta en ese caso no es que alguien lo dé de alta, sino una contratación de planta vigente:
     * el ingreso lo crea `AscensoEscalafonService::ingresar()` cuando Talento Humano la registra.
     */
    public function tramoVigente(User $user): ?HistorialEscalonDocente
    {
        return $user->historialEscalonUsuario
            ->first(fn (HistorialEscalonDocente $tramo) => !$tramo->estaRevertido() && $tramo->hasta === null);
    }

    public function escalonVigente(User $user): ?EscalonDocente
    {
        return $this->tramoVigente($user)?->escalon;
    }

    /**
     * El escalón con el que se entra al escalafón: el activo de `orden` más bajo (hoy, Auxiliar).
     *
     * No se elige: todo docente vinculado entra por el primer escalón y sube desde ahí. Por eso el
     * ingreso no recibe un escalón, lo resuelve contra el catálogo —así que si el Administrador
     * reordena o desactiva escalones, el ingreso sigue apuntando al primero que exista.
     *
     * Null solo si no hay ningún escalón activo, que es un catálogo mal configurado.
     */
    public function escalonInicial(): ?EscalonDocente
    {
        return EscalonDocente::activos()->orderBy('orden')->first();
    }

    /**
     * El escalón al que le tocaría ascender: el activo de `orden` inmediatamente superior.
     *
     * Se busca "el siguiente por orden" y no `orden + 1` porque los `orden` no tienen por qué ser
     * contiguos —el Administrador puede desactivar un escalón intermedio— y en ese caso el ascenso
     * debe pasar al siguiente que sí exista, no quedarse sin objetivo.
     */
    private function escalonSiguiente(EscalonDocente $vigente): ?EscalonDocente
    {
        return EscalonDocente::activos()
            ->with('idioma:id_idioma_catalogo,nombre_idioma')
            ->where('orden', '>', $vigente->orden)
            ->orderBy('orden')
            ->first();
    }

    // ---------------------------------------------------------------
    // Evaluación de ascenso
    // ---------------------------------------------------------------

    /**
     * Evalúa si el docente puede ascender, con el detalle de lo que le falta.
     *
     * @param PeriodoAscenso|null $periodo Periodo contra el que se mide. Si se omite se usa el
     *        vigente, y si tampoco hay se evalúa a la fecha de hoy: entre un periodo y el
     *        siguiente el docente sigue consultando su avance, simplemente no hay ascensos que
     *        ejecutar.
     */
    public function evaluarAscenso(User $user, ?PeriodoAscenso $periodo = null): array
    {
        $periodo ??= PeriodoAscenso::vigente();
        $corte = $periodo ? $periodo->fecha_cierre->copy()->endOfDay() : now();

        return $this->evaluarConCorte($user, $periodo, $corte, true);
    }

    /**
     * El cuerpo de la evaluación, con la fecha de corte ya resuelta.
     *
     * Existe separado de `evaluarAscenso()` por `$compararConHoy`: cuando el docente no es elegible
     * contra un periodo pasado, el mensaje necesita saber si lo sería hoy —es la diferencia entre
     * "no cumple" y "todavía no cumplía"—, y eso obliga a evaluarlo dos veces. La bandera corta esa
     * segunda vuelta para que no se llame a sí misma indefinidamente.
     */
    private function evaluarConCorte(
        User $user,
        ?PeriodoAscenso $periodo,
        Carbon $corte,
        bool $compararConHoy
    ): array {
        $tramo = $this->tramoVigente($user);
        $vigente = $tramo?->escalon;

        $base = [
            'escalon_vigente' => $vigente?->nombre,
            'escalon_vigente_desde' => $tramo?->desde?->toDateString(),
            'escalon_objetivo' => null,
            'elegible' => false,
            'via' => null,
            'razon' => '',
            'faltantes' => [],
            'meses_en_escalon' => 0,
            'meses_en_escalon_declarados' => 0,
            'meses_requeridos' => null,
            'estado_antiguedad' => self::SIN_EXPERIENCIA_SUFICIENTE,
            'puntaje_total' => 0,
            'puntaje_declarado' => 0,
            'periodo_ascenso' => $periodo?->only(['id_periodo_ascenso', 'nombre']),
            'fecha_corte' => $corte->toDateString(),
        ];

        if (!$vigente) {
            return array_merge($base, [
                // El mensaje decía que Apoyo Profesoral debía registrar el escalón inicial, y eso
                // dejó de ser cierto en dos pasos: primero cuando el ingreso pasó a dispararlo
                // `ContratacionObserver` con la contratación de planta, y después cuando el
                // Administrador ganó el ingreso manual. Ninguno de los dos es Apoyo Profesoral.
                'razon' => 'El docente no ha ingresado al escalafón. El ingreso lo acredita una contratación '
                    . 'de planta vigente; si ya la tiene, el Administrador puede registrarlo manualmente.',
            ]);
        }

        // Los meses del escalón vigente sirven para dos cosas distintas: el requisito de
        // antigüedad (solo lo respaldado por documento aprobado) y el semáforo de la bandeja (lo
        // que alcanzaría si se le aprobara la experiencia que ya declaró).
        $meses = $this->mesesEnEscalon($user, $vigente, $corte);
        $mesesDeclarados = $this->mesesEnEscalon($user, $vigente, $corte, false);

        // Inicio de la ventana de producción: desde que entró a este escalón. Lo anterior ya se
        // usó para llegar hasta aquí.
        $desdeEscalon = $tramo->desde->copy()->startOfDay();

        // El puntaje se parte igual que los meses, y por la misma razón: `puntaje_total` es el que
        // decide el ascenso, `puntaje_declarado` el que le dice al docente que su producción está
        // en cola y no perdida. Nunca es menor que el total —cuenta un superconjunto de la misma
        // producción, y `ambito_divulgacion.puntaje` no admite negativos— así que el frontend puede
        // restarlos para dibujar el tramo pendiente sin protegerse de un resultado negativo.
        $puntaje = $this->calcularPuntaje($user, $desdeEscalon, $corte);
        $puntajeDeclarado = $this->calcularPuntaje($user, $desdeEscalon, $corte, false);

        // La excepción se resuelve primero porque omite todos los requisitos del escalón que
        // otorga, incluida la antigüedad. Lo único que no omite es el calendario.
        $piso = $this->resolverPisoEscalon($user, $corte);

        if ($piso && $piso->orden > $vigente->orden) {
            return array_merge($base, [
                'escalon_objetivo' => $piso->nombre,
                'elegible' => true,
                'via' => HistorialEscalonDocente::VIA_EXCEPCION,
                'razon' => "Elegible para {$piso->nombre} por regla de excepción, sin necesidad de cumplir los demás requisitos.",
                'meses_en_escalon' => $meses,
                'meses_en_escalon_declarados' => $mesesDeclarados,
                'estado_antiguedad' => self::ELEGIBLE,
                'puntaje_total' => $puntaje,
                'puntaje_declarado' => $puntajeDeclarado,
            ]);
        }

        $objetivo = $this->escalonSiguiente($vigente);

        if (!$objetivo) {
            return array_merge($base, [
                'meses_en_escalon' => $meses,
                'meses_en_escalon_declarados' => $mesesDeclarados,
                'puntaje_total' => $puntaje,
                'puntaje_declarado' => $puntajeDeclarado,
                'estado_antiguedad' => self::ANTIGUEDAD_CUMPLIDA,
                'razon' => "{$vigente->nombre} es el escalón más alto configurado: no hay ascenso posible.",
            ]);
        }

        $cumple = $this->evaluarRequisitos($objetivo, $user, $meses, $puntaje, $corte, $desdeEscalon);
        $elegible = collect($cumple)->every(fn ($v) => $v);
        $faltantes = $elegible ? [] : $this->detalleFaltantes($cumple, $objetivo, $vigente, $user, $meses, $puntaje, $corte);

        return array_merge($base, [
            'escalon_objetivo' => $objetivo->nombre,
            'elegible' => $elegible,
            'via' => $elegible ? HistorialEscalonDocente::VIA_REQUISITOS : null,
            'razon' => $elegible
                ? "Cumple todos los requisitos para ascender a {$objetivo->nombre}."
                : $this->razonDeFaltantes(
                    $objetivo,
                    $faltantes,
                    $periodo,
                    $corte,
                    $compararConHoy ? $this->evaluarConCorte($user, null, now(), false) : null
                ),
            'faltantes' => $faltantes,
            'meses_en_escalon' => $meses,
            'meses_en_escalon_declarados' => $mesesDeclarados,
            'meses_requeridos' => $objetivo->meses_minimos_escalon_anterior,
            'estado_antiguedad' => $this->estadoAntiguedad($objetivo, $meses, $mesesDeclarados, $elegible),
            'puntaje_total' => $puntaje,
            'puntaje_declarado' => $puntajeDeclarado,
        ]);
    }

    /**
     * Semáforo que ordena el trabajo de Apoyo Profesoral. Ver las constantes de la clase.
     */
    private function estadoAntiguedad(
        EscalonDocente $objetivo,
        int $meses,
        int $mesesDeclarados,
        bool $elegible
    ): string {
        if ($elegible) {
            return self::ELEGIBLE;
        }

        $requerido = $objetivo->meses_minimos_escalon_anterior;

        // Un escalón que no exige antigüedad nunca está esperando por ella.
        if ($requerido === null || $meses >= $requerido) {
            return self::ANTIGUEDAD_CUMPLIDA;
        }

        return $mesesDeclarados >= $requerido
            ? self::POR_VERIFICAR_EXPERIENCIA
            : self::SIN_EXPERIENCIA_SUFICIENTE;
    }

    /**
     * Evalúa los requisitos propios de un escalón. "Al menos una producción aprobada" se exige
     * siempre (no es un umbral configurable, es un mínimo fijo); el resto solo cuando el escalón
     * los define.
     */
    private function evaluarRequisitos(
        EscalonDocente $escalon,
        User $user,
        int $meses,
        int $puntaje,
        Carbon $corte,
        Carbon $desdeEscalon
    ): array {
        $cumple = [];

        if ($escalon->formacion_minima !== null) {
            $cumple['formacion'] = $this->tieneFormacionAprobada($user, $escalon->formacion_minima, $corte);
        }
        if ($escalon->nivel_mcer_minimo !== null) {
            $cumple['idioma'] = $this->cumpleNivelMcer(
                $user,
                $escalon->nivel_mcer_minimo,
                $escalon->idioma?->nombre_idioma,
                $corte,
                $escalon->idioma_catalogo_id,
            );
        }
        if ($escalon->puntaje_minimo !== null) {
            $cumple['puntaje'] = $puntaje >= $escalon->puntaje_minimo;
        }
        if ($escalon->meses_minimos_escalon_anterior !== null) {
            $cumple['antiguedad'] = $meses >= $escalon->meses_minimos_escalon_anterior;
        }
        if ($escalon->evaluacion_minima !== null) {
            $cumple['evaluacion'] = optional($user->evaluacionDocenteUsuario)->promedio_evaluacion_docente >= $escalon->evaluacion_minima;
        }

        // Mínimo fijo, no configurable. Se comprueba aparte del puntaje a propósito: un ámbito de
        // divulgación puede valer 0 puntos, y en ese caso el docente sí tiene producción avalada
        // aunque su puntaje siga en cero.
        $cumple['produccion_academica'] = $this->tieneProduccionEnVentana($user, $desdeEscalon, $corte);

        return $cumple;
    }

    /** ¿Tiene al menos una producción aprobada dentro de la ventana de su escalón actual? */
    private function tieneProduccionEnVentana(User $user, ?Carbon $desde, ?Carbon $hasta): bool
    {
        return $user->produccionAcademicaUsuario->contains(
            fn ($produccion) => $this->produccionEnVentana($produccion, $desde, $hasta)
        );
    }

    /**
     * Nombre legible de cada criterio. Las claves son internas (`produccion_academica`) y estaban
     * saliendo tal cual en el mensaje de error que ve el usuario.
     */
    private const ETIQUETAS_REQUISITO = [
        'evaluacion' => 'evaluación docente',
        'formacion' => 'formación académica',
        'idioma' => 'nivel de idioma',
        'puntaje' => 'puntaje de producción',
        'antiguedad' => 'antigüedad en el escalón',
        'produccion_academica' => 'producción académica',
    ];

    /**
     * El mensaje de "no puede ascender", que es lo único que llega a la pantalla cuando el acto se
     * rechaza con 409.
     *
     * Distingue dos situaciones que la lista de criterios sola confunde, y que exigen cosas
     * distintas de quien lee:
     *
     * - **"Todavía no cumplía"**: el docente sí es elegible hoy, pero no lo era a la `fecha_cierre`
     *   del periodo elegido, porque lo que lo acredita se subió o se aprobó después. No hay nada que
     *   corregir en el expediente: hay que ascenderlo en un periodo que cierre más tarde. Este caso
     *   salía indistinguible del otro —una lista de seis requisitos, como si no tuviera nada— y era
     *   imposible de diagnosticar desde la pantalla.
     * - **"No cumple"**: le falta de verdad, y ahí lo útil es la lista y saber contra qué fecha se
     *   midió.
     *
     * El detalle de cada criterio —qué se pedía y qué tiene— viaja aparte, en `faltantes`.
     *
     * @param array|null $hoy Evaluación del mismo docente con corte a hoy, o null si este cálculo ya
     *                        es el de hoy y no hay con qué comparar.
     */
    private function razonDeFaltantes(
        EscalonDocente $objetivo,
        array $faltantes,
        ?PeriodoAscenso $periodo,
        Carbon $corte,
        ?array $hoy
    ): string {
        $etiquetas = array_map(
            fn (array $faltante) => self::ETIQUETAS_REQUISITO[$faltante['campo']] ?? $faltante['campo'],
            $faltantes
        );

        // "a, b y c" en vez de "a, b, c": el mensaje lo lee una persona.
        $ultima = array_pop($etiquetas);
        $lista = $etiquetas ? implode(', ', $etiquetas) . " y {$ultima}" : $ultima;

        $fecha = $corte->format('d/m/Y');

        if ($periodo && $hoy && $hoy['elegible']) {
            $via = $hoy['via'] === HistorialEscalonDocente::VIA_EXCEPCION
                ? ' por regla de excepción'
                : '';

            return "El periodo «{$periodo->nombre}» cerró el {$fecha}, y a esa fecha el docente todavía no cumplía: "
                . 'lo que lo acredita se subió o se aprobó después. '
                . "Hoy sí sería elegible para {$hoy['escalon_objetivo']}{$via}, así que el ascenso hay que "
                . 'ejecutarlo en un periodo que cierre más tarde, no en este.';
        }

        // Sin periodo el corte es hoy, y decirlo solo añade ruido.
        if (!$periodo) {
            return "Le falta para {$objetivo->nombre}: {$lista}.";
        }

        return "Con el cierre del periodo «{$periodo->nombre}», el {$fecha}, le falta para "
            . "{$objetivo->nombre}: {$lista}. No cuenta lo que se haya subido o aprobado después de esa fecha.";
    }

    /**
     * Construye, para cada criterio no cumplido, un mensaje con el valor requerido y el actual.
     */
    private function detalleFaltantes(
        array $cumple,
        EscalonDocente $objetivo,
        EscalonDocente $vigente,
        User $user,
        int $meses,
        int $puntaje,
        Carbon $corte
    ): array {
        $info = [
            'produccion_academica' => [
                'mensaje' => 'Debe tener al menos un producto de producción académica aprobado, divulgado y subido mientras estaba en su escalón actual.',
                'requerido' => 1,
                'actual' => null,
            ],
        ];

        if (array_key_exists('evaluacion', $cumple)) {
            $info['evaluacion'] = [
                'mensaje' => "La evaluación docente debe ser mínimo {$objetivo->evaluacion_minima}.",
                'requerido' => $objetivo->evaluacion_minima,
                'actual' => optional($user->evaluacionDocenteUsuario)->promedio_evaluacion_docente,
            ];
        }
        if (array_key_exists('formacion', $cumple)) {
            $info['formacion'] = [
                'mensaje' => "Debe tener un estudio de tipo {$objetivo->formacion_minima} con documento aprobado.",
                'requerido' => $objetivo->formacion_minima,
                'actual' => null,
            ];
        }
        if (array_key_exists('idioma', $cumple)) {
            $nombreIdioma = $objetivo->idioma?->nombre_idioma ?? 'idioma';
            $info['idioma'] = [
                'mensaje' => "Debe certificar {$nombreIdioma} nivel mínimo {$objetivo->nivel_mcer_minimo}, con documento aprobado.",
                'requerido' => $objetivo->nivel_mcer_minimo,
                // Mismos argumentos que usa la evaluación: si aquí se comparara por nombre y allá
                // por id, el mensaje podría decir «tienes B2» mientras el requisito sale sin
                // cumplir, o al revés.
                'actual' => $this->nivelMcerMaximoAprobado(
                    $user,
                    $objetivo->idioma?->nombre_idioma,
                    $corte,
                    $objetivo->idioma_catalogo_id,
                ),
            ];
        }
        if (array_key_exists('puntaje', $cumple)) {
            $info['puntaje'] = [
                'mensaje' => "Debe alcanzar al menos {$objetivo->puntaje_minimo} puntos de producción académica divulgada y subida mientras estaba en {$vigente->nombre}.",
                'requerido' => $objetivo->puntaje_minimo,
                'actual' => $puntaje,
            ];
        }
        if (array_key_exists('antiguedad', $cumple)) {
            $info['antiguedad'] = [
                'mensaje' => "Debe tener al menos {$objetivo->meses_minimos_escalon_anterior} meses como {$vigente->nombre}, respaldados por experiencia en la Universidad Autónoma con documento aprobado.",
                'requerido' => $objetivo->meses_minimos_escalon_anterior,
                'actual' => $meses,
            ];
        }

        $faltantes = [];
        foreach ($cumple as $criterio => $ok) {
            if (!$ok) {
                $faltantes[] = array_merge(['campo' => $criterio], $info[$criterio]);
            }
        }

        return $faltantes;
    }

    // ---------------------------------------------------------------
    // Reglas de excepción
    // ---------------------------------------------------------------

    /**
     * Resuelve el escalón "piso" que otorgan las reglas de excepción activas cuyas condiciones
     * cumple el docente. Si varias aplican, gana la de mayor escalón.
     */
    private function resolverPisoEscalon(User $user, Carbon $corte): ?EscalonDocente
    {
        $candidatos = ReglaExcepcionEscalon::activas()->with('escalonOtorgado')->get()
            ->filter(fn (ReglaExcepcionEscalon $regla) => $this->cumpleCondicionExcepcion($user, $regla, $corte))
            ->map(fn (ReglaExcepcionEscalon $regla) => $regla->escalonOtorgado)
            ->filter();

        return $candidatos->isEmpty() ? null : $candidatos->sortByDesc('orden')->first();
    }

    /**
     * Evalúa la condición de una regla de excepción.
     *
     * `tipo_condicion` es un vocabulario controlado por código: hoy solo entiende 'formacion'.
     * Agregar un tipo de condición nuevo requiere un caso más aquí.
     */
    private function cumpleCondicionExcepcion(User $user, ReglaExcepcionEscalon $regla, Carbon $corte): bool
    {
        return match ($regla->tipo_condicion) {
            'formacion' => $this->tieneFormacionAprobada($user, $regla->valor_condicion, $corte),
            default => false,
        };
    }

    // ---------------------------------------------------------------
    // Formación e idiomas
    // ---------------------------------------------------------------

    /**
     * Si el usuario tiene un estudio con documento aprobado que alcance `$tipo` **o un nivel
     * superior**, según el `orden` del catálogo `niveles_formacion_academica`.
     *
     * Se conserva la comparación por nombre como primer criterio: un estudio cuyo nivel no esté en
     * el catálogo (registro viejo, o un nivel que el Administrador borró) sigue cumpliendo si el
     * nombre coincide exactamente. Los niveles con `orden` nulo (Diplomado, Certificación, Curso)
     * no participan en la jerarquía: solo cumplen por coincidencia exacta.
     *
     * @param Carbon|null $corte Si se indica, el documento tiene que haberse **subido** antes de esa
     *        fecha. Su aval puede haber llegado después: al evaluador no se le pone contra reloj.
     */
    public function tieneFormacionAprobada(User $user, string $tipo, ?Carbon $corte = null): bool
    {
        // `mb_strtoupper` y no `strtoupper`: esta comparación cruza PHP con SQL, y el
        // `strtoupper` de PHP no toca los acentos ("Maestría" → "MAESTRíA") mientras que el
        // `UPPER()` de PostgreSQL sí ("MAESTRÍA"), así que nunca calzarían.
        $tipoNormalizado = mb_strtoupper(trim($tipo));

        // Orden exigido. Si el nivel pedido no está en el catálogo o no tiene orden, solo se
        // puede comparar por nombre.
        $ordenRequerido = NivelFormacionAcademica::whereRaw('UPPER(TRIM(nivel_formacion)) = ?', [$tipoNormalizado])
            ->value('orden');

        return $user->estudiosUsuario->contains(function ($estudio) use ($tipoNormalizado, $ordenRequerido, $corte) {
            if (!$this->tieneDocumentoAprobado($estudio->documentosEstudio, $corte)) {
                return false;
            }

            if (mb_strtoupper(trim($estudio->tipo_estudio ?? '')) === $tipoNormalizado) {
                return true;
            }

            if ($ordenRequerido === null) {
                return false;
            }

            $ordenEstudio = $this->ordenNivelFormacion($estudio);

            return $ordenEstudio !== null && $ordenEstudio >= $ordenRequerido;
        });
    }

    /**
     * Orden del nivel de formación de un estudio. Usa la FK cuando existe y, si no (registros
     * anteriores a la conexión con el catálogo), lo resuelve por el nombre guardado.
     */
    private function ordenNivelFormacion($estudio): ?int
    {
        if ($estudio->nivel_formacion_academica_id !== null) {
            return NivelFormacionAcademica::where('id_nivel_formacion_academica', $estudio->nivel_formacion_academica_id)
                ->value('orden');
        }

        $nombre = mb_strtoupper(trim($estudio->tipo_estudio ?? ''));

        if ($nombre === '') {
            return null;
        }

        return NivelFormacionAcademica::whereRaw('UPPER(TRIM(nivel_formacion)) = ?', [$nombre])->value('orden');
    }

    /**
     * Nivel MCER más alto entre los idiomas del usuario con documento aprobado, o null.
     *
     * Si `$idiomaNombre` viene informado (desde el catálogo de idiomas, ej. "Inglés"), solo cuenta
     * los idiomas del usuario cuyo nombre coincide. `idiomas.idioma` sigue siendo texto libre en el
     * formulario del docente/aspirante —la mayoría de registros tienen `idioma_catalogo_id` en
     * null—, así que la coincidencia tiene que hacerse por el nombre.
     *
     * Y por eso se comparan **sin tildes**: `strtoupper('Ingles')` nunca es igual a `'INGLÉS'`, así
     * que un docente que escribió su idioma sin acento no cumplía nunca el requisito del escalón y
     * no había forma de que lo notara — el aviso solo decía «Debe certificar Inglés nivel B1».
     */
    public function nivelMcerMaximoAprobado(
        User $user,
        ?string $idiomaNombre = null,
        ?Carbon $corte = null,
        ?int $idiomaCatalogoId = null
    ): ?string {
        $maximo = null;
        $maximoValor = 0;
        $idiomaNormalizado = $idiomaNombre !== null ? self::compararNombre($idiomaNombre) : null;

        foreach ($user->idiomasUsuario as $idioma) {
            if (!$this->tieneDocumentoAprobado($idioma->documentosIdioma, $corte)) {
                continue;
            }

            if (!$this->esElIdiomaExigido($idioma, $idiomaCatalogoId, $idiomaNormalizado)) {
                continue;
            }

            $nivel = strtoupper(trim($idioma->nivel));
            $valor = self::NIVELES_MCER[$nivel] ?? null;

            if ($valor !== null && $valor > $maximoValor) {
                $maximoValor = $valor;
                $maximo = $nivel;
            }
        }

        return $maximo;
    }

    /**
     * ¿El idioma del docente es el que exige el escalón?
     *
     * Se compara primero por `idioma_catalogo_id`, que es la referencia real al catálogo, y solo
     * si a alguno de los dos lados le falta se cae al nombre.
     *
     * El orden importa: `idiomas.idioma` guarda una **copia** del nombre que tenía el catálogo el
     * día en que el docente registró su certificado. Si el Administrador renombra ese idioma
     * —«Inglés» a «Inglés británico», por ejemplo—, el escalón pasa a exigir el nombre nuevo
     * mientras las filas ya guardadas conservan el viejo, y el requisito dejaría de cumplirse para
     * todos los docentes a la vez sin que nada lo advirtiera. Comparando por id, renombrar el
     * catálogo no rompe nada.
     */
    private function esElIdiomaExigido($idioma, ?int $idiomaCatalogoId, ?string $nombreNormalizado): bool
    {
        // Sin idioma exigido, cualquiera cuenta.
        if ($idiomaCatalogoId === null && $nombreNormalizado === null) {
            return true;
        }

        if ($idiomaCatalogoId !== null && $idioma->idioma_catalogo_id !== null) {
            return (int) $idioma->idioma_catalogo_id === $idiomaCatalogoId;
        }

        if ($nombreNormalizado === null) {
            return false;
        }

        return self::compararNombre($idioma->idioma ?? '') === $nombreNormalizado;
    }

    /**
     * Deja un nombre de idioma listo para compararlo: sin tildes, sin espacios sobrantes, en
     * mayúsculas. «Inglés», «ingles» e «INGLES» son el mismo idioma.
     */
    private static function compararNombre(string $nombre): string
    {
        $sinTildes = \Illuminate\Support\Str::ascii(trim($nombre));

        return mb_strtoupper($sinTildes);
    }

    private function cumpleNivelMcer(
        User $user,
        string $nivelRequerido,
        ?string $idiomaNombre = null,
        ?Carbon $corte = null,
        ?int $idiomaCatalogoId = null
    ): bool {
        $maximo = $this->nivelMcerMaximoAprobado($user, $idiomaNombre, $corte, $idiomaCatalogoId);
        $requerido = self::NIVELES_MCER[strtoupper(trim($nivelRequerido))] ?? null;

        if ($maximo === null || $requerido === null) {
            return false;
        }

        return self::NIVELES_MCER[$maximo] >= $requerido;
    }

    /**
     * ¿Hay algún documento aprobado, subido antes del corte?
     *
     * El corte se compara contra `created_at` (cuándo lo subió el docente), no contra
     * `revisado_en`: lo que el reglamento exige es haberlo presentado a tiempo. Que Apoyo
     * Profesoral o el Evaluador de Producción lo avalen después del cierre no puede perjudicar al
     * docente, que ya hizo su parte.
     */
    private function tieneDocumentoAprobado($documentos, ?Carbon $corte): bool
    {
        return $documentos->contains(
            fn ($documento) => $documento->estado === 'aprobado'
                && ($corte === null || $documento->created_at === null || $documento->created_at->lessThanOrEqualTo($corte))
        );
    }

    // ---------------------------------------------------------------
    // Producción académica
    // ---------------------------------------------------------------

    /**
     * Suma el puntaje de la producción académica aprobada —o también la pendiente de revisión, con
     * `$soloAprobados` en false— que cae dentro de la ventana del escalón actual, según el puntaje
     * configurado en su ámbito de divulgación (`ambito_divulgacion.puntaje`, administrable).
     *
     * La ventana es `[desde, hasta]` = `[historial.desde del escalón vigente, fecha_cierre]`, y un
     * producto cuenta solo si **ambas** fechas caen dentro:
     *
     * - `fecha_divulgacion`: lo divulgado antes de entrar al escalón ya se usó para llegar a él.
     * - la fecha en que se **subió** el documento aprobado: lo cargado después del cierre queda
     *   para el siguiente periodo de ascenso.
     *
     * Esto es lo que implementa "la producción no es acumulable entre escalones" sin necesidad de
     * marcar ni consumir productos: al ascender, `historial.desde` se mueve y el puntaje arranca en
     * cero solo. Un producto sin `fecha_divulgacion` no puede ubicarse en la ventana y no suma.
     *
     * Con `$desde` y `$hasta` en null suma toda la producción aprobada, que es lo que necesitan los
     * usos informativos (hoja de vida, filtros de Apoyo Profesoral) donde no hay escalón ni periodo
     * de por medio.
     *
     * @param bool $soloAprobados Con `false` incluye la producción cuyo documento sigue pendiente
     *        de revisión, igual que `mesesEnEscalon()` hace con la experiencia. Alimenta
     *        `puntaje_declarado`, nunca el requisito de ascenso. Lo rechazado no entra en ninguno
     *        de los dos: ya se decidió que no vale.
     */
    public function calcularPuntaje(
        User $user,
        ?Carbon $desde = null,
        ?Carbon $hasta = null,
        bool $soloAprobados = true
    ): int {
        $ambitoIds = $user->produccionAcademicaUsuario->pluck('ambito_divulgacion_id')->filter()->unique();

        if ($ambitoIds->isEmpty()) {
            return 0;
        }

        $puntajesPorAmbito = AmbitoDivulgacion::whereIn('id_ambito_divulgacion', $ambitoIds)
            ->pluck('puntaje', 'id_ambito_divulgacion');

        $total = 0;

        foreach ($user->produccionAcademicaUsuario as $produccion) {
            if ($produccion->ambito_divulgacion_id === null) {
                continue;
            }

            if (!$this->produccionEnVentana($produccion, $desde, $hasta, $soloAprobados)) {
                continue;
            }

            $total += (int) ($puntajesPorAmbito[$produccion->ambito_divulgacion_id] ?? 0);
        }

        return $total;
    }

    /**
     * ¿Esta producción cae dentro de la ventana, y con qué respaldo?
     *
     * `$soloAprobados` cambia únicamente qué documentos cuentan como respaldo; las dos fechas de la
     * ventana se exigen igual en ambos modos. Un documento `rechazado` no respalda nada en ninguno
     * de los dos: incluirlo en el declarado le prometería al docente puntos que ya se le negaron.
     */
    private function produccionEnVentana(
        $produccion,
        ?Carbon $desde,
        ?Carbon $hasta,
        bool $soloAprobados = true
    ): bool {
        $estadosValidos = $soloAprobados ? ['aprobado'] : ['aprobado', 'pendiente'];
        $respaldo = $produccion->documentosProduccionAcademica
            ->whereIn('estado', $estadosValidos);

        if ($respaldo->isEmpty()) {
            return false;
        }

        if ($desde === null && $hasta === null) {
            return true;
        }

        if ($produccion->fecha_divulgacion === null) {
            return false;
        }

        $divulgacion = Carbon::parse($produccion->fecha_divulgacion);

        if (($desde && $divulgacion->lessThan($desde)) || ($hasta && $divulgacion->greaterThan($hasta))) {
            return false;
        }

        return $respaldo->contains(function ($documento) use ($desde, $hasta) {
            if ($documento->created_at === null) {
                return true;
            }

            return (!$desde || $documento->created_at->greaterThanOrEqualTo($desde))
                && (!$hasta || $documento->created_at->lessThanOrEqualTo($hasta));
        });
    }

    // ---------------------------------------------------------------
    // Antigüedad en el escalón
    // ---------------------------------------------------------------

    /**
     * Meses que el docente lleva en un escalón, respaldados por experiencia en la Universidad
     * Autónoma con documento aprobado.
     *
     * Reemplaza a `calcularMesesUniautonoma()`, que sumaba todas las experiencias sin importar el
     * escalón y comparaba ese total contra el requisito. Ahora se cruzan dos series de intervalos:
     *
     * - **A**: los tramos del historial en ese escalón (no revertidos), acotados al corte. Los
     *   tramos se acumulan aunque haya interrupciones, que es la regla acordada.
     * - **B**: los periodos de experiencia `es_uniautonoma`, **fusionados**. Fusionar es lo que
     *   corrige un error que existía antes: el bucle anterior sumaba cada experiencia por separado,
     *   así que dos experiencias solapadas contaban doble.
     *
     * Lo que el historial dice pero el certificado no cubre no suma: por eso se intersectan.
     *
     * @param bool $soloAprobados Con `false` incluye la experiencia que el docente ya declaró pero
     *        todavía nadie ha revisado. Alimenta el semáforo de la bandeja ("si le apruebo esto,
     *        ¿alcanza?"), nunca el requisito.
     */
    public function mesesEnEscalon(
        User $user,
        EscalonDocente $escalon,
        ?Carbon $corte = null,
        bool $soloAprobados = true
    ): int {
        $corte ??= now();

        $tramos = [];
        foreach ($user->historialEscalonUsuario as $tramo) {
            if ($tramo->estaRevertido() || $tramo->escalon_id !== $escalon->id_escalon) {
                continue;
            }

            $inicio = $tramo->desde->copy()->startOfDay();
            $fin = $tramo->hasta ? $tramo->hasta->copy()->endOfDay() : $corte->copy();
            $fin = $fin->greaterThan($corte) ? $corte->copy() : $fin;

            if ($inicio->lessThan($fin)) {
                $tramos[] = [$inicio, $fin];
            }
        }

        $experiencias = [];
        foreach ($user->experienciasUsuario as $experiencia) {
            if (!$experiencia->es_uniautonoma) {
                continue;
            }
            if ($soloAprobados && !$experiencia->documentosExperiencia->contains('estado', 'aprobado')) {
                continue;
            }

            $inicio = Carbon::parse($experiencia->fecha_inicio)->startOfDay();
            // Un trabajo actual cierra en el corte, no en la fecha que tenga guardada: así la
            // antigüedad avanza sola día a día. Ver `Experiencia::fechaFinEfectiva()`.
            $fin = $experiencia->fechaFinEfectiva($corte);

            if ($inicio->lessThan($fin)) {
                $experiencias[] = [$inicio, $fin];
            }
        }

        return $this->sumarMeses(
            $this->intersectar($this->fusionar($tramos), $this->fusionar($experiencias))
        );
    }

    /** Une los intervalos que se solapan o se tocan, para que ningún periodo se cuente dos veces. */
    private function fusionar(array $intervalos): array
    {
        if ($intervalos === []) {
            return [];
        }

        usort($intervalos, fn ($a, $b) => $a[0] <=> $b[0]);

        $fusionados = [];
        $actual = array_shift($intervalos);

        foreach ($intervalos as [$inicio, $fin]) {
            if ($inicio->lessThanOrEqualTo($actual[1])) {
                if ($fin->greaterThan($actual[1])) {
                    $actual[1] = $fin;
                }
                continue;
            }

            $fusionados[] = $actual;
            $actual = [$inicio, $fin];
        }

        $fusionados[] = $actual;

        return $fusionados;
    }

    /** Intersección de dos series de intervalos ya fusionadas. */
    private function intersectar(array $a, array $b): array
    {
        $resultado = [];

        foreach ($a as [$inicioA, $finA]) {
            foreach ($b as [$inicioB, $finB]) {
                $inicio = $inicioA->greaterThan($inicioB) ? $inicioA : $inicioB;
                $fin = $finA->lessThan($finB) ? $finA : $finB;

                if ($inicio->lessThan($fin)) {
                    $resultado[] = [$inicio->copy(), $fin->copy()];
                }
            }
        }

        return $resultado;
    }

    /**
     * Suma los meses cumplidos de una serie de intervalos.
     *
     * Se trunca intervalo por intervalo, así que un docente con varios tramos cortos puede perder
     * unos días en cada uno. Es deliberado: el sesgo tiene que ser conservador —nunca acreditar
     * antigüedad que no está—, y en la práctica los tramos son de años.
     *
     * `diffInMonths()` devuelve float en Carbon 3 (ej. 139.186 meses). Sumarlo a un int dispara
     * "Implicit conversion from float ... loses precision", que en PHP 9 pasa de deprecación a
     * error, de ahí el `floor()` y el cast explícitos.
     */
    private function sumarMeses(array $intervalos): int
    {
        $meses = 0;

        foreach ($intervalos as [$inicio, $fin]) {
            $meses += max(0, (int) floor($inicio->diffInMonths($fin)));
        }

        return $meses;
    }
}
