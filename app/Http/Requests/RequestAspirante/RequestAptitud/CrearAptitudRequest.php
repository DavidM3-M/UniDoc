<?php

namespace App\Http\Requests\RequestAspirante\RequestAptitud;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CrearAptitudRequest extends FormRequest
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
    {
        return [
            'nombre_aptitud' => 'required|string|max:50|regex:/^[\pL\pN\s\-,]+$/u',
             // La regla para `nombre_aptitud` indica que es obligatorio (`required`), debe ser una cadena (`string`),
            // tiene un máximo de 50 caracteres (`max:50`) y debe coincidir con un patrón regex que permite letras,
            // números, espacios y guiones.

            'descripcion_aptitud'    => 'required|string|max:500|regex:/^[\pL\pN\s\-,.]+$/u',
             // La regla para `descripcion_aptitud` es similar, pero no tiene un límite de longitud.
            // También es obligatorio, debe ser una cadena y cumplir con el mismo patrón regex.
      

        ];
    }
    protected function failedValidation(Validator $validator)
    // Método que se ejecuta cuando la validación falla.
    {
        throw new HttpResponseException(
           response()->json([
                'success' => false,
                // Indica que la solicitud no fue exitosa.
                'message' => 'Error en el formulario',
                // Mensaje general de error
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
