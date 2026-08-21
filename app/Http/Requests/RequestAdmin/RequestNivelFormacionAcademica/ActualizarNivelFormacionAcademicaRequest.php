<?php

namespace App\Http\Requests\RequestAdmin\RequestNivelFormacionAcademica;

use App\Models\NivelFormacionAcademica;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarNivelFormacionAcademicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Administrador` del grupo de rutas.
    }

    public function rules(): array
    {
        // Si solo se envía nivel_formacion (sin nivel_academico), el unique necesita el
        // nivel_academico actual del registro para comparar contra el par correcto.
        $nivelAcademicoActual = $this->input(
            'nivel_academico',
            NivelFormacionAcademica::find($this->route('id'))?->nivel_academico
        );

        return [
            'nivel_academico' => ['sometimes', 'required', 'string', 'max:100'],
            'nivel_formacion' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                // Se ignora el propio registro para que reenviar el mismo par no dispare el unique.
                Rule::unique('niveles_formacion_academica')
                    ->where(fn ($query) => $query->where('nivel_academico', $nivelAcademicoActual))
                    ->ignore($this->route('id'), 'id_nivel_formacion_academica'),
            ],

            // Jerarquía para el escalafón: mayor número, nivel más alto. Nulo = no participa.
            'orden' => 'sometimes|nullable|integer|min:0|max:999',

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
