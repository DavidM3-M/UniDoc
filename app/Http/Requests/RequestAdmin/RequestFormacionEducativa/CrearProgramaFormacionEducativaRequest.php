<?php

namespace App\Http\Requests\RequestAdmin\RequestFormacionEducativa;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CrearProgramaFormacionEducativaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Administrador` del grupo de rutas.
    }

    public function rules(): array
    {
        return [
            'nombre_programa' => ['required', 'string', 'max:255'],
            'titulo_otorgado' => ['nullable', 'string', 'max:255'],
            // Texto libre: se busca/crea la institución por nombre (alta manual, sin código SNIES).
            'institucion_nombre' => ['required', 'string', 'max:255'],
            'nivel_formacion_academica_id' => ['required', 'integer', 'exists:niveles_formacion_academica,id_nivel_formacion_academica'],
            'modalidad' => ['nullable', 'string', 'max:50'],
            'activo' => 'sometimes|boolean',
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
