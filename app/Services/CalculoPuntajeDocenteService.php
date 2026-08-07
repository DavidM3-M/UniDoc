<?php

namespace App\Services;

use App\Models\Usuario\User;
use Carbon\Carbon;

// clase que se encarga de calcular el puntaje y categoría de un docente
class CalculoPuntajeDocenteService
{
    // Constante que define los requisitos para cada categoría docente.
    //
    // El umbral mínimo de evaluación docente NO está aquí: lo configura el
    // Administrador y se resuelve en tiempo de ejecución vía
    // UmbralEvaluacionDocenteService. El resto de requisitos sigue fijo.
    const CATEGORIAS = [
        'Asistente' => [
            'formacion' => 'Maestría', // Formación mínima: Maestría
            'ingles' => 'B1',          // Nivel mínimo de inglés: B1
            'puntaje' => 20,           // Puntaje mínimo de producción académica
            'anos' => 4                // Anos mínimos en categoría anterior
        ],
        'Asociado' => [
            'formacion' => 'Doctorado',
            'ingles' => 'B2',
            'puntaje' => 30,
            'anos' => 6
        ],
        'Titular' => [
            'formacion' => 'Doctorado',
            'ingles' => 'B2',
            'puntaje' => 60,
            'anos' => 8
        ],
    ];

    // Constante que asigna un valor numérico a cada nivel de inglés para comparar niveles
    const NIVELES_INGLES = [
        'A1' => 1,
        'A2' => 2,
        'B1' => 3,
        'B2' => 4,
        'C1' => 5,
        'C2' => 6,
    ];

    // Orden del escalafón, de menor a mayor. Permite comparar dos categorías
    // para saber cuál es superior (lo usa la regla de no retroactividad).
    const ORDEN_CATEGORIAS = ['Ninguna', 'Auxiliar', 'Asistente', 'Asociado', 'Titular'];

    public function __construct(private UmbralEvaluacionDocenteService $umbrales)
    {
    }

    /**
     * Posición de una categoría en el escalafón. Mayor número, categoría superior.
     */
    public static function rangoCategoria(?string $categoria): int
    {
        $posicion = array_search($categoria, self::ORDEN_CATEGORIAS, true);

        return $posicion === false ? 0 : $posicion;
    }

    /**
     * Evalúa el perfil de un usuario (docente).
     *
     * @param User $user
     * @param float|null $umbralEvaluacion Umbral de evaluación a aplicar. Si se omite se
     *        usa el vigente. Se pasa explícitamente para re-evaluar a un docente bajo el
     *        umbral con el que se le otorgó su categoría (regla de no retroactividad).
     */
    public function evaluar(User $user, ?float $umbralEvaluacion = null): array
    {
        $umbral = $umbralEvaluacion ?? $this->umbrales->valorVigente();

        // Resultado inicial por defecto
        $resultado = [
            'valido' => false,
            'categoria_lograda' => 'Ninguna',
            'razon' => '',
            'puntaje_total' => 0,
            'faltantes_por_categoria' => [],
            'umbral_evaluacion' => $umbral,
        ];

        // Obtener la contratación del docente
        $contrato = $user->contratacionUsuario;

        // Si no tiene contrato de planta, no aplica evaluación
        if (!$contrato || strtolower(trim($contrato->tipo_contrato)) !== 'planta') {
            $resultado['razon'] = 'Solo aplica para docentes de planta.';
            return $resultado;
        }

        // Calcular los años de contratación y el puntaje de producción académica
        $anios = $this->calcularAniosPlanta($user);
        $puntaje = $this->calcularPuntaje($user);

        // Verificar si el docente tiene producción académica aprobada
        $tieneProduccion = $user->produccionAcademicaUsuario->flatMap(function ($produccion) {
            return $produccion->documentosProduccionAcademica->where('estado', 'aprobado');
        })->isNotEmpty();

        // Verificar si tiene un Doctorado aprobado
        $tieneDoctorado = $user->estudiosUsuario->contains(
            fn($e) =>
            $e->documentosEstudio->contains('estado', 'aprobado') &&
            strtoupper(trim($e->tipo_estudio)) === 'DOCTORADO'
        );

        // Requisitos para ser Titular
        $titular = self::CATEGORIAS['Titular'];
        $cumpleTitular = [
            'formacion' => $tieneDoctorado,
            'ingles' => $this->validarNivelIngles($user, $titular['ingles']),
            'evaluacion' => optional($user->evaluacionDocenteUsuario)->promedio_evaluacion_docente >= $umbral,
            'puntaje' => $puntaje >= $titular['puntaje'],
            'anos' => $anios >= $titular['anos'],
            'produccion_academica' => $tieneProduccion,
        ];

        // Si cumple todos los requisitos de Titular
        if (collect($cumpleTitular)->every(fn($v) => $v)) {
            $resultado = [
                'valido' => true,
                'categoria_lograda' => 'Titular',
                'razon' => 'Cumple todos los requisitos para Titular.',
                'puntaje_total' => $puntaje,
                'faltantes_por_categoria' => [],
                'umbral_evaluacion' => $umbral,
            ];
        // Si tiene Doctorado pero no cumple reuqisiatos para Titular, es Asociado
        } elseif ($tieneDoctorado) {
            $faltantesDetalle = $this->detalleFaltantes($cumpleTitular, $titular, $user, $anios, $puntaje, $umbral);
            $faltantes = collect($faltantesDetalle)->pluck('campo')->toArray();
            $resultado = [
                'valido' => true,
                'categoria_lograda' => 'Asociado',
                'razon' => 'Tiene Doctorado aprobado. Clasificado como Asociado. Para ascender a Titular le faltan: ' . implode(', ', $faltantes),
                'puntaje_total' => $puntaje,
                'faltantes_por_categoria' => ['Titular' => $faltantesDetalle],
                'umbral_evaluacion' => $umbral,
            ];
        // Si no tiene doctorado, se evalúa para Asistente o Auxiliar
        } else {
            $asistente = self::CATEGORIAS['Asistente'];
            $cumpleAsistente = [
                'formacion' => $user->estudiosUsuario->contains(
                    fn($e) =>
                    $e->documentosEstudio->contains('estado', 'aprobado') &&
                    strtoupper(trim($e->tipo_estudio)) === strtoupper(trim($asistente['formacion']))
                ),
                'ingles' => $this->validarNivelIngles($user, $asistente['ingles']),
                'evaluacion' => optional($user->evaluacionDocenteUsuario)->promedio_evaluacion_docente >= $umbral,
                'puntaje' => $puntaje >= $asistente['puntaje'],
                'anos' => $anios >= $asistente['anos'],
                'produccion_academica' => $tieneProduccion,
            ];

            // Si cumple requisitos para ser Asistente
            if (collect($cumpleAsistente)->every(fn($v) => $v)) {
                $resultado = [
                    'valido' => true,
                    'categoria_lograda' => 'Asistente',
                    'razon' => 'Cumple todos los requisitos para Asistente.',
                    'puntaje_total' => $puntaje,
                    'faltantes_por_categoria' => [],
                    'umbral_evaluacion' => $umbral,
                ];
            // Si no cumple requisitos, queda en Auxiliar
            } else {
                $faltantesDetalle = $this->detalleFaltantes($cumpleAsistente, $asistente, $user, $anios, $puntaje, $umbral);
                $faltantesAsistente = collect($faltantesDetalle)->pluck('campo')->toArray();
                $resultado = [
                    'valido' => true,
                    'categoria_lograda' => 'Auxiliar',
                    'razon' => 'No cumple requisitos para categorías superiores. Le faltan para Asistente: ' . implode(', ', $faltantesAsistente),
                    'puntaje_total' => $puntaje,
                    'faltantes_por_categoria' => ['Asistente' => $faltantesDetalle],
                    'umbral_evaluacion' => $umbral,
                ];
            }
        }

        return $resultado; // Devolver la evaluación completa
    }

    // Construye, para cada criterio que no se cumple, un mensaje claro con el valor requerido y el actual del docente
    protected function detalleFaltantes(array $cumple, array $requisitos, User $user, int $anios, int $puntaje, float $umbral): array
    {
        $evaluacionActual = optional($user->evaluacionDocenteUsuario)->promedio_evaluacion_docente;

        // Descripción de cada criterio: mensaje para el usuario, valor requerido y valor actual (si aplica)
        $info = [
            'formacion' => [
                'mensaje' => "Debe tener un estudio de tipo {$requisitos['formacion']} con documento aprobado.",
                'requerido' => $requisitos['formacion'],
                'actual' => null,
            ],
            'ingles' => [
                'mensaje' => "Debe certificar un nivel de inglés mínimo de {$requisitos['ingles']}, con documento aprobado.",
                'requerido' => $requisitos['ingles'],
                'actual' => null,
            ],
            'evaluacion' => [
                'mensaje' => "La evaluación docente debe ser mínimo {$umbral}.",
                'requerido' => $umbral,
                'actual' => $evaluacionActual,
            ],
            'puntaje' => [
                'mensaje' => "Debe alcanzar al menos {$requisitos['puntaje']} puntos de producción académica.",
                'requerido' => $requisitos['puntaje'],
                'actual' => $puntaje,
            ],
            'anos' => [
                'mensaje' => "Debe tener al menos {$requisitos['anos']} años de antigüedad en planta.",
                'requerido' => $requisitos['anos'],
                'actual' => $anios,
            ],
            'produccion_academica' => [
                'mensaje' => 'Debe tener al menos un producto de producción académica aprobado.',
                'requerido' => 1,
                'actual' => null,
            ],
        ];

        $faltantes = [];
        foreach ($cumple as $criterio => $ok) {
            if (!$ok) {
                $faltantes[] = array_merge(['campo' => $criterio], $info[$criterio]);
            }
        }

        return $faltantes;
    }

    // Valida que el nivel de inglés del usuario cumpla o supere el requerido
    protected function validarNivelIngles(User $user, string $nivelRequerido): bool
    {
        foreach ($user->idiomasUsuario as $idioma) {
            if (
                $idioma->documentosIdioma->contains('estado', 'aprobado') &&
                isset(self::NIVELES_INGLES[strtoupper(trim($idioma->nivel))]) &&
                isset(self::NIVELES_INGLES[strtoupper(trim($nivelRequerido))])
            ) {
                $nivelUsuario = self::NIVELES_INGLES[strtoupper(trim($idioma->nivel))];
                $nivelNecesario = self::NIVELES_INGLES[strtoupper(trim($nivelRequerido))];
                if ($nivelUsuario >= $nivelNecesario) {
                    return true;
                }
            }
        }

        return false;
    }

    // Calcula el puntaje total de producción académica del usuario
    public function calcularPuntaje(User $user): int
    {
        $total = 0;

        foreach ($user->produccionAcademicaUsuario as $produccion) {
            $documentosAprobados = $produccion->documentosProduccionAcademica
                ->where('estado', 'aprobado');

            if ($documentosAprobados->isNotEmpty()) {
                $ambitoId = $produccion->ambito_divulgacion_id;

                if ($ambitoId !== null) {
                    $clasificacion = $this->clasificacionPorAmbito($ambitoId);

                    // Asigna puntos dependiendo de la clasificación del ámbito de publicación
                    $total += match ($clasificacion) {
                        'top' => 10,
                        'a' => 6,
                        'b' => 3,
                        default => 0,
                    };
                }
            }
        }

        return $total;
    }

    // Calcula cuántos años lleva el usuario contratado en planta
    protected function calcularAniosPlanta(User $user): int
    {
        $contrato = $user->contratacionUsuario;

        if (!$contrato || strtolower(trim($contrato->tipo_contrato)) !== 'planta') {
            return 0;
        }

        $inicio = Carbon::parse($contrato->fecha_inicio);
        $fin = $contrato->fecha_fin ? Carbon::parse($contrato->fecha_fin) : now();

        return $inicio->diffInYears($fin); // Diferencia en años
    }

    // Clasifica el ámbito de divulgación para asignar puntajes
    protected function clasificacionPorAmbito(int $ambitoId): string
    {
        return match ($ambitoId) {
            1, 20, 21, 25, 27, 29, 34, 46, 50, 54, 62, 65 => 'top',
            2, 12, 15, 26, 28, 30, 35, 38, 47, 51, 55, 63, 66, 73, 45 => 'a',
            3, 13, 16, 19, 31, 36, 39, 48, 52, 56, 64, 41, 42 => 'b',
            default => 'ninguna' // Si no coincide, no asigna puntaje
        };
    }
}
