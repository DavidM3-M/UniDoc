<?php

namespace App\Services;

use App\Models\EscalonDocente;
use App\Models\Usuario\User;

/**
 * Resuelve la categoría efectiva de un docente aplicando la regla de no retroactividad.
 *
 * `MotorEscalafonDocenteService` responde "qué categoría alcanza este docente hoy". Este
 * servicio decide además qué pasa cuando la evaluación mínima que exige el escalón otorgado
 * subió después de que al docente ya se le había otorgado.
 *
 * La regla acordada es **reglas del momento del otorgamiento**: que el escalón exija una
 * evaluación más alta no puede bajarle la categoría a quien ya la tenía, pero perder un
 * requisito real (que le rechacen el doctorado, por ejemplo) sí. Para distinguir ambos
 * casos se re-evalúa al docente con la evaluación mínima que exigía su escalón cuando lo
 * obtuvo:
 *
 * - Si bajo aquella exigencia todavía alcanza la categoría otorgada, la conserva.
 * - Si ni siquiera bajo aquella exigencia la alcanza, la degradación es legítima.
 *
 * Esto solo protege frente a cambios en la evaluación mínima: los demás requisitos del
 * escalón (formación, puntaje, meses, idioma) no tienen anclaje histórico y un cambio en
 * ellos sí baja la categoría de inmediato, igual que antes de este ajuste.
 */
class EscalafonDocenteService
{
    public function __construct(private MotorEscalafonDocenteService $motor)
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
        $resultado = $this->motor->evaluar($user);
        $resultado['categoria_protegida'] = false;
        $resultado['categoria_vigente'] = $resultado['categoria_lograda'];

        $puntaje = $user->puntajeUsuario;
        $otorgada = $puntaje?->categoria_lograda;
        $evaluacionMinimaOtorgada = $puntaje?->umbral_aplicado;

        // Sin categoría previa registrada, o sin evaluación mínima anclada (el escalón
        // otorgado no exigía evaluación), no hay nada que proteger en ese frente.
        if (!$otorgada || $evaluacionMinimaOtorgada === null) {
            return $resultado;
        }

        $rangoOtorgado = MotorEscalafonDocenteService::rangoCategoria($otorgada);
        $rangoActual = MotorEscalafonDocenteService::rangoCategoria($resultado['categoria_lograda']);

        // Si no bajó, no hay conflicto: un ascenso siempre se aplica.
        if ($rangoOtorgado <= $rangoActual) {
            return $resultado;
        }

        // Bajó. Se re-evalúa con la evaluación mínima que exigía su escalón cuando se la
        // otorgaron, para saber si la caída se debe a que esa exigencia subió o a que
        // perdió un requisito real.
        $bajoReglasDeSuMomento = $this->motor->evaluar($user, (float) $evaluacionMinimaOtorgada);
        $rangoHistorico = MotorEscalafonDocenteService::rangoCategoria($bajoReglasDeSuMomento['categoria_lograda']);

        if ($rangoHistorico < $rangoOtorgado) {
            // Ni con la exigencia de su momento alcanza la categoría: la degradación es legítima.
            return $resultado;
        }

        // Conserva la categoría otorgada.
        $resultado['categoria_lograda'] = $otorgada;
        $resultado['categoria_protegida'] = true;
        $evaluacionMinimaVigente = EscalonDocente::where('nombre', $otorgada)->value('evaluacion_minima');
        $resultado['razon'] = sprintf(
            'Conserva la categoría %s: la evaluación mínima que exige ese escalón subió de %s a %s desde que se le otorgó. '
            . 'Con las reglas vigentes alcanzaría %s.',
            $otorgada,
            $evaluacionMinimaOtorgada,
            $evaluacionMinimaVigente,
            $resultado['categoria_vigente']
        );

        return $resultado;
    }

    /**
     * Resuelve la categoría efectiva y la persiste en la tabla `puntajes`.
     *
     * El `umbral_aplicado` (evaluación mínima que exigía el escalón otorgado, ver
     * `MotorEscalafonDocenteService::evaluar()`) solo se reescribe cuando la categoría NO
     * viene protegida: si se reescribiera al proteger, se perdería el ancla que permite
     * volver a comparar contra las reglas originales en la siguiente evaluación.
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
            $datos['umbral_aplicado'] = $resultado['evaluacion_minima_aplicada'];
        }

        $user->puntajeUsuario()->updateOrCreate(['user_id' => $user->id], $datos);
        $user->unsetRelation('puntajeUsuario');

        return $resultado;
    }
}
