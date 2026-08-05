<?php

namespace App\Http\Requests\RequestAspirante\RequestExperiencia;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\ConstAgregarExperiencia\TiposExperiencia;
use App\Constants\ConstAgregarExperiencia\TrabajoActual;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarExperienciaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [

            'tipo_experiencia'             => ['sometimes','required','string', Rule::in(TiposExperiencia::all())],
             // Valida que `tipo_experiencia` sea opcional (`sometimes`), requerido si está presente, de tipo `string`,
            // y que su valor esté dentro de los valores definidos en `TiposExperiencia`.
            'institucion_experiencia'      => 'sometimes|required|string|min:3|max:100|regex:/^[\pL\pN\s\-]+$/u',
              // Valida que `institucion_experiencia` sea opcional (`sometimes`), requerido si está presente, de tipo `string`,
            // con un mínimo de 3 caracteres, un máximo de 100 caracteres y que coincida con el patrón de letras, números, espacios y guiones.
            'cargo'                        => 'sometimes|required|string|min:3|max:100|regex:/^[\pL\pN\s\-]+$/u',
              // Valida que `cargo` sea opcional (`sometimes`), requerido si está presente, de tipo `string`,
            // con un mínimo de 3 caracteres, un máximo de 100 caracteres y que coincida con el patrón de letras, números, espacios y guiones.
            'trabajo_actual'               => ['sometimes','required','string', Rule::in(TrabajoActual::all())],
             // Valida que `trabajo_actual` sea opcional (`sometimes`), requerido si está presente y que su valor esté
            // dentro de los valores definidos en `TrabajoActual`.
            'intensidad_horaria'           => 'sometimes|nullable|integer|min:1|max:168',
             // Valida que `intensidad_horaria` sea opcional (`sometimes`), puede ser nulo (`nullable`), de tipo `integer`,
            // con un valor mínimo de 1 y un máximo de 168 (horas en una semana).
            'fecha_inicio'                 => 'sometimes|required|date',
            // Valida que `fecha_inicio` sea opcional (`sometimes`), requerido si está presente y de tipo `date`.
            'fecha_finalizacion'           => 'sometimes|nullable|date|after_or_equal:fecha_inicio',
            // Valida que `fecha_finalizacion` sea opcional (`sometimes`), puede ser nulo (`nullable`), de tipo `date`,
            // y que sea igual o posterior a `fecha_inicio`.
            'fecha_expedicion_certificado' => 'sometimes|nullable|date',
            // Valida que `fecha_expedicion_certificado` sea opcional (`sometimes`), puede ser nulo (`nullable`) y de tipo `date`.
            'archivo'                      => 'sometimes|nullable|file|mimes:pdf|max:2048',
             // Valida que `archivo` sea opcional (`sometimes`), puede ser nulo (`nullable`), de tipo `file`,
            // con extensiones permitidas `pdf`, `jpg`, `png` y un tamaño máximo de 2048 KB.
    
        ];
    }
    protected function failedValidation(Validator $validator)
    // Método que se ejecuta cuando la validación falla.
    {
        throw new HttpResponseException(
        // Lanza una excepción de respuesta HTTP personalizada.
           response()->json([
                'success' => false,
                // Indica que la solicitud no fue exitosa.
                'message' => 'Error en el formulario',
                // Mensaje de error general.
                'errors' => $validator->errors(),
                // Incluye los errores de validación específicos.
            ], 422)
                // Retorna una respuesta JSON con un código de estado HTTP 422 (Unprocessable Entity).
        );
    }
}
