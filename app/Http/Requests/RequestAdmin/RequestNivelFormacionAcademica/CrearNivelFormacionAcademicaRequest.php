<?php

namespace App\Http\Requests\RequestAdmin\RequestNivelFormacionAcademica;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CrearNivelFormacionAcademicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Administrador` del grupo de rutas.
    }

    public function rules(): array
    {
        return [
            // Texto libre a propósito: sin Rule::in ni cruce entre los dos campos.
            'nivel_academico' => ['required', 'string', 'max:100'],
            'nivel_formacion' => [
                'required',
                'string',
                'max:100',
                Rule::unique('niveles_formacion_academica')->where(
                    fn ($query) => $query->where('nivel_academico', $this->input('nivel_academico'))
                ),
            ],

            // Jerarquía para el escalafón: mayor número, nivel más alto. Nulo = el nivel no
            // participa en el escalafón (formación complementaria: diplomado, curso...).
            // Dos niveles pueden compartir orden a propósito, cuando son sinónimos.
            'orden' => 'nullable|integer|min:0|max:999',

            'activo' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nivel_formacion.unique' => 'Ya existe ese nivel de formación para ese nivel académico.',
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
