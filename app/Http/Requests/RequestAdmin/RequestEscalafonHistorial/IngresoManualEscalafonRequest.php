<?php

namespace App\Http\Requests\RequestAdmin\RequestEscalafonHistorial;

use App\Constants\ClavePrimaria;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Ingreso manual de un docente al escalafón, en el escalón y con la fecha que decide el Administrador.
 *
 * El motivo es obligatorio, igual que en la reversión: es una intervención a mano sobre el
 * expediente de alguien y queda en `historial_escalon_bitacoras`, donde sin explicación no sirve
 * de nada.
 *
 * Que el escalón exista se valida aquí; que además esté **activo** lo comprueba
 * `AscensoEscalafonService::ingresarManual()` y responde 409, no 422: un escalón inactivo es un
 * valor perfectamente formado del catálogo, lo que falla es la regla de negocio de que el escalón
 * vigente de un docente tiene que estar en uso.
 */
class IngresoManualEscalafonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Administrador` del grupo de rutas.
    }

    public function rules(): array
    {
        return [
            // `bail` y el tope de smallint antes del `exists`: `escalones_docente.id_escalon` es
            // `smallIncrements`, y un id mayor aborta la consulta en PostgreSQL con SQLSTATE 22003
            // en vez de no encontrar filas. Mismo criterio que `AscenderEscalonRequest`.
            'escalon_id' => 'bail|required|integer|min:1|max:' . ClavePrimaria::SMALLINT_MAXIMO
                . '|exists:escalones_docente,id_escalon',

            // Un ingreso siempre ocurrió ya: no hay calendario que justifique una fecha futura,
            // a diferencia de la corrección, donde un cierre anticipado de periodo sí puede
            // dejar tramos con fechas por venir.
            'desde' => 'bail|required|date|before_or_equal:today',

            'motivo' => 'required|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo es obligatorio al registrar un ingreso manual al escalafón.',
            'desde.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
        ];
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
