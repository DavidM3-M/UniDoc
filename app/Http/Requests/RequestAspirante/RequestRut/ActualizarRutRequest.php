<?php

namespace App\Http\Requests\RequestAspirante\RequestRut;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\ConstRut\CodigoCiiu;
use App\Constants\ConstRut\TipoPersona;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarRutRequest extends FormRequest
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
            'numero_rut'                    => 'sometimes|required|string|min:7|max:100|regex:/^[\pL\pN\s\-]+$/u',
               // El campo `numero_rut` es opcional (`sometimes`), pero si está presente, es obligatorio (`required`).
            // Debe ser una cadena (`string`) con un mínimo de 7 caracteres y un máximo de 100.
            // Además, debe cumplir con un patrón regex que permite letras, números, espacios y guiones.
            'razon_social'                  => 'sometimes|required|string|min:7|max:100|regex:/^[\pL\pN\s\-]+$/u',
              // El campo `razon_social` es opcional, pero si está presente, es obligatorio.
            // Debe ser una cadena con un mínimo de 7 caracteres y un máximo de 100, y cumplir con el mismo patrón regex.
            'tipo_persona'                  => ['sometimes','required','string', Rule::in(TipoPersona::all())],
             // El campo `tipo_persona` es opcional, pero si está presente, es obligatorio.
            // Su valor debe estar dentro de los valores definidos en `TipoPersona::all()`.
            'codigo_ciiu'                   => ['sometimes','required','string', Rule::in(CodigoCiiu::all())],
            // El campo `codigo_ciiu` es opcional, pero si está presente, es obligatorio.
            // Su valor debe estar dentro de los valores definidos en `CodigoCiiu::all()`.
            'responsabilidades_tributarias' => 'sometimes|required|string|min:7|max:100',
             // El campo `responsabilidades_tributarias` es opcional, pero si está presente, es obligatorio.
            // Debe ser una cadena con un mínimo de 7 caracteres y un máximo de 100.
            'archivo'                       => 'sometimes|nullable|file|mimes:pdf|max:2048', // Validación del archivo
              // El campo `archivo` es opcional, pero si está presente, debe ser un archivo (`file`) con extensiones permitidas
            // (`pdf`, `jpg`, `png`) y su tamaño no debe exceder los 2048 KB.
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
