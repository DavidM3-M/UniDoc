<?php

namespace App\Services;

use App\Models\Docente\UmbralEvaluacionDocente;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Acceso al umbral mínimo de evaluación docente configurable por el Administrador.
 *
 * El valor se cachea porque `CalculoPuntajeDocenteService` se invoca una vez por
 * docente dentro de un bucle (`FiltrarDocentesController::listarDocentesConPuntaje`);
 * leerlo de base de datos sin caché metería una consulta por docente.
 */
class UmbralEvaluacionDocenteService
{
    /** Clave de caché del umbral vigente. */
    public const CACHE_KEY = 'umbral_evaluacion_docente_vigente';

    /**
     * Valor usado si todavía no hay ningún umbral registrado.
     *
     * Es el mismo que estuvo hardcodeado en CalculoPuntajeDocenteService, para que
     * una base sin sembrar se comporte igual que antes de esta funcionalidad.
     */
    public const VALOR_POR_DEFECTO = 4.0;

    /**
     * Devuelve el valor del umbral vigente.
     */
    public function valorVigente(): float
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $umbral = UmbralEvaluacionDocente::vigente()->first();

            return $umbral ? (float) $umbral->valor_minimo : self::VALOR_POR_DEFECTO;
        });
    }

    /** Devuelve el registro vigente completo (o null si no hay ninguno). */
    public function vigente(): ?UmbralEvaluacionDocente
    {
        return UmbralEvaluacionDocente::vigente()->with('creadoPor:id,primer_nombre,primer_apellido')->first();
    }

    /** Histórico completo, del más reciente al más antiguo. */
    public function historico()
    {
        return UmbralEvaluacionDocente::with('creadoPor:id,primer_nombre,primer_apellido')
            ->orderByDesc('vigencia_desde')
            ->orderByDesc('id_umbral_evaluacion')
            ->get();
    }

    /**
     * Registra un umbral nuevo y cierra el anterior.
     *
     * No actualiza ni borra el registro previo: le asigna `vigencia_hasta` para
     * conservar el histórico y poder reconstruir qué regía en cada momento.
     *
     * @param float $valor Nuevo umbral (0 a 5, un decimal).
     * @param string $vigenciaDesde Fecha en que empieza a regir (Y-m-d).
     * @param int|null $creadoPor ID del administrador que lo registra.
     * @param string|null $observaciones Justificación del cambio.
     */
    public function registrar(float $valor, string $vigenciaDesde, ?int $creadoPor, ?string $observaciones = null): UmbralEvaluacionDocente
    {
        $umbral = DB::transaction(function () use ($valor, $vigenciaDesde, $creadoPor, $observaciones) {
            // Cierra el vigente el día anterior al inicio del nuevo, para que los
            // periodos no se solapen.
            UmbralEvaluacionDocente::vigente()->update([
                'vigencia_hasta' => $vigenciaDesde,
            ]);

            return UmbralEvaluacionDocente::create([
                'valor_minimo'   => $valor,
                'vigencia_desde' => $vigenciaDesde,
                'vigencia_hasta' => null,
                'creado_por'     => $creadoPor,
                'observaciones'  => $observaciones,
            ]);
        });

        $this->olvidarCache();

        return $umbral;
    }

    /** Invalida la caché del umbral vigente. */
    public function olvidarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
