<?php

namespace App\Http\Requests\RequestAspirante\RequestEps;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\ConstEps\TipoAfiliacion;
use App\Constants\ConstEps\EstadoAfiliacion;
use App\Constants\ConstEps\TipoAfiliado;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarEpsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    // Retorna una respuesta JSON con un código de estado HTTP 422 (Unprocessable Entity).
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
            'nombre_eps'                    => 'sometimes|required|string|min:7|max:100|regex:/^[\pL\pN\s\-]+$/u',
             // Valida que `nombre_eps` sea opcional (`sometimes`), requerido si está presente, de tipo `string`,
            // con un mínimo de 7 caracteres, un máximo de 100 caracteres y que coincida con el patrón de letras, números, espacios y guiones.
            'tipo_afiliacion'               => ['sometimes','required','string', Rule::in(TipoAfiliacion::all())],//llamo a la constante tipo afiliacion para obtener los tipos de afiliacion
              // Valida que `tipo_afiliacion` sea opcional (`sometimes`), requerido si está presente y que su valor esté
            // dentro de los valores definidos en la constante `TipoAfiliacion`.

         
            'estado_afiliacion'             => ['sometimes','required','string', Rule::in(EstadoAfiliacion::all())],//llamo a la constante estado afiliacion para obtener los estados de afiliacion
             // Valida que `estado_afiliacion` sea opcional (`sometimes`), requerido si está presente y que su valor esté
            // dentro de los valores definidos en la constante `EstadoAfiliacion`.
            'fecha_afiliacion_efectiva'     => 'sometimes|required|date',
            // Valida que `fecha_afiliacion_efectiva` sea opcional (`sometimes`), requerido si está presente y de tipo `date`.
            'fecha_finalizacion_afiliacion' => 'sometimes|nullable|date',
            // Valida que `fecha_finalizacion_afiliacion` sea opcional (`sometimes`), puede ser nulo (`nullable`) y de tipo `date`.
            'tipo_afiliado'                 => ['sometimes','required','string', Rule::in(TipoAfiliado::all())],//llamo a la constante tipo afiliado para obtener los tipos de afiliado
            // Valida que `tipo_afiliado` sea opcional (`sometimes`), requerido si está presente y que su valor esté
            // dentro de los valores definidos en la constante `TipoAfiliado`.
            'numero_afiliado'               => 'sometimes|nullable|string|max:100|regex:/^[\pL\pN\s\-]+$/u',
             // Valida que `numero_afiliado` sea opcional (`sometimes`), puede ser nulo (`nullable`), de tipo `string`,
            // con un máximo de 100 caracteres y que coincida con el patrón de letras, números, espacios y guiones.
            'archivo'                       => 'sometimes|nullable|file|mimes:pdf|max:2048', // Validación del archivo
             // Valida que `archivo` sea opcional (`sometimes`), puede ser nulo (`nullable`), de tipo `file`,
            // con extensiones permitidas `pdf`, `jpg`, `png` y un tamaño máximo de 2048 KB.
       
        ];
    }
    protected function failedValidation(Validator $validator)    // Método que se ejecuta cuando la validación falla.

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
