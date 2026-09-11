<?php

namespace App\Http\Requests\RequestAdmin\RequestExamenIdioma;

use App\Constants\ClavePrimaria;
use App\Models\ExamenIdioma;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarExamenIdiomaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Administrador` del grupo de rutas.
    }

    public function rules(): array
    {
        return [
            'idioma_catalogo_id' => 'bail|sometimes|required|integer|min:1|max:' . ClavePrimaria::SMALLINT_MAXIMO
                . '|exists:catalogo_idiomas,id_idioma_catalogo',

            'nombre_examen' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('examenes_idioma', 'nombre_examen')
                    ->where('idioma_catalogo_id', $this->idiomaEfectivo())
                    ->ignore($this->route('id'), 'id_examen_idioma'),
            ],

            'vigencia_meses' => 'nullable|integer|min:1|max:240',

            'activo' => 'sometimes|boolean',
        ];
    }

    /**
     * Idioma contra el que se mide la unicidad del nombre.
     *
     * Si la petición no trae `idioma_catalogo_id` (solo se está renombrando o cambiando la
     * vigencia), hay que comparar contra el idioma que el examen ya tiene; de lo contrario la
     * regla `unique` se evaluaría contra `null` y nunca encontraría el duplicado real.
     */
    private function idiomaEfectivo(): ?int
    {
        if ($this->filled('idioma_catalogo_id')) {
            $id = $this->input('idioma_catalogo_id');

            return ClavePrimaria::fueraDeRango($id) ? null : (int) $id;
        }

        if (ClavePrimaria::fueraDeRango($this->route('id'))) {
            return null;
        }

        return ExamenIdioma::find($this->route('id'))?->idioma_catalogo_id;
    }

    public function messages(): array
    {
        return [
            'nombre_examen.unique' => 'Ese idioma ya tiene un examen con ese nombre.',
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
