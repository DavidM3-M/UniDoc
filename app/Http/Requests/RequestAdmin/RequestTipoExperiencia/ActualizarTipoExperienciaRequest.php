<?php

namespace App\Http\Requests\RequestAdmin\RequestTipoExperiencia;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarTipoExperienciaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Administrador` del grupo de rutas.
    }

    /**
     * Reglas de validación que se aplican a la solicitud.
     */
    public function rules(): array
    {
        return [
            'nombre_tipo_experiencia' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                // Se ignora el propio registro para que reenviar el mismo nombre no dispare el unique.
                Rule::unique('tipo_experiencias', 'nombre_tipo_experiencia')
                    ->ignore($this->route('id'), 'id_tipo_experiencia'),
            ],

            'activo' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_tipo_experiencia.unique' => 'Ya existe un tipo de experiencia con ese nombre.',
        ];
    }

    /**
     * Manejo de errores de validación.
     */
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
