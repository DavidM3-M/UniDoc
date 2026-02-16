<?php

namespace App\Http\Requests\RequestTalentoHumano\RequestConvocatoria;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\ConstTalentoHumano\EstadoConvocatoria;
use App\Constants\ConstTalentoHumano\Aprobaciones;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Normalize frontend field names to those expected by this Request
        if ($this->has('perfil_profesional') && !$this->has('perfil_profesional_id') && !$this->has('perfil_profesional_outro')) {
            $val = $this->input('perfil_profesional');
            if (is_numeric($val)) {
                $this->merge(['perfil_profesional_id' => (int)$val]);
            } elseif (!empty($val)) {
                $this->merge(['perfil_profesional_outro' => $val]);
            }
        }

        if ($this->has('experiencia_requerida') && !$this->has('experiencia_requerida_id') && !$this->has('experiencia_requerida_fecha')) {
            $val = $this->input('experiencia_requerida');
            if (is_numeric($val)) {
                $this->merge(['experiencia_requerida_id' => (int)$val]);
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                $this->merge(['experiencia_requerida_fecha' => $val]);
            }
        }

        // Normalize 'facultad' coming from frontend to 'facultad_id' or 'facultad_otro'
        if ($this->has('facultad') && !$this->has('facultad_id') && !$this->has('facultad_otro')) {
            $val = $this->input('facultad');
            if (is_numeric($val)) {
                $this->merge(['facultad_id' => (int)$val]);
            } elseif (!empty($val)) {
                $this->merge(['facultad_otro' => $val]);
            }
        }

        // Normalize 'cargo_solicitado' to 'tipo_cargo_id' or 'tipo_cargo_otro'
        if ($this->has('cargo_solicitado') && !$this->has('tipo_cargo_id') && !$this->has('tipo_cargo_otro')) {
            $val = $this->input('cargo_solicitado');
            if (is_numeric($val)) {
                $this->merge(['tipo_cargo_id' => (int)$val]);
            } elseif (!empty($val)) {
                $this->merge(['tipo_cargo_otro' => $val]);
            }
        }

        // Decode JSON-encoded arrays coming from FormData as strings
        $arrayFields = [
            'requisitos_experiencia',
            'requisitos_idiomas',
            'requisitos_adicionales',
            'avales_establecidos',
        ];

        foreach ($arrayFields as $f) {
            if ($this->has($f)) {
                $val = $this->input($f);
                if (is_string($val) && ($decoded = json_decode($val, true)) !== null) {
                    $this->merge([$f => $decoded]);
                }
            }
        }

        // Normalize experiencia_requerida_cantidad and experiencia_requerida_unidad to cantidad_experiencia and unidad_experiencia
        if ($this->has('experiencia_requerida_cantidad') || $this->has('experiencia_requerida_unidad')) {
            $cantidad = $this->input('experiencia_requerida_cantidad');
            $unidad = $this->input('experiencia_requerida_unidad');
            
            if (!empty($cantidad) || !empty($unidad)) {
                if (!empty($cantidad)) {
                    $this->merge(['cantidad_experiencia' => (int)$cantidad]);
                }
                if (!empty($unidad)) {
                    // Normalize unit names (accept variations)
                    $unidad = trim($unidad);
                    $unidades_map = [
                        'año' => 'Años',
                        'años' => 'Años',
                        'year' => 'Años',
                        'years' => 'Años',
                        'mes' => 'Meses',
                        'meses' => 'Meses',
                        'month' => 'Meses',
                        'months' => 'Meses',
                        'semana' => 'Semanas',
                        'semanas' => 'Semanas',
                        'week' => 'Semanas',
                        'weeks' => 'Semanas',
                        'día' => 'Días',
                        'dias' => 'Días',
                        'dias' => 'Días',
                        'day' => 'Días',
                        'days' => 'Días',
                    ];
                    
                    $unidad_normalizada = $unidades_map[strtolower($unidad)] ?? $unidad;
                    $this->merge(['unidad_experiencia' => $unidad_normalizada]);
                }
            }
        }

        // Normalize referencia_experiencia from various field names
        $referenciaNames = ['referencia_experiencia', 'referencia_cargo', 'contexto_cargo', 'referencia', 'contexto'];
        foreach ($referenciaNames as $name) {
            if ($this->has($name) && !$this->has('referencia_experiencia')) {
                $val = $this->input($name);
                if (!empty($val)) {
                    $this->merge(['referencia_experiencia' => $val]);
                    break;
                }
            }
        }
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
            'tipo_cargo_id' => 'nullable|integer',
            'tipo_cargo_otro' => 'nullable|string|max:255',
            'facultad_id' => 'nullable|integer',
            'facultad_otro' => 'nullable|string|max:255',
            'cursos' => 'nullable|string',
            'tipo_vinculacion' => 'required|string|max:255',
            'personas_requeridas' => 'required|integer|min:1',
            'fecha_inicio_contrato' => 'required|date|after:fecha_cierre',
            'perfil_profesional_id' => 'nullable|integer',
            'perfil_profesional_outro' => 'nullable|string|max:255',
            'experiencia_requerida_id' => 'nullable|integer',
            'experiencia_requerida_fecha' => 'nullable|date',
            'solicitante' => 'required|string|max:255',
            'avales_establecidos' => ['required','array','min:1'],
            'avales_establecidos.*' => ['string', Rule::in(Aprobaciones::all())],

            // Nuevos campos para requerimientos detallados
            'requisitos_experiencia' => 'nullable|array',
            'requisitos_experiencia.*' => 'numeric|min:0|max:50',
            'requisitos_idiomas' => 'nullable|array',
            'requisitos_idiomas.*' => 'string|in:A1,A2,B1,B2,C1,C2',
            'requisitos_adicionales' => 'nullable|array',
            'anos_experiencia_requerida' => 'nullable|integer|min:0|max:100',
            'tipo_experiencia_requerida' => 'nullable|string|max:255',
            'cantidad_experiencia' => 'nullable|integer|min:0',
            'unidad_experiencia' => 'nullable|string|max:50',
            'referencia_experiencia' => 'nullable|string|max:1000',

            // Archivo opcional
            'archivo' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->sometimes(['facultad', 'facultad_otro'], 'required_without:facultad_otro|required_without:facultad', function ($input) {
            if (!empty($input->tipo_cargo_id)) {
                $tipo = \App\Models\TipoCargo::find($input->tipo_cargo_id);
                if ($tipo && property_exists($tipo, 'es_administrativo')) {
                    return $tipo->es_administrativo === false;
                }
            }
            return false;
        });
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
            'tipo_cargo_id.exists' => 'El tipo de cargo seleccionado no es válido.',
            'tipo_cargo_otro.max' => 'El tipo de cargo personalizado no puede tener más de 255 caracteres.',
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
            'avales_establecidos.required' => 'Debe especificar al menos una aprobación requerida.',
            'avales_establecidos.array' => 'Las aprobaciones deben ser un arreglo.',
            'avales_establecidos.*.in' => 'Una o más aprobaciones no son válidas.',

            'archivo.file' => 'El archivo debe ser un archivo válido.',
            'archivo.mimes' => 'El archivo debe ser de tipo: PDF, DOC o DOCX.',
            'archivo.max' => 'El archivo no debe superar los 10MB.',
        ];

    }


}
