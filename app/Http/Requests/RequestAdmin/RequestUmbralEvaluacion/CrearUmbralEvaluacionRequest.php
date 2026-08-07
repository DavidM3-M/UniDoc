<?php

namespace App\Http\Requests\RequestAdmin\RequestUmbralEvaluacion;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CrearUmbralEvaluacionRequest extends FormRequest
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
     * Si no se indica desde cuándo rige, el umbral entra en vigor hoy.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->filled('vigencia_desde')) {
            $this->merge(['vigencia_desde' => now()->toDateString()]);
        }
    }

    /**
     * Reglas de validación que se aplican a la solicitud.
     */
    public function rules(): array
    {
        return [
            // Mismo rango y precisión que evaluacion_docentes.promedio_evaluacion_docente,
            // que es el campo contra el que se compara este umbral.
            'valor_minimo' => 'required|numeric|min:0|max:5|decimal:0,1',

            // No se permite retrodatar: cambiar el pasado alteraría categorías ya otorgadas,
            // que es justamente lo que la regla de no retroactividad busca evitar.
            'vigencia_desde' => 'required|date|after_or_equal:today',

            'observaciones' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'vigencia_desde.after_or_equal' => 'La vigencia no puede iniciar en una fecha pasada: alteraría categorías ya otorgadas.',
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
