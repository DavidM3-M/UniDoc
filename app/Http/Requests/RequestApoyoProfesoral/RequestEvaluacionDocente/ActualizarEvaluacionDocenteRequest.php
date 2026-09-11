<?php

namespace App\Http\Requests\RequestApoyoProfesoral\RequestEvaluacionDocente;

use App\Constants\ConstDocente\EstadoEvaluacionDocente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarEvaluacionDocenteRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Apoyo Profesoral` del grupo de rutas.
    }

    /**
     * Reglas de validación que se aplican a la solicitud.
     */
    public function rules(): array
    {
        return [
            // Mismos límites que al asignar, pero todos los campos son opcionales:
            // se permite actualizar solo el promedio, solo el estado, o ambos.
            'promedio_evaluacion_docente' => 'sometimes|required|numeric|min:0|max:5|decimal:0,1',
            'estado_evaluacion_docente' => ['sometimes', 'required', 'string', Rule::in(EstadoEvaluacionDocente::all())],
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
