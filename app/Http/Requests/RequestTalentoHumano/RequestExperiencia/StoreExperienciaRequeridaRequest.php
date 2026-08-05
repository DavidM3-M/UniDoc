<?php

namespace App\Http\Requests\RequestTalentoHumano\RequestExperiencia;

use Illuminate\Foundation\Http\FormRequest;

class StoreExperienciaRequeridaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'descripcion_experiencia' => 'required|string|max:255',
            'horas_minimas' => 'required|integer|min:0',
            'anos_equivalentes' => 'required|integer|min:0',
            'es_administrativo' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'descripcion_experiencia.required' => 'La descripción de la experiencia es obligatoria.',
            'horas_minimas.required' => 'Las horas mínimas son obligatorias.',
            'horas_minimas.integer' => 'Las horas mínimas deben ser un número entero.',
            'anos_equivalentes.required' => 'Los años equivalentes son obligatorios.',
        ];
    }
}
