<?php

namespace App\Http\Requests\RequestTalentoHumano\RequestAscensos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ActualizarAscensosRequest extends FormRequest
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
            'user_id' => 'sometimes|integer|exists:users,id',
            'id_convocatoria' => 'sometimes|integer|exists:convocatorias,id_convocatoria',
            'nuevo_cargo' => 'sometimes|string|max:255',
            'area' => 'sometimes|string|max:255',
            'fecha_ascenso' => 'sometimes|date',
            'observaciones' => 'nullable|string',
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
