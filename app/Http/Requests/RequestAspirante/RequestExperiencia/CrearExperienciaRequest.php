<?php

namespace App\Http\Requests\RequestAspirante\RequestExperiencia;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\ConstAgregarExperiencia\TiposExperiencia;
use App\Constants\ConstAgregarExperiencia\TrabajoActual;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;


class CrearExperienciaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    // Método que determina si el usuario está autorizado para realizar esta solicitud.
    {
        return true;
    // Retorna `true`, lo que significa que cualquier usuario está autorizado para usar esta solicitud.
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    // Método que define las reglas de validación para los datos enviados en la solicitud.
    {
        return [
            'tipo_experiencia'             => ['required','string', Rule::in(TiposExperiencia::all())],
            // El campo `tipo_experiencia` es obligatorio (`required`), debe ser una cadena (`string`) y su valor
            // debe estar dentro de los valores definidos en `TiposExperiencia::all()`.
            'institucion_experiencia'      => 'required|string|min:3|max:100|regex:/^[\pL\pN\s\-]+$/u',
             // El campo `institucion_experiencia` es obligatorio, debe ser una cadena con un mínimo de 3 caracteres
            // y un máximo de 100. Además, debe coincidir con un patrón regex que permite letras, números, espacios y guiones.

            'cargo'                        => 'required|string|min:3|max:100|regex:/^[\pL\pN\s\-]+$/u',
              // El campo `cargo` es obligatorio, debe ser una cadena con un mínimo de 3 caracteres y un máximo de 100.
            // También debe coincidir con el mismo patrón regex.
            'trabajo_actual'               => ['required','string', Rule::in(TrabajoActual::all())],
            // El campo `trabajo_actual` es obligatorio y su valor debe estar dentro de los valores definidos en `TrabajoActual::all()`.
            'intensidad_horaria'           => 'nullable|integer|min:1|max:168',
              // El campo `intensidad_horaria` es opcional (`nullable`), pero si se proporciona, debe ser un número entero
            // entre 1 y 168 (máximo número de horas en una semana).
            'fecha_inicio'                 => 'required|date', // volver este campo a requerido
            // El campo `fecha_inicio` es obligatorio y debe ser una fecha válida.
            'fecha_finalizacion'           => 'nullable|date|after_or_equal:fecha_inicio',
            // El campo `fecha_finalizacion` es opcional, pero si se proporciona, debe ser una fecha válida
            // y debe ser igual o posterior a `fecha_inicio`.
            'fecha_expedicion_certificado' => 'nullable|date',
            // El campo `fecha_expedicion_certificado` es opcional, pero si se proporciona, debe ser una fecha válida.
            'archivo'                      => 'required|file|mimes:pdf|max:2048', // Validación del archivo
            // El campo `archivo` es obligatorio, debe ser un archivo (`file`) con extensiones permitidas (`pdf`, `jpg`, `png`)
            // y su tamaño no debe exceder los 2048 KB
        ];
    }

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
