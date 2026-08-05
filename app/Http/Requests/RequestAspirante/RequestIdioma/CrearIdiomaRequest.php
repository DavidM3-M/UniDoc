<?php

namespace App\Http\Requests\RequestAspirante\RequestIdioma;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\ConstAgregarIdioma\NivelIdioma;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CrearIdiomaRequest extends FormRequest
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
            'idioma'             => 'required|string|max:255|regex:/^[\pL\pN\s\-]+$/u',
             // El campo `idioma` es obligatorio (`required`), debe ser una cadena (`string`), con un máximo de 255 caracteres
            // y debe coincidir con un patrón regex que permite letras, números, espacios y guiones.
            'institucion_idioma' => 'required|string|max:255|regex:/^[\pL\pN\s\-]+$/u',
             // El campo `institucion_idioma` es obligatorio, debe ser una cadena con un máximo de 255 caracteres
            // y cumplir con el mismo patrón regex.
            'fecha_certificado'  => 'required|date',//poner este campo otra ves a requerido
            // El campo `fecha_certificado` es obligatorio y debe ser una fecha válida.
            'nivel'              => ['required','string' , Rule::in(NivelIdioma::all())],
            // El campo `nivel` es obligatorio y su valor debe estar dentro de los valores definidos en `NivelIdioma::all()`.
            'archivo'            => 'required|file|mimes:pdf|max:2048', // Validación de archivo
            // El campo `archivo` es obligatorio, debe ser un archivo (`file`) con extensiones permitidas (`pdf`, `jpg`, `png`)
            // y su tamaño no debe exceder los 2048 KB.
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
