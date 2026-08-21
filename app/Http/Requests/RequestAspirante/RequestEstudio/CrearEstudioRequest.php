<?php

namespace App\Http\Requests\RequestAspirante\RequestEstudio;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\ClavePrimaria;
use App\Constants\ConstAgregarEstudio\Graduado;
use App\Constants\ConstAgregarEstudio\TituloConvalidado;
use App\Constants\TextoLibre;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CrearEstudioRequest extends FormRequest
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
            // Ya no valida contra la constante `TiposEstudio`: valida contra el catálogo real
            // `niveles_formacion_academica` (ver `nivel_formacion_academica_id` abajo). El
            // controlador sobreescribe este valor con el del catálogo cuando se manda el id, así
            // que aquí basta con exigir que sea un nombre de nivel vigente.
            'tipo_estudio'              => ['required','string', Rule::exists('niveles_formacion_academica', 'nivel_formacion')->where('activo', true)],
            // Id del nivel de formación elegido en el select (opcional: si no se manda, el
            // aspirante sigue pudiendo llenar `tipo_estudio` desde el listado de nombres).
            'nivel_formacion_academica_id' => 'bail|nullable|integer|min:1|max:' . ClavePrimaria::SMALLINT_MAXIMO . '|exists:niveles_formacion_academica,id_nivel_formacion_academica',
            'graduado'                  => ['required','string', Rule::in(Graduado::all())],
            // Valida que `graduado` sea requerido y que su valor esté dentro de los valores definidos en `Graduado`.
            'institucion'               => 'required|string|min:7|max:100|' . TextoLibre::SIN_EMOJIS,
             // Valida que `institucion` sea requerido, de tipo `string`, con un mínimo de 7 caracteres, un máximo de 100 caracteres,
            // y que coincida con el patrón de letras, números, espacios y guiones.
            // Id del programa SNIES elegido en la cascada Institución → Programa (opcional:
            // fallback a institución/título manuales si el programa no está en el catálogo).
            'programa_formacion_educativa_id' => 'bail|nullable|integer|min:1|exists:programas_formacion_educativa,id_programa',
            'fecha_graduacion'          => 'nullable|date',
            // Valida que `fecha_graduacion` sea opcional (`nullable`) y de tipo `date`.
            'titulo_convalidado'        => ['required','string', Rule::in(TituloConvalidado::all())],
            // Valida que `titulo_convalidado` sea requerido y que su valor esté dentro de los valores definidos en `TituloConvalidado`.
            'fecha_convalidacion'       => 'nullable|date',
            // Valida que `fecha_convalidacion` sea opcional (`nullable`) y de tipo `date`.
            'resolucion_convalidacion'  => 'nullable|string|min:7|max:100|' . TextoLibre::SIN_EMOJIS,
             // Valida que `resolucion_convalidacion` sea opcional (`nullable`), de tipo `string`, con un mínimo de 7 caracteres,
            // un máximo de 100 caracteres y que coincida con el patrón de letras, números, espacios y guiones.
            'posible_fecha_graduacion'  => 'nullable|date',
            // Valida que `posible_fecha_graduacion` sea opcional (`nullable`) y de tipo `date`.
            'titulo_estudio'            => 'required|string|min:7|max:100|' . TextoLibre::SIN_EMOJIS,
             // Valida que `titulo_estudio` sea opcional (`nullable`), de tipo `string`, con un mínimo de 7 caracteres,
            // un máximo de 100 caracteres y que coincida con el patrón de letras, números, espacios y guiones.
            'fecha_inicio'              => 'required|date',
            // Valida que `fecha_inicio` sea requerido y de tipo `date`.
            'fecha_fin'                 => 'nullable|date',
            // Valida que `fecha_fin` sea opcional (`nullable`) y de tipo `date`.
            'archivo'                   => 'required|file|mimes:pdf|max:2048',
             // Valida que `archivo` sea requerido, de tipo `file`, con extensiones permitidas `pdf`, `jpg`, `png`,
            // y un tamaño máximo de 2048 KB.
    
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
