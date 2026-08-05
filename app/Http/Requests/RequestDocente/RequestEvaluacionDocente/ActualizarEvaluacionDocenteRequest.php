<?php

namespace App\Http\Requests\RequestDocente\RequestEvaluacionDocente;

use App\Constants\ConstDocente\EstadoEvaluacionDocente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
class ActualizarEvaluacionDocenteRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
        // Método que determina si el usuario está autorizado para realizar esta solicitud.

    {
        return true;
        // Retorna `true`, lo que significa que cualquier usuario está autorizado para usar esta solicitud.
    }

    /**
     * Reglas de validación que se aplican a la solicitud.
     */
    public function rules(): array
    {
        return [
            'promedio_evaluacion_docente' => 'sometimes|required|numeric|min:0|max:10',
              // El campo `promedio_evaluacion_docente` es opcional (`sometimes`), pero si está presente, es obligatorio (`required`).
            // Debe ser un valor numérico (`numeric`) entre 0 y 10.
            'estado_evaluacion_docente' => ['sometimes','required','string', Rule::in(EstadoEvaluacionDocente::all())],
              // El campo `estado_evaluacion_docente` es opcional, pero si está presente, su valor debe estar dentro
            // de los valores definidos en `EstadoEvaluacionDocente::all()`. También puede ser nulo (`nullable`).
    
        ];
    }

    /**
     * Manejo de errores de validación.
     */
    protected function failedValidation(Validator $validator)
        // Método que se ejecuta cuando la validación falla.
    {
        throw new HttpResponseException(
        // Lanza una excepción `HttpResponseException` para devolver una respuesta JSON personalizada.
            response()->json([
                'success' => false,
                // Indica que la solicitud no fue exitosa.
                'message' => 'Error en el formulario',
                // Mensaje general de error.
                'errors' => $validator->errors(),
                // Incluye los errores específicos de validación generados por el validador.
            ], 422)
                // Devuelve un código de estado HTTP 422 (Unprocessable Entity) para indicar errores de validación.
        );
    }
}