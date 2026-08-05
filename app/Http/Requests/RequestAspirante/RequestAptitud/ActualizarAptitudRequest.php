<?php

namespace App\Http\Requests\RequestAspirante\RequestAptitud;
// Importa la clase `Route` para definir rutas en la API.

use Illuminate\Foundation\Http\FormRequest;
// Importa la clase base `FormRequest` para manejar solicitudes HTTP con validación.

use Illuminate\Contracts\Validation\Validator;
// Importa la interfaz `Validator` para manejar la validación de datos.

use Illuminate\Http\Exceptions\HttpResponseException;
// Importa la excepción `HttpResponseException` para manejar errores de validación personalizados.


class ActualizarAptitudRequest extends FormRequest
// Define la clase `ActualizarAptitudRequest`, que extiende la funcionalidad de `FormRequest`.

{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
        // Método que determina si el usuario está autorizado para realizar esta solicitud.

    {
        return true;
        // Retorna `true`, lo que significa que cualquier usuario está autorizado para realizar esta solicitud.

    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    // Método que define las reglas de validación que se aplican a los datos de la solicitud.

    {
        return [
            'nombre_aptitud' => 'sometimes|required|string|max:50|regex:/^[\pL\pN\s\-,]+$/u',
             // Valida que `nombre_aptitud` sea opcional (`sometimes`), requerido si está presente, de tipo `string`,
            // con un máximo de 50 caracteres y que coincida con el patrón de letras, números, espacios y guiones.

            'descripcion_aptitud'    => 'sometimes|required|string|max:500|regex:/^[\pL\pN\s\-,]+$/u',
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
