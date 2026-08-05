<?php

namespace App\Http\Requests\RequestAspirante\RequestEstudio;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\ConstAgregarEstudio\TiposEstudio;
use App\Constants\ConstAgregarEstudio\Graduado;
use App\Constants\ConstAgregarEstudio\TituloConvalidado;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarEstudioRequest extends FormRequest
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
    {
        return [
            
            'tipo_estudio'              => ['sometimes','required','string', Rule::in( TiposEstudio::all())],
             // Valida que `tipo_estudio` sea opcional (`sometimes`), requerido si está presente y que su valor esté
            // dentro de los valores definidos en la constante `TiposEstudio`.
            'graduado'                  => ['sometimes','required','string', Rule::in(Graduado::all())],
             // Valida que `graduado` sea opcional (`sometimes`), requerido si está presente y que su valor esté
            // dentro de los valores definidos en la constante `Graduado`.
            'institucion'               => 'sometimes|required|string|min:7|max:100|regex:/^[\pL\pN\s\-]+$/u',
              // Valida que `institucion` sea opcional (`sometimes`), requerido si está presente, de tipo `string`,
            // con un mínimo de 7 caracteres, un máximo de 100 caracteres y que coincida con el patrón de letras, números, espacios y guiones.
            'fecha_graduacion'          => 'sometimes|nullable|date',
             // Valida que `fecha_graduacion` sea opcional (`sometimes`), puede ser nulo (`nullable`) y de tipo `date`.
            'titulo_convalidado'        => ['sometimes','required','string', Rule::in(TituloConvalidado::all())],
             // Valida que `titulo_convalidado` sea opcional (`sometimes`), requerido si está presente y que su valor esté
            // dentro de los valores definidos en la constante `TituloConvalidado`.
            'fecha_convalidacion'       => 'sometimes|nullable|date',
            // Valida que `fecha_convalidacion` sea opcional (`sometimes`), puede ser nulo (`nullable`) y de tipo `date`.
            'resolucion_convalidacion'  => 'sometimes|nullable|string|min:7|max:100|regex:/^[\pL\pN\s\-]+$/u',
             // Valida que `resolucion_convalidacion` sea opcional (`sometimes`), puede ser nulo (`nullable`), de tipo `string`,
            // con un mínimo de 7 caracteres, un máximo de 100 caracteres y que coincida con el patrón de letras, números, espacios y guiones.
            'posible_fecha_graduacion'  => 'sometimes|nullable|date',
            // Valida que `posible_fecha_graduacion` sea opcional (`sometimes`), puede ser nulo (`nullable`) y de tipo `date`.
            'titulo_estudio'            => 'sometimes|required|string|min:7|max:100|regex:/^[\pL\pN\s\-]+$/u',
             // Valida que `titulo_estudio` sea opcional (`sometimes`), puede ser nulo (`nullable`), de tipo `string`,
            // con un mínimo de 7 caracteres, un máximo de 100 caracteres y que coincida con el patrón de letras, números, espacios y guiones.
            'fecha_inicio'              => 'sometimes|required|date', // volver este campo a requerido
            // Valida que `fecha_inicio` sea opcional (`sometimes`), requerido si está presente y de tipo `date`.
            'fecha_fin'                 => 'sometimes|nullable|date',
            // Valida que `fecha_fin` sea opcional (`sometimes`), puede ser nulo (`nullable`) y de tipo `date`.
            'archivo'                   => 'sometimes|nullable|file|mimes:pdf|max:2048', // Validación del archivo
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
