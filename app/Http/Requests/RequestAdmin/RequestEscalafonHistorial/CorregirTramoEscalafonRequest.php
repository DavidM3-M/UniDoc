<?php

namespace App\Http\Requests\RequestAdmin\RequestEscalafonHistorial;

use App\Constants\ClavePrimaria;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Corrección de un tramo del historial de escalafón.
 *
 * Solo tres campos son corregibles —escalón y las dos fechas— y aquí se validan únicamente su forma.
 * Todo lo demás lo decide `AscensoEscalafonService::corregirTramo()` con la cadena de tramos del
 * docente delante, porque no se puede juzgar desde la petición: si un periodo se solapa con otro
 * tramo, si reabrirlo dejaría dos vigentes o si cerrarlo sacaría al docente del escalafón son
 * preguntas sobre el resto del expediente, y todas responden 409.
 *
 * Los campos que **no** viajan aquí lo hacen a propósito: `user_id`, `periodo_ascenso_id`, `via`,
 * `otorgado_por` y los de reversión no son corregibles. Al no estar en las reglas, `validated()` los
 * descarta aunque alguien los mande.
 *
 * Dos trampas del validador que conviene tener presentes:
 *
 * 1. `after_or_equal:desde` solo se evalúa si `desde` viaja en la petición. Cuando llega solo
 *    `hasta`, la comparación contra el `desde` **guardado** la hace el servicio.
 * 2. Con `sometimes|nullable`, la única forma de distinguir "no mando `hasta`" (no tocarlo) de
 *    "mando `hasta: null`" (reabrir el tramo) es `array_key_exists()`, que es como lo lee el
 *    servicio. Leerlo con `?? null` haría las dos cosas indistinguibles.
 */
class CorregirTramoEscalafonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Administrador` del grupo de rutas.
    }

    public function rules(): array
    {
        return [
            'escalon_id' => 'bail|sometimes|integer|min:1|max:' . ClavePrimaria::SMALLINT_MAXIMO
                . '|exists:escalones_docente,id_escalon',

            // Sin `before_or_equal:today`, al contrario que en el ingreso manual: `ascender()` fija
            // las fechas del tramo en `periodo.fecha_cierre`, y un periodo cerrado por adelantado
            // (`cerrado_en` con `fecha_cierre` futura) deja tramos legítimos con fechas por venir.
            'desde' => 'bail|sometimes|date',
            'hasta' => 'bail|sometimes|nullable|date|after_or_equal:desde',

            'motivo' => 'required|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo es obligatorio al corregir un tramo del escalafón.',
            'hasta.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Una corrección que no corrige nada escribiría una fila de bitácora con el mismo
            // retrato antes y después, y notificaría al docente de un cambio que no ocurrió.
            $tocaAlgo = $this->has('escalon_id') || $this->has('desde') || $this->has('hasta');

            if (!$tocaAlgo) {
                $validator->errors()->add(
                    'escalon_id',
                    'Indique al menos un campo a corregir: escalon_id, desde o hasta.'
                );
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error en el formulario',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
