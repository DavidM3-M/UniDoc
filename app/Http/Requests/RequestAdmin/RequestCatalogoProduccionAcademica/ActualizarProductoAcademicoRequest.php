<?php

namespace App\Http\Requests\RequestAdmin\RequestCatalogoProduccionAcademica;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarProductoAcademicoRequest extends FormRequest
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
            'nombre_producto_academico' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                // Se ignora el propio registro para que reenviar el mismo nombre no dispare el unique.
                Rule::unique('producto_academicos', 'nombre_producto_academico')
                    ->ignore($this->route('id'), 'id_producto_academico'),
            ],

            'activo' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_producto_academico.unique' => 'Ya existe un tipo de producto académico con ese nombre.',
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
