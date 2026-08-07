<?php

namespace App\Services;

use App\Models\Usuario\User;

/**
 * Resuelve la categoría efectiva de un docente aplicando la regla de no retroactividad.
 *
 * `CalculoPuntajeDocenteService` responde "qué categoría alcanza este docente con tal
 * umbral". Este servicio decide además qué pasa cuando el umbral cambió después de que
 * al docente ya se le había otorgado una categoría.
 *
 * La regla acordada es **reglas del momento del otorgamiento**: un cambio de umbral no
 * puede bajarle la categoría a quien ya la tenía, pero perder un requisito real
 * (que le rechacen el doctorado, por ejemplo) sí. Para distinguir ambos casos se
 * re-evalúa al docente con el umbral que regía cuando obtuvo su categoría:
 *
 * - Si bajo aquellas reglas todavía alcanza la categoría otorgada, la conserva.
 * - Si ni siquiera bajo aquellas reglas la alcanza, la degradación es legítima.
 */
class EscalafonDocenteService
{
    public function __construct(private CalculoPuntajeDocenteService $calculo)
    {
    }

    /**
     * Evalúa al docente y devuelve el resultado con la categoría efectiva.
     *
     * Añade al arreglo de `CalculoPuntajeDocenteService::evaluar()` dos claves:
     * - `categoria_protegida`: si la categoría se conservó por no retroactividad.
     * - `categoria_vigente`: la que alcanzaría hoy, cuando difiere de la efectiva.
     *
     * @return array
     */
    public function resolver(User $user): array
    {
        $resultado = $this->calculo->evaluar($user);
        $resultado['categoria_protegida'] = false;
        $resultado['categoria_vigente'] = $resultado['categoria_lograda'];

        $puntaje = $user->puntajeUsuario;
        $otorgada = $puntaje?->categoria_lograda;
        $umbralOtorgado = $puntaje?->umbral_aplicado;

        // Sin categoría previa registrada no hay nada que proteger.
        if (!$otorgada || $umbralOtorgado === null) {
            return $resultado;
        }

        $rangoOtorgado = CalculoPuntajeDocenteService::rangoCategoria($otorgada);
        $rangoActual = CalculoPuntajeDocenteService::rangoCategoria($resultado['categoria_lograda']);

        // Si no bajó, no hay conflicto: un ascenso siempre se aplica.
        if ($rangoOtorgado <= $rangoActual) {
            return $resultado;
        }

        // Bajó. Se re-evalúa con el umbral con el que se le otorgó la categoría para
        // saber si la caída se debe al cambio de umbral o a que perdió un requisito.
        $bajoReglasDeSuMomento = $this->calculo->evaluar($user, (float) $umbralOtorgado);
        $rangoHistorico = CalculoPuntajeDocenteService::rangoCategoria($bajoReglasDeSuMomento['categoria_lograda']);

        if ($rangoHistorico < $rangoOtorgado) {
            // Ni con las reglas de su momento alcanza la categoría: la degradación es legítima.
            return $resultado;
        }

        // Conserva la categoría otorgada.
        $resultado['categoria_lograda'] = $otorgada;
        $resultado['categoria_protegida'] = true;
        $resultado['razon'] = sprintf(
            'Conserva la categoría %s, otorgada cuando el umbral de evaluación era %s. '
            . 'Con el umbral vigente (%s) alcanzaría %s.',
            $otorgada,
            $umbralOtorgado,
            $resultado['umbral_evaluacion'],
            $resultado['categoria_vigente']
        );

        return $resultado;
    }

    /**
     * Resuelve la categoría efectiva y la persiste en la tabla `puntajes`.
     *
     * El `umbral_aplicado` solo se reescribe cuando la categoría NO viene protegida:
     * si se reescribiera al proteger, se perdería el ancla que permite volver a
     * comparar contra las reglas originales en la siguiente evaluación.
     *
     * @return array
     */
    public function evaluarYPersistir(User $user): array
    {
        $resultado = $this->resolver($user);

        $datos = ['puntaje_total' => $resultado['puntaje_total']];

        // La fecha de otorgamiento solo se mueve cuando efectivamente cambia la categoría.
        if (optional($user->puntajeUsuario)->categoria_lograda !== $resultado['categoria_lograda']) {
            $datos['categoria_lograda'] = $resultado['categoria_lograda'];
            $datos['categoria_otorgada_at'] = now();
        }

        if (!$resultado['categoria_protegida']) {
            $datos['umbral_aplicado'] = $resultado['umbral_evaluacion'];
        }

        $user->puntajeUsuario()->updateOrCreate(['user_id' => $user->id], $datos);
        $user->unsetRelation('puntajeUsuario');

        return $resultado;
    }
}
