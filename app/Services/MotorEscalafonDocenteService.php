<?php

namespace App\Services;

use App\Models\EscalonDocente;
use App\Models\NivelFormacionAcademica;
use App\Models\ReglaExcepcionEscalon;
use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use App\Models\Usuario\User;
use Carbon\Carbon;

/**
 * Motor de evaluación del escalafón docente, data-driven: lee los escalones y sus requisitos
 * desde `escalones_docente` (administrable), y las reglas de excepción —ej. "tiene Doctorado
 * aprobado → mínimo Asociado"— desde `reglas_excepcion_escalon`. Reemplaza por completo a
 * `CalculoPuntajeDocenteService`, que tenía las mismas reglas hardcodeadas en la constante
 * `CATEGORIAS` y el puntaje por ámbito en un `match` de IDs.
 *
 * `App\Services\EscalafonDocenteService` (la regla de no retroactividad frente a cambios en los
 * requisitos) usa este motor como su fuente de verdad de "qué categoría alcanza el docente hoy".
 *
 * Separación de responsabilidades a propósito:
 * - Un escalón "base" (sin ningún requisito propio, ej. Auxiliar) es donde cae quien no alcanza
 *   ningún otro escalón y no tiene ninguna excepción a su favor.
 * - Los "requisitos" de un escalón deben cumplirse todos a la vez.
 * - Las "excepciones" son reglas de piso: si se cumplen, garantizan un escalón mínimo sin
 *   importar si se cumplen sus demás requisitos.
 *
 * La evaluación docente mínima es un requisito más de cada escalón (`evaluacion_minima`), no un
 * umbral único y global: ya no existe una pantalla "Umbral evaluación" compartida por todos los
 * escalones. Lo que se compara siempre es el mismo dato real que asigna Apoyo Profesoral
 * (`evaluacion_docentes.promedio_evaluacion_docente`).
 */
class MotorEscalafonDocenteService
{
    /** Valor numérico de cada nivel MCER, para comparar "al menos tal nivel". */
    private const NIVELES_MCER = [
        'A1' => 1, 'A2' => 2, 'B1' => 3, 'B2' => 4, 'C1' => 5, 'C2' => 6,
    ];

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

    /**
     * Evalúa el perfil de un usuario (docente) contra el escalafón configurado.
     *
     * @param User $user
     * @param float|null $evaluacionMinimaOverride Si se indica, reemplaza la `evaluacion_minima`
     *        propia de cada escalón durante esta evaluación. Lo usa `EscalafonDocenteService`
     *        para re-evaluar con las reglas vigentes en el momento en que se otorgó una
     *        categoría (regla de no retroactividad); en el uso normal se omite y cada escalón
     *        usa su propio valor.
     */
    public function evaluar(User $user, ?float $evaluacionMinimaOverride = null): array
    {
        $resultado = [
            'valido' => false,
            'categoria_lograda' => 'Ninguna',
            'razon' => '',
            'puntaje_total' => 0,
            'faltantes_por_categoria' => [],
            'evaluacion_minima_aplicada' => null,
        ];

        $contrato = $user->contratacionUsuario;
        if (!$contrato || strtolower(trim($contrato->tipo_contrato)) !== 'planta') {
            $resultado['razon'] = 'Solo aplica para docentes de planta.';
            return $resultado;
        }

        $meses = $this->calcularMesesUniautonoma($user);
        $puntaje = $this->calcularPuntaje($user);
        $tieneProduccion = $this->tieneProduccionAprobada($user);

        $escalones = EscalonDocente::activos()->with('idioma:id_idioma_catalogo,nombre_idioma')->ordenados()->get();
        $escalonBase = $escalones->first(fn ($e) => $this->esBase($e));
        $escalonesConRequisitos = $escalones->reject(fn ($e) => $this->esBase($e))->sortByDesc('orden')->values();
        $pisoEscalon = $this->resolverPisoEscalon($user, $escalones);

        $anterior = null;

        foreach ($escalonesConRequisitos as $escalon) {
            $cumple = $this->evaluarRequisitos($escalon, $user, $meses, $puntaje, $tieneProduccion, $evaluacionMinimaOverride);

            if (collect($cumple)->every(fn ($v) => $v)) {
                return [
                    'valido' => true,
                    'categoria_lograda' => $escalon->nombre,
                    'razon' => "Cumple todos los requisitos para {$escalon->nombre}.",
                    'puntaje_total' => $puntaje,
                    'faltantes_por_categoria' => [],
                    // Ancla para la regla de no retroactividad de `EscalafonDocenteService`: la
                    // evaluación mínima que exigía ESTE escalón cuando se otorgó. Null si el
                    // escalón no exige evaluación (no hay nada que proteger en ese frente).
                    'evaluacion_minima_aplicada' => $evaluacionMinimaOverride ?? $escalon->evaluacion_minima,
                ];
            }

            // Si este es el escalón que otorga el piso de una excepción, la búsqueda se
            // detiene aquí: no sigue bajando aunque tampoco cumpla todos sus requisitos. Los
            // faltantes se reportan contra el escalón inmediatamente superior al piso (lo que
            // le falta para el siguiente ascenso), no contra el piso mismo: ese ya lo tiene
            // garantizado por la excepción, así que listar lo que le falta de él no aporta nada.
            if ($pisoEscalon && $escalon->id_escalon === $pisoEscalon->id_escalon) {
                $escalonObjetivo = $anterior ?? $escalon;
                $cumpleObjetivo = $anterior
                    ? $this->evaluarRequisitos($anterior, $user, $meses, $puntaje, $tieneProduccion, $evaluacionMinimaOverride)
                    : $cumple;
                $faltantesDetalle = $this->detalleFaltantes($cumpleObjetivo, $escalonObjetivo, $user, $meses, $puntaje, $evaluacionMinimaOverride);
                $faltantes = collect($faltantesDetalle)->pluck('campo')->toArray();

                return [
                    'valido' => true,
                    'categoria_lograda' => $pisoEscalon->nombre,
                    'razon' => "Conserva {$pisoEscalon->nombre} por excepción. Para ascender a {$escalonObjetivo->nombre} le faltan: "
                        . implode(', ', $faltantes),
                    'puntaje_total' => $puntaje,
                    'faltantes_por_categoria' => [$escalonObjetivo->nombre => $faltantesDetalle],
                    // El piso lo otorga la excepción, no un requisito de evaluación: nada que anclar.
                    'evaluacion_minima_aplicada' => null,
                ];
            }

            $anterior = $escalon;
        }

        // Ningún escalón con requisitos se cumplió y ninguna excepción aplicó: cae al escalón
        // base, reportando qué le falta para el más bajo de los que sí tienen requisitos.
        $siguienteEscalon = $escalonesConRequisitos->last();
        $faltantesDetalle = $siguienteEscalon
            ? $this->detalleFaltantes(
                $this->evaluarRequisitos($siguienteEscalon, $user, $meses, $puntaje, $tieneProduccion, $evaluacionMinimaOverride),
                $siguienteEscalon,
                $user,
                $meses,
                $puntaje,
                $evaluacionMinimaOverride
            )
            : [];
        $faltantes = collect($faltantesDetalle)->pluck('campo')->toArray();

        return [
            'valido' => true,
            'categoria_lograda' => $escalonBase->nombre ?? 'Auxiliar',
            'razon' => $siguienteEscalon
                ? "No cumple requisitos para categorías superiores. Le faltan para {$siguienteEscalon->nombre}: " . implode(', ', $faltantes)
                : 'No hay escalones con requisitos configurados.',
            'puntaje_total' => $puntaje,
            'faltantes_por_categoria' => $siguienteEscalon ? [$siguienteEscalon->nombre => $faltantesDetalle] : [],
            // El escalón base no exige evaluación (por definición no exige nada): nada que anclar.
            'evaluacion_minima_aplicada' => null,
        ];
    }

    /** Un escalón "base" no exige ningún requisito propio (ej. Auxiliar). */
    private function esBase(EscalonDocente $escalon): bool
    {
        return $escalon->formacion_minima === null
            && $escalon->nivel_mcer_minimo === null
            && $escalon->puntaje_minimo === null
            && $escalon->meses_minimos === null
            && $escalon->evaluacion_minima === null;
    }

    /**
     * Evalúa los requisitos propios de un escalón. "Al menos una producción aprobada" se exige
     * siempre que el escalón tenga algún requisito (no es un umbral configurable, es un mínimo
     * fijo); la evaluación docente sí es configurable por escalón (`evaluacion_minima`) y por
     * eso, como el resto, solo se exige cuando el escalón la define.
     */
    private function evaluarRequisitos(
        EscalonDocente $escalon,
        User $user,
        int $meses,
        int $puntaje,
        bool $tieneProduccion,
        ?float $evaluacionMinimaOverride
    ): array {
        $cumple = [];

        if ($escalon->formacion_minima !== null) {
            $cumple['formacion'] = $this->tieneFormacionAprobada($user, $escalon->formacion_minima);
        }
        if ($escalon->nivel_mcer_minimo !== null) {
            $cumple['idioma'] = $this->cumpleNivelMcer($user, $escalon->nivel_mcer_minimo, $escalon->idioma?->nombre_idioma);
        }
        if ($escalon->puntaje_minimo !== null) {
            $cumple['puntaje'] = $puntaje >= $escalon->puntaje_minimo;
        }
        if ($escalon->meses_minimos !== null) {
            $cumple['antiguedad'] = $meses >= $escalon->meses_minimos;
        }

        $evaluacionMinima = $evaluacionMinimaOverride ?? $escalon->evaluacion_minima;
        if ($evaluacionMinima !== null) {
            $cumple['evaluacion'] = optional($user->evaluacionDocenteUsuario)->promedio_evaluacion_docente >= $evaluacionMinima;
        }

        $cumple['produccion_academica'] = $tieneProduccion;

        return $cumple;
    }

    /**
     * Construye, para cada criterio no cumplido, un mensaje claro con el valor requerido y el
     * actual del docente.
     */
    private function detalleFaltantes(
        array $cumple,
        EscalonDocente $escalon,
        User $user,
        int $meses,
        int $puntaje,
        ?float $evaluacionMinimaOverride = null
    ): array {
        $info = [
            'produccion_academica' => [
                'mensaje' => 'Debe tener al menos un producto de producción académica aprobado.',
                'requerido' => 1,
                'actual' => null,
            ],
        ];

        if (array_key_exists('evaluacion', $cumple)) {
            $evaluacionMinima = $evaluacionMinimaOverride ?? $escalon->evaluacion_minima;
            $info['evaluacion'] = [
                'mensaje' => "La evaluación docente debe ser mínimo {$evaluacionMinima}.",
                'requerido' => $evaluacionMinima,
                'actual' => optional($user->evaluacionDocenteUsuario)->promedio_evaluacion_docente,
            ];
        }
        if (array_key_exists('formacion', $cumple)) {
            $info['formacion'] = [
                'mensaje' => "Debe tener un estudio de tipo {$escalon->formacion_minima} con documento aprobado.",
                'requerido' => $escalon->formacion_minima,
                'actual' => null,
            ];
        }
        if (array_key_exists('idioma', $cumple)) {
            $nombreIdioma = $escalon->idioma?->nombre_idioma ?? 'idioma';
            $info['idioma'] = [
                'mensaje' => "Debe certificar {$nombreIdioma} nivel mínimo {$escalon->nivel_mcer_minimo}, con documento aprobado.",
                'requerido' => $escalon->nivel_mcer_minimo,
                'actual' => $this->nivelMcerMaximoAprobado($user, $escalon->idioma?->nombre_idioma),
            ];
        }
        if (array_key_exists('puntaje', $cumple)) {
            $info['puntaje'] = [
                'mensaje' => "Debe alcanzar al menos {$escalon->puntaje_minimo} puntos de producción académica.",
                'requerido' => $escalon->puntaje_minimo,
                'actual' => $puntaje,
            ];
        }
        if (array_key_exists('antiguedad', $cumple)) {
            $info['antiguedad'] = [
                'mensaje' => "Debe tener al menos {$escalon->meses_minimos} meses de experiencia en la Universidad Autónoma, con documento aprobado.",
                'requerido' => $escalon->meses_minimos,
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

    /**
     * Resuelve el escalón "piso" que otorgan las reglas de excepción activas cuyas condiciones
     * cumple el docente. Si varias aplican, gana la de mayor escalón.
     */
    private function resolverPisoEscalon(User $user, $escalones): ?EscalonDocente
    {
        $reglas = ReglaExcepcionEscalon::activas()->with('escalonOtorgado')->get();

        $candidatos = $reglas
            ->filter(fn (ReglaExcepcionEscalon $regla) => $this->cumpleCondicionExcepcion($user, $regla))
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
    private function cumpleCondicionExcepcion(User $user, ReglaExcepcionEscalon $regla): bool
    {
        return match ($regla->tipo_condicion) {
            'formacion' => $this->tieneFormacionAprobada($user, $regla->valor_condicion),
            default => false,
        };
    }

    /**
     * Si el usuario tiene un estudio con documento aprobado que alcance `$tipo` **o un nivel
     * superior**, según el `orden` del catálogo `niveles_formacion_academica`.
     *
     * Antes esto comparaba por igualdad de texto, con una consecuencia absurda: quien tenía
     * Doctorado no cumplía un requisito de Maestría. Ahora usa la misma lógica de "al menos
     * este nivel" que los idiomas ya tenían con la escala MCER.
     *
     * Se conserva además la comparación por nombre como primer criterio: un estudio cuyo nivel
     * no esté en el catálogo (registro viejo, o un nivel que el Administrador borró) sigue
     * cumpliendo si el nombre coincide exactamente. Así nadie pierde una categoría que ya tenía
     * por un cambio de catálogo.
     *
     * Los niveles con `orden` nulo (Diplomado, Certificación, Curso) no participan en la
     * jerarquía: solo cumplen por coincidencia exacta de nombre.
     */
    public function tieneFormacionAprobada(User $user, string $tipo): bool
    {
        // `mb_strtoupper` y no `strtoupper`: esta comparación cruza PHP con SQL, y el
        // `strtoupper` de PHP no toca los acentos ("Maestría" → "MAESTRíA") mientras que el
        // `UPPER()` de PostgreSQL sí ("MAESTRÍA"), así que nunca calzarían.
        $tipoNormalizado = mb_strtoupper(trim($tipo));

        // Orden exigido. Si el nivel pedido no está en el catálogo o no tiene orden, solo se
        // puede comparar por nombre.
        $ordenRequerido = NivelFormacionAcademica::whereRaw('UPPER(TRIM(nivel_formacion)) = ?', [$tipoNormalizado])
            ->value('orden');

        return $user->estudiosUsuario->contains(function ($estudio) use ($tipoNormalizado, $ordenRequerido) {
            if (!$estudio->documentosEstudio->contains('estado', 'aprobado')) {
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
     * Si `$idiomaNombre` viene informado (desde el catálogo de idiomas, ej. "Inglés"), solo
     * cuenta los idiomas del usuario cuyo nombre coincide (sin distinguir mayúsculas ni espacios
     * sobrantes). `idiomas.idioma` sigue siendo texto libre en el formulario del
     * docente/aspirante —no está enlazado al catálogo por FK todavía—, así que esta comparación
     * es la misma que ya usa `tieneFormacionAprobada()` contra `Estudio.tipo_estudio`.
     */
    public function nivelMcerMaximoAprobado(User $user, ?string $idiomaNombre = null): ?string
    {
        $maximo = null;
        $maximoValor = 0;
        $idiomaNormalizado = $idiomaNombre !== null ? strtoupper(trim($idiomaNombre)) : null;

        foreach ($user->idiomasUsuario as $idioma) {
            if (!$idioma->documentosIdioma->contains('estado', 'aprobado')) {
                continue;
            }

            if ($idiomaNormalizado !== null && strtoupper(trim($idioma->idioma ?? '')) !== $idiomaNormalizado) {
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

    private function cumpleNivelMcer(User $user, string $nivelRequerido, ?string $idiomaNombre = null): bool
    {
        $maximo = $this->nivelMcerMaximoAprobado($user, $idiomaNombre);
        $requerido = self::NIVELES_MCER[strtoupper(trim($nivelRequerido))] ?? null;

        if ($maximo === null || $requerido === null) {
            return false;
        }

        return self::NIVELES_MCER[$maximo] >= $requerido;
    }

    /**
     * Suma el puntaje de cada producción académica aprobada, según el puntaje configurado en su
     * ámbito de divulgación (`ambito_divulgacion.puntaje`, administrable). Reemplaza el `match`
     * hardcodeado de `CalculoPuntajeDocenteService::clasificacionPorAmbito()`.
     */
    public function calcularPuntaje(User $user): int
    {
        $ambitoIds = $user->produccionAcademicaUsuario->pluck('ambito_divulgacion_id')->filter()->unique();

        if ($ambitoIds->isEmpty()) {
            return 0;
        }

        $puntajesPorAmbito = AmbitoDivulgacion::whereIn('id_ambito_divulgacion', $ambitoIds)
            ->pluck('puntaje', 'id_ambito_divulgacion');

        $total = 0;
        foreach ($user->produccionAcademicaUsuario as $produccion) {
            $aprobada = $produccion->documentosProduccionAcademica->where('estado', 'aprobado')->isNotEmpty();

            if ($aprobada && $produccion->ambito_divulgacion_id !== null) {
                $total += (int) ($puntajesPorAmbito[$produccion->ambito_divulgacion_id] ?? 0);
            }
        }

        return $total;
    }

    private function tieneProduccionAprobada(User $user): bool
    {
        return $user->produccionAcademicaUsuario->flatMap(
            fn ($p) => $p->documentosProduccionAcademica->where('estado', 'aprobado')
        )->isNotEmpty();
    }

    /**
     * Suma los meses de las experiencias marcadas como "en la Universidad Autónoma"
     * (`es_uniautonoma`) con documento aprobado. Reemplaza `calcularAniosPlanta()`, que leía el
     * contrato de planta en vez de la experiencia verificada.
     */
    public function calcularMesesUniautonoma(User $user): int
    {
        $meses = 0;

        foreach ($user->experienciasUsuario as $experiencia) {
            if (!$experiencia->es_uniautonoma) {
                continue;
            }
            if (!$experiencia->documentosExperiencia->contains('estado', 'aprobado')) {
                continue;
            }

            $inicio = Carbon::parse($experiencia->fecha_inicio);
            $fin = $experiencia->fecha_finalizacion ? Carbon::parse($experiencia->fecha_finalizacion) : now();

            // `diffInMonths()` devuelve float en Carbon 3 (ej. 139.186 meses). Sumarlo a un int
            // dispara "Implicit conversion from float ... loses precision", que en PHP 9 pasa de
            // deprecación a error. Se trunca explícitamente: solo cuentan los meses cumplidos.
            $meses += max(0, (int) floor($inicio->diffInMonths($fin)));
        }

        return $meses;
    }
}
