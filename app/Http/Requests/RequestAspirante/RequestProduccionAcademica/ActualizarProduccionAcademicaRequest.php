<?php

namespace App\Http\Requests\RequestAspirante\RequestProduccionAcademica;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ActualizarProduccionAcademicaRequest extends FormRequest
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
            'ambito_divulgacion_id' => 'sometimes|required|integer|exists:ambito_divulgacions,id_ambito_divulgacion',
            // El campo `ambito_divulgacion_id` es opcional (`sometimes`), pero si está presente, es obligatorio (`required`).
            // Debe ser un número entero (`integer`) y debe existir en la tabla `ambito_divulgacions` en la columna `id_ambito_divulgacion`.
            'titulo' => 'sometimes|required|string|max:255|regex:/^[\pL\pN\s\-]+$/u',
              // El campo `titulo` es opcional, pero si está presente, es obligatorio. Debe ser una cadena (`string`)
            // con un máximo de 255 caracteres y cumplir con un patrón regex que permite letras, números, espacios y guiones.
            'numero_autores' => 'sometimes|required|integer',
            // El campo `numero_autores` es opcional, pero si está presente, es obligatorio. Debe ser un número entero (`integer`).
            'medio_divulgacion' => 'sometimes|required|string|max:255|regex:/^[\pL\pN\s\-]+$/u',
            // El campo `medio_divulgacion` es opcional, pero si está presente, es obligatorio. Debe ser una cadena
            // con un máximo de 255 caracteres y cumplir con un patrón regex que permite letras, números, espacios y guiones.
            'fecha_divulgacion' => 'sometimes|nullable|date',// volver este campo a requerido
             // El campo `fecha_divulgacion` es opcional, pero si está presente, debe ser una fecha válida (`date`).
            
            'archivo' => 'sometimes|nullable|file|mimes:pdf|max:2048',
            // El campo `archivo` es opcional, pero si está presente, debe ser un archivo (`file`) con extensiones permitidas
            // (`pdf`, `doc`, `docx`) y su tamaño no debe exceder los 2048 KB.
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
                // Mensaje general de error
                'errors' => $validator->errors(),
                // Incluye los errores específicos de validación generados por el validador.
            ], 422)
                // Devuelve un código de estado HTTP 422 (Unprocessable Entity) para indicar errores de validación.
        );
    }
}
