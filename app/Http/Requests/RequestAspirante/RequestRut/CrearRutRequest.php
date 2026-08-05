<?php

namespace App\Http\Requests\RequestAspirante\RequestRut;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\ConstRut\CodigoCiiu;
use App\Constants\ConstRut\TipoPersona;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;


class CrearRutRequest extends FormRequest
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
            'numero_rut'                    => 'required|string|min:7|max:100|regex:/^[\pL\pN\s\-]+$/u',
             // El campo `numero_rut` es obligatorio (`required`), debe ser una cadena (`string`) con un mínimo de 7 caracteres
            // y un máximo de 100. Además, debe cumplir con un patrón regex que permite letras, números, espacios y guiones.
            'razon_social'                  => 'required|string|min:7|max:100|regex:/^[\pL\pN\s\-]+$/u',
             // El campo `razon_social` es obligatorio, debe ser una cadena con un mínimo de 7 caracteres y un máximo de 100,
            // y cumplir con el mismo patrón regex.
            'tipo_persona'                  => ['required','string', Rule::in(TipoPersona::all())],
            // El campo `tipo_persona` es obligatorio y su valor debe estar dentro de los valores definidos en `TipoPersona::all()`.
            'codigo_ciiu'                   => ['required','string', Rule::in(CodigoCiiu::all())],
            // El campo `codigo_ciiu` es obligatorio y su valor debe estar dentro de los valores definidos en `CodigoCiiu::all()`.
            'responsabilidades_tributarias' => 'required|string|min:7|max:100',
             // El campo `responsabilidades_tributarias` es obligatorio, debe ser una cadena con un mínimo de 7 caracteres
            // y un máximo de 100.
            'archivo'                       => 'required|file|mimes:pdf|max:2048',
            // El campo `archivo` es obligatorio, debe ser un archivo (`file`) con extensiones permitidas (`pdf`, `jpg`, `png`)
            // y su tamaño no debe exceder los 2048 KB.
            //si, del d
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
