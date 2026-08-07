<?php

namespace App\Http\Requests\RequestApoyoProfesoral\RequestEvaluacionDocente;

use App\Constants\ConstDocente\EstadoEvaluacionDocente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class AsignarEvaluacionDocenteRequest extends FormRequest
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
            // La evaluación docente se califica de 0 a 5 con un decimal (4.5, 3.0, 3.2...),
            // coherente con el `decimal(3,1)` de la columna `promedio_evaluacion_docente`.
            'promedio_evaluacion_docente' => 'required|numeric|min:0|max:5|decimal:0,1',

            // El estado es opcional al asignar: si no se envía, el controlador aplica 'Pendiente'.
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
