<?php

namespace App\Http\Requests\RequestSecretaria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Constants\ConstAgregarEstudio\TiposEstudio;
use App\Constants\ConstAgregarEstudio\Graduado;
use App\Constants\ConstAgregarEstudio\TituloConvalidado;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ActualizarCertificadoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para editar un certificado generado.
     *
     * Todos los campos son opcionales (`sometimes`); solo se validan los que se envían,
     * permitiendo actualizaciones parciales. Se reutilizan las mismas constantes y patrones
     * que en la creación masiva para mantener consistencia.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipo_estudio'              => ['sometimes', 'required', 'string', Rule::in(TiposEstudio::all())],
            'graduado'                  => ['sometimes', 'required', 'string', Rule::in(Graduado::all())],
            'institucion'               => 'sometimes|required|string|min:7|max:100|regex:/^[\pL\pN\s\-]+$/u',
            'fecha_graduacion'          => 'sometimes|nullable|date',
            'titulo_convalidado'        => ['sometimes', 'required', 'string', Rule::in(TituloConvalidado::all())],
            'fecha_convalidacion'       => 'sometimes|nullable|date',
            'resolucion_convalidacion'  => 'sometimes|nullable|string|min:7|max:100|regex:/^[\pL\pN\s\-]+$/u',
            'posible_fecha_graduacion'  => 'sometimes|nullable|date',
            'titulo_estudio'            => 'sometimes|required|string|min:7|max:100|regex:/^[\pL\pN\s\-]+$/u',
            'fecha_inicio'              => 'sometimes|required|date',
            'fecha_fin'                 => 'sometimes|nullable|date',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error en el formulario',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
