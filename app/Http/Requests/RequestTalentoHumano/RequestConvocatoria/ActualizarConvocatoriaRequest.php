<?php

namespace App\Http\Requests\RequestTalentoHumano\RequestConvocatoria;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarConvocatoriaRequest extends FormRequest
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
        $convocatoriaId = $this->route('id'); // Obtener el ID de la convocatoria desde la ruta

        return [
            // Campos obligatorios
            'nombre_convocatoria' => 'required|string|max:255|unique:convocatorias,nombre_convocatoria,' . $convocatoriaId . ',id_convocatoria',
            'tipo' => 'required|string|max:255',
            'fecha_publicacion' => 'required|date',
            'fecha_cierre' => 'required|date|after:fecha_publicacion',
            'descripcion' => 'required|string',
            'estado_convocatoria' => 'required|string|in:Abierta,Cerrada,Finalizada',

            // Nuevos campos obligatorios
            'numero_convocatoria' => 'required|string|max:255|unique:convocatorias,numero_convocatoria,' . $convocatoriaId . ',id_convocatoria',
            'periodo_academico' => 'required|string|max:255',
            'cargo_solicitado' => 'required|string|max:255',
            'facultad' => 'required|string|max:255',
            'cursos' => 'required|string',
            'tipo_vinculacion' => 'required|string|max:255',
            'personas_requeridas' => 'required|integer|min:1',
            'fecha_inicio_contrato' => 'required|date|after:fecha_cierre',
            'perfil_profesional' => 'required|string',
            'experiencia_requerida' => 'required|string',
            'solicitante' => 'required|string|max:255',
            'aprobaciones' => 'required|string',

            // Archivo opcional
            'archivo' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre_convocatoria.required' => 'El nombre de la convocatoria es obligatorio.',
            'nombre_convocatoria.unique' => 'Ya existe una convocatoria con este nombre.',
            'tipo.required' => 'El tipo de convocatoria es obligatorio.',
            'fecha_publicacion.required' => 'La fecha de publicación es obligatoria.',
            'fecha_cierre.required' => 'La fecha de cierre es obligatoria.',
            'fecha_cierre.after' => 'La fecha de cierre debe ser posterior a la fecha de publicación.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'estado_convocatoria.required' => 'El estado de la convocatoria es obligatorio.',
            'estado_convocatoria.in' => 'El estado debe ser: Abierta, Cerrada o Finalizada.',

            'numero_convocatoria.required' => 'El número de convocatoria es obligatorio.',
            'numero_convocatoria.unique' => 'Ya existe una convocatoria con este número.',
            'periodo_academico.required' => 'El período académico es obligatorio.',
            'cargo_solicitado.required' => 'El cargo solicitado es obligatorio.',
            'facultad.required' => 'La facultad es obligatoria.',
            'cursos.required' => 'Los cursos son obligatorios.',
            'tipo_vinculacion.required' => 'El tipo de vinculación es obligatorio.',
            'personas_requeridas.required' => 'El número de personas requeridas es obligatorio.',
            'personas_requeridas.min' => 'Debe requerir al menos 1 persona.',
            'fecha_inicio_contrato.required' => 'La fecha de inicio de contrato es obligatoria.',
            'fecha_inicio_contrato.after' => 'La fecha de inicio debe ser posterior a la fecha de cierre.',
            'perfil_profesional.required' => 'El perfil profesional es obligatorio.',
            'experiencia_requerida.required' => 'La experiencia requerida es obligatoria.',
            'solicitante.required' => 'El solicitante es obligatorio.',
            'aprobaciones.required' => 'Las aprobaciones son obligatorias.',

            'archivo.file' => 'El archivo debe ser un archivo válido.',
            'archivo.mimes' => 'El archivo debe ser de tipo: PDF, DOC o DOCX.',
            'archivo.max' => 'El archivo no debe superar los 10MB.',
        ];
    }
}
