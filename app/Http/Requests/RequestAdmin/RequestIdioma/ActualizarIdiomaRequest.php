<?php

namespace App\Http\Requests\RequestAdmin\RequestIdioma;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarIdiomaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Administrador` del grupo de rutas.
    }

    public function rules(): array
    {
        return [
            'nombre_idioma' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('catalogo_idiomas', 'nombre_idioma')->ignore($this->route('id'), 'id_idioma_catalogo'),
            ],
            'activo' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_idioma.unique' => 'Ya existe un idioma con ese nombre.',
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
