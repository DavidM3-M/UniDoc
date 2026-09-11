<?php

namespace App\Http\Requests\RequestAspirante\RequestIdioma;

use App\Models\ExamenIdioma;
use Illuminate\Contracts\Validation\Validator;

/**
 * Regla compartida por Crear/Actualizar idioma: cómo se exigen `puntaje_obtenido` y `nivel`
 * según el examen elegido.
 *
 * Son tres escenarios y no se pueden expresar con reglas declarativas sueltas, porque dependen
 * de si el examen del catálogo tiene rangos configurados:
 *
 * 1. Examen del catálogo CON rangos → sirve **el puntaje o el nivel**, lo que traiga el
 *    certificado: no todos indican el puntaje numérico, algunos solo dicen "B2". Si viene el
 *    puntaje se valida contra los rangos y el nivel lo calcula el servidor (más confiable,
 *    ver `IdiomaController`); si solo viene el nivel, se acepta tal cual.
 * 2. Examen del catálogo SIN rangos (el Administrador todavía no los cargó) → no hay contra qué
 *    derivar, así que se pide el nivel a mano.
 * 3. Examen escrito a mano (sin id de catálogo) → igual que el caso 2.
 */
trait ValidaPuntajeYNivel
{
    protected function validarPuntajeYNivel(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $examenId = $this->input('examen_idioma_id');
            $puntaje = $this->input('puntaje_obtenido');
            $nivel = $this->input('nivel');

            $rangos = $examenId
                ? ExamenIdioma::with('rangos')->find($examenId)?->rangos
                : null;

            // Casos 2 y 3: sin rangos con los que derivar, el nivel lo pone el docente.
            if ($rangos === null || $rangos->isEmpty()) {
                if (blank($nivel)) {
                    $validator->errors()->add(
                        'nivel',
                        'Selecciona el nivel MCER: ese examen no tiene rangos de puntaje configurados.'
                    );
                }

                return;
            }

            // Caso 1: hace falta al menos uno de los dos.
            if (blank($puntaje) && blank($nivel)) {
                $validator->errors()->add(
                    'puntaje_obtenido',
                    'Ingresa el puntaje del certificado, o elige el nivel si tu certificado no lo indica.'
                );

                return;
            }

            // Solo el nivel: válido, se guarda tal cual (no hay puntaje que contrastar).
            if (blank($puntaje)) {
                return;
            }

            $puntaje = (float) $puntaje;

            $coincide = $rangos->contains(
                fn ($rango) => $puntaje >= (float) $rango->puntaje_min && $puntaje <= (float) $rango->puntaje_max
            );

            if (!$coincide) {
                $minimo = $rangos->min('puntaje_min');
                $maximo = $rangos->max('puntaje_max');

                $validator->errors()->add(
                    'puntaje_obtenido',
                    "El puntaje {$puntaje} no corresponde a ningún rango configurado para ese examen "
                        . "(va de {$minimo} a {$maximo}). Revisa el puntaje del certificado."
                );
            }
        });
    }
}
