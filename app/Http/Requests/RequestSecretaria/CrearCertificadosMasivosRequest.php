<?php

namespace App\Http\Requests\RequestSecretaria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Constants\ConstAgregarEstudio\Graduado;
use App\Constants\ConstAgregarEstudio\TituloConvalidado;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CrearCertificadosMasivosRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }


    protected function prepareForValidation()
    {
        $this->merge([
            'tipo_estudio'       => $this->input('tipo_estudio') ?? 'Curso programado o capacitación',
            'graduado'           => $this->input('graduado') ?? 'Si',
            'titulo_convalidado' => $this->input('titulo_convalidado') ?? 'No',
            'fecha_graduacion'   => $this->input('fecha_graduacion') ?? null,
            'fecha_convalidacion'=> $this->input('fecha_convalidacion') ?? null,
            'resolucion_convalidacion' => $this->input('resolucion_convalidacion') ?? null,

        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Escribe en la MISMA tabla `estudios` que el formulario del docente, así que tiene
            // que validar contra el mismo catálogo. Antes usaba la constante fija `TiposEstudio`,
            // cuyos 12 valores ya no coinciden con los 17 del catálogo administrable: un
            // certificado generado con "Pregrado en medicina humana o composición musical" no
            // existía en `niveles_formacion_academica`, así que el motor del escalafón nunca lo
            // contaba y el docente no podía editarlo.
            'tipo_estudio'              => ['required', 'string', Rule::exists('niveles_formacion_academica', 'nivel_formacion')->where('activo', true)],
            // Valida que `tipo_estudio` sea requerido y que su valor esté dentro de los valores definidos en `TiposEstudio`.
            'graduado'                  => ['required', 'string', Rule::in(Graduado::all())],
            // Valida que `graduado` sea requerido y que su valor esté dentro de los valores definidos en `Graduado`.
            'institucion'               => 'required|string|min:7|max:100|regex:/^[\pL\pN\s\-]+$/u',
            // Valida que `institucion` sea requerido, de tipo `string`, con un mínimo de 7 caracteres, un máximo de 100 caracteres,
            // y que coincida con el patrón de letras, números, espacios y guiones.
            'fecha_graduacion'          => 'nullable|date',
            // Valida que `fecha_graduacion` sea opcional (`nullable`) y de tipo `date`.
            'titulo_convalidado'        => ['required', 'string', Rule::in(TituloConvalidado::all())],
            // Valida que `titulo_convalidado` sea requerido y que su valor esté dentro de los valores definidos en `TituloConvalidado`.
            'fecha_convalidacion'       => 'nullable|date',
            // Valida que `fecha_convalidacion` sea opcional (`nullable`) y de tipo `date`.
            'resolucion_convalidacion'  => 'nullable|string|min:7|max:100|regex:/^[\pL\pN\s\-]+$/u',
            // Valida que `resolucion_convalidacion` sea opcional (`nullable`), de tipo `string`, con un mínimo de 7 caracteres,
            // un máximo de 100 caracteres y que coincida con el patrón de letras, números, espacios y guiones.
            'posible_fecha_graduacion'  => 'nullable|date',
            // Valida que `posible_fecha_graduacion` sea opcional (`nullable`) y de tipo `date`.
            'titulo_estudio'            => 'required|string|min:7|max:100|regex:/^[\pL\pN\s\-]+$/u',
            // Valida que `titulo_estudio` sea opcional (`nullable`), de tipo `string`, con un mínimo de 7 caracteres,
            // un máximo de 100 caracteres y que coincida con el patrón de letras, números, espacios y guiones.
            'fecha_inicio'              => 'required|date',
            // Valida que `fecha_inicio` sea requerido y de tipo `date`.
            'fecha_fin'                 => 'nullable|date',
            // Valida que `fecha_fin` sea opcional (`nullable`) y de tipo `date`.
            'docentes'                  => 'required|array|min:1',
            // Valida que `docentes` sea requerido, de tipo `array` y contenga al menos un elemento.
            'docentes.*'                => 'exists:users,id',

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
