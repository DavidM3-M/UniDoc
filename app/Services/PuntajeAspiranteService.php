<?php

namespace App\Services;

use App\Models\Usuario\User;
use App\Models\Aspirante\Estudio;
use App\Models\Aspirante\Idioma;
use App\Models\Aspirante\Experiencia;
use Carbon\Carbon;

/**
 * Servicio para calcular el puntaje de aptitud de un aspirante.
 *
 * Criterios:
 *  - Estudios   → hasta 350 pts  (según tipo_estudio y estado de graduación)
 *  - Idiomas    → hasta  90 pts  (MCER, máximo 3 idiomas)
 *  - Experiencia→ hasta  50 pts  (5 pts por año de experiencia)
 *  Total máximo posible: 490 pts
 */
class PuntajeAspiranteService
{
    // ─── Tablas de puntos ─────────────────────────────────────────────────────

    /** Puntos base por tipo de estudio (si el aspirante está graduado). */
    private const PUNTOS_ESTUDIO = [
        'Postdoctorado'                                        => 150,
        'Doctorado'                                            => 120,
        'Maestría'                                             => 80,
        'Especialización en medicina humana y odontología'     => 65,
        'Especialización'                                      => 60,
        'Pregrado en medicina humana o composición musical'    => 50,
        'Pregrado'                                             => 40,
        'Tecnológico'                                          => 20,
        'Técnico'                                              => 15,
        'Diplomado'                                            => 10,
        'Certificación'                                        => 8,
        'Curso programado o capacitación'                      => 5,
    ];

    /** Puntos por nivel MCER de idioma. */
    private const PUNTOS_IDIOMA = [
        'C2' => 30,
        'C1' => 25,
        'B2' => 20,
        'B1' => 15,
        'A2' => 10,
        'A1' => 5,
    ];

    private const MAX_ESTUDIOS    = 350;
    private const MAX_IDIOMAS     = 90;   // top 3 idiomas
    private const MAX_EXPERIENCIA = 50;
    private const PUNTOS_POR_ANIO = 5;

    // ─── Punto de entrada ─────────────────────────────────────────────────────

    /**
     * Calcula el puntaje total del aspirante y devuelve el detalle por categoría.
     *
     * @param  int  $userId
     * @return array{
     *   total: int,
     *   estudios: int,
     *   idiomas: int,
     *   experiencia: int,
     *   desglose: array
     * }
     */
    public function calcular(int $userId): array
    {
        $estudiosDetalle   = $this->calcularEstudios($userId);
        $idiomasDetalle    = $this->calcularIdiomas($userId);
        $experienciaDetalle = $this->calcularExperiencia($userId);

        $total = $estudiosDetalle['subtotal']
               + $idiomasDetalle['subtotal']
               + $experienciaDetalle['subtotal'];

        return [
            'total'       => $total,
            'estudios'    => $estudiosDetalle['subtotal'],
            'idiomas'     => $idiomasDetalle['subtotal'],
            'experiencia' => $experienciaDetalle['subtotal'],
            'desglose'    => [
                'estudios'    => $estudiosDetalle['items'],
                'idiomas'     => $idiomasDetalle['items'],
                'experiencia' => $experienciaDetalle['items'],
            ],
        ];
    }

    // ─── Estudios ─────────────────────────────────────────────────────────────

    private function calcularEstudios(int $userId): array
    {
        $estudios = Estudio::where('user_id', $userId)->get();

        $items    = [];
        $subtotal = 0;

        foreach ($estudios as $estudio) {
            $tipo        = $estudio->tipo_estudio ?? '';
            $graduado    = $estudio->graduado ?? 'No';
            $puntosTipo  = self::PUNTOS_ESTUDIO[$tipo] ?? 0;

            // Si no está graduado se otorga la mitad de los puntos
            $pts = ($graduado === 'Si') ? $puntosTipo : (int) floor($puntosTipo / 2);

            $items[] = [
                'tipo'       => $tipo,
                'institucion'=> $estudio->institucion ?? '',
                'titulo'     => $estudio->titulo_estudio ?? '',
                'graduado'   => $graduado,
                'puntos'     => $pts,
            ];

            $subtotal += $pts;
        }

        // Aplicar tope
        $subtotal = min($subtotal, self::MAX_ESTUDIOS);

        return ['subtotal' => $subtotal, 'items' => $items];
    }

    // ─── Idiomas ──────────────────────────────────────────────────────────────

    private function calcularIdiomas(int $userId): array
    {
        $idiomas = Idioma::where('user_id', $userId)->get();

        $items = [];
        foreach ($idiomas as $idioma) {
            $nivel = strtoupper(trim($idioma->nivel ?? ''));
            $pts   = self::PUNTOS_IDIOMA[$nivel] ?? 0;

            $items[] = [
                'idioma' => $idioma->idioma ?? '',
                'nivel'  => $nivel,
                'puntos' => $pts,
            ];
        }

        // Ordenar de mayor a menor puntaje y tomar los 3 mejores
        usort($items, fn($a, $b) => $b['puntos'] - $a['puntos']);
        $top3     = array_slice($items, 0, 3);
        $subtotal = min(array_sum(array_column($top3, 'puntos')), self::MAX_IDIOMAS);

        return ['subtotal' => $subtotal, 'items' => $items];
    }

    // ─── Experiencia ──────────────────────────────────────────────────────────

    private function calcularExperiencia(int $userId): array
    {
        $experiencias = Experiencia::where('user_id', $userId)->get();

        $items        = [];
        $aniosTotales = 0.0;

        foreach ($experiencias as $exp) {
            try {
                $inicio = Carbon::parse($exp->fecha_inicio);
                $fin    = ($exp->trabajo_actual ?? false)
                    ? Carbon::now()
                    : ($exp->fecha_finalizacion ? Carbon::parse($exp->fecha_finalizacion) : Carbon::now());

                $anios = max(0, $inicio->floatDiffInYears($fin));
            } catch (\Exception $e) {
                $anios = 0;
            }

            $items[] = [
                'cargo'       => $exp->cargo ?? '',
                'institucion' => $exp->institucion_experiencia ?? '',
                'anios'       => round($anios, 1),
            ];

            $aniosTotales += $anios;
        }

        $pts      = (int) floor($aniosTotales) * self::PUNTOS_POR_ANIO;
        $subtotal = min($pts, self::MAX_EXPERIENCIA);

        return ['subtotal' => $subtotal, 'items' => $items];
    }
}
