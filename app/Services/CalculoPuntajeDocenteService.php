<?php

namespace App\Services;

use App\Models\Usuario\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

// clase que se encarga de calcular el puntaje y categoría de un docente
class CalculoPuntajeDocenteService
{
    // Constante que define los requisitos para cada categoría docente
    const CATEGORIAS = [
        'Asistente' => [
            'formacion' => 'Maestría', // Formación mínima: Maestría
            'ingles' => 'B1',          // Nivel mínimo de inglés: B1
            'evaluacion' => 4.0,       // Evaluación docente mínima: 4.0
            'puntaje' => 20,           // Puntaje mínimo de producción académica
            'anos' => 4                // Anos mínimos en categoría anterior
        ],
        'Asociado' => [
            'formacion' => 'Doctorado',
            'ingles' => 'B2',
            'evaluacion' => 4.0,
            'puntaje' => 30,
            'anos' => 6
        ],
        'Titular' => [
            'formacion' => 'Doctorado',
            'ingles' => 'B2',
            'evaluacion' => 4.0,
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

    // Método principal que evalúa el perfil de un usuario (docente)
    public function evaluar(User $user): array
    {
        // Resultado inicial por defecto
        $resultado = [
            'valido' => false,
            'categoria_lograda' => 'Ninguna',
            'razon' => '',
            'puntaje_total' => 0,
            'faltantes_por_categoria' => [],
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
            'evaluacion' => optional($user->evaluacionDocenteUsuario)->promedio_evaluacion_docente >= $titular['evaluacion'],
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
            ];
        // Si tiene Doctorado pero no cumple reuqisiatos para Titular, es Asociado
        } elseif ($tieneDoctorado) {
            $faltantes = collect($cumpleTitular)->filter(fn($v) => !$v)->keys()->toArray();
            $resultado = [
                'valido' => true,
                'categoria_lograda' => 'Asociado',
                'razon' => 'Tiene Doctorado aprobado. Clasificado como Asociado. Para ascender a Titular le faltan: ' . implode(', ', $faltantes),
                'puntaje_total' => $puntaje,
                'faltantes_por_categoria' => ['Titular' => $faltantes],
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
                'evaluacion' => optional($user->evaluacionDocenteUsuario)->promedio_evaluacion_docente >= $asistente['evaluacion'],
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
                ];
            // Si no cumple requisitos, queda en Auxiliar
            } else {
                $faltantesAsistente = collect($cumpleAsistente)->filter(fn($v) => !$v)->keys()->toArray();
                $resultado = [
                    'valido' => true,
                    'categoria_lograda' => 'Auxiliar',
                    'razon' => 'No cumple requisitos para categorías superiores. Le faltan para Asistente: ' . implode(', ', $faltantesAsistente),
                    'puntaje_total' => $puntaje,
                    'faltantes_por_categoria' => ['Asistente' => $faltantesAsistente],
                ];
            }
        }

        return $resultado; // Devolver la evaluación completa
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
