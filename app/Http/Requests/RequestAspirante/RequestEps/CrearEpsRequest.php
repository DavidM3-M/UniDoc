<?php

namespace App\Http\Requests\RequestAspirante\RequestEps;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\ConstEps\TipoAfiliacion;
use App\Constants\ConstEps\EstadoAfiliacion;
use App\Constants\ConstEps\TipoAfiliado;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CrearEpsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre_eps'                    => 'required|string|min:7|max:100|regex:/^[\pL\pN\s\-]+$/u',
            'tipo_afiliacion'               => ['required','string', Rule::in(TipoAfiliacion::all())],//llamo a la constante tipo afiliacion para obtener los tipos de afiliacion
            'estado_afiliacion'             => ['required','string', Rule::in(EstadoAfiliacion::all())],//llamo a la constante estado afiliacion para obtener los estados de afiliacion
            'fecha_afiliacion_efectiva'     => 'required|date',
            'fecha_finalizacion_afiliacion' => 'nullable|date',
            'tipo_afiliado'                 => ['required','string',  Rule::in(TipoAfiliado::all())],//llamo a la constante tipo afiliado para obtener los tipos de afiliado
            'numero_afiliado'               => 'nullable|string|max:100|regex:/^[\pL\pN\s\-]+$/u',
            'archivo'                       => 'required|file|mimes:pdf|max:2048', // Validación del archivo
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
