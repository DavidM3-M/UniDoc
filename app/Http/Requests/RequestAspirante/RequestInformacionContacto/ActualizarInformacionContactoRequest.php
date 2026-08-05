<?php

namespace App\Http\Requests\RequestAspirante\RequestInformacionContacto;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\ConstInformacionContacto\CategoriaLibretaMilitar;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarInformacionContactoRequest extends FormRequest
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
            'municipio_id'                          => 'sometimes|required|exists:municipios,id_municipio',
            // El campo `municipio_id` es opcional (`sometimes`), pero si está presente, es obligatorio (`required`).
            // Además, debe existir en la tabla `municipios` en la columna `id_municipio`.
            'categoria_libreta_militar'             => ['sometimes', 'nullable', 'string', Rule::in(CategoriaLibretaMilitar::all())], //llamo a la constante categoria libreta militar para obtener los tipos de libreta militar
            // El campo `categoria_libreta_militar` es opcional, pero si está presente, su valor debe estar dentro
            // de los valores definidos en `CategoriaLibretaMilitar::all()`.
            'numero_libreta_militar'                => 'sometimes|nullable|string|max:50|regex:/^[\pL\pN\s\-]+$/u',
            // El campo `numero_libreta_militar` es opcional, pero si está presente, debe ser una cadena (`string`)
            // con un máximo de 50 caracteres y cumplir con un patrón regex que permite letras, números, espacios y guiones.
            'numero_distrito_militar'               => 'sometimes|nullable|string|max:50|regex:/^[\pL\pN\s\-]+$/u',
            // El campo `numero_distrito_militar` es opcional, pero si está presente, debe cumplir las mismas reglas
            // que `numero_libreta_militar`.
            'direccion_residencia'                  => 'sometimes|nullable|string|max:100|regex:/^[\pL\pN\s\-,#]+$/u',
            // El campo `direccion_residencia` es opcional, pero si está presente, debe ser una cadena con un máximo
            // de 100 caracteres y cumplir con un patrón regex que permite letras, números, espacios y guiones.
            'barrio'                                => 'sometimes|nullable|string|max:100|regex:/^[\pL\pN\s\-]+$/u',
            // El campo `barrio` es opcional, pero si está presente, debe cumplir las mismas reglas que `direccion_residencia`.
            'telefono_movil'                        => 'sometimes|required|string|min:7|max:20|regex:/^[0-9+\-\s()]+$/',
            // El campo `telefono_movil` es opcional, pero si está presente, es obligatorio (`required`).
            // Debe ser una cadena con un mínimo de 7 caracteres y un máximo de 20, y cumplir con un patrón regex
            // que permite números, signos de más, guiones, espacios y paréntesis.
            'celular_alternativo'                   => 'sometimes|nullable|string|min:7|max:20|regex:/^[0-9+\-\s()]+$/',
            // El campo `celular_alternativo` es opcional, pero si está presente, debe cumplir las mismas reglas que `telefono_movil`.
            'correo_alterno'                        => 'sometimes|nullable|string|email|max:100|unique:users,email',
            // El campo `correo_alterno` es opcional, pero si está presente, debe ser una cadena válida de correo electrónico,
            // con un máximo de 100 caracteres y debe ser único en la tabla `users` en la columna `email`.
            'archivo'                               => 'sometimes|nullable|file|mimes:pdf|max:2048',
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
