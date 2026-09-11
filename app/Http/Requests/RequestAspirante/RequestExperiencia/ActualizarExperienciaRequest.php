<?php

namespace App\Http\Requests\RequestAspirante\RequestExperiencia;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Concerns\ConservaValorDelCatalogo;
use App\Models\Aspirante\Experiencia;
use App\Constants\TextoLibre;
use App\Constants\ConstAgregarExperiencia\TrabajoActual;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarExperienciaRequest extends FormRequest
{
    use ConservaValorDelCatalogo;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mismo criterio que al crear: marcar el cargo como actual borra la fecha de finalización.
     *
     * Aquí importa más que en la creación, porque es el camino por el que un registro cerrado
     * pasa a ser el trabajo actual del docente: si no se limpiara, la fecha vieja quedaría en la
     * fila y congelaría la antigüedad que el escalafón debería seguir contando hasta el corte.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('trabajo_actual') === TrabajoActual::SI) {
            $this->merge(['fecha_finalizacion' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [

            'tipo_experiencia'             => [
                'sometimes',
                'required',
                'string',
                $this->reglaCatalogoVigente(
                    'tipo_experiencias',
                    'nombre_tipo_experiencia',
                    $this->valorGuardado(Experiencia::class, 'id_experiencia', 'tipo_experiencia')
                ),
            ],
             // Valida que `tipo_experiencia` sea opcional (`sometimes`), requerido si está presente, de tipo `string`,
            // y que corresponda a un tipo **activo** del catálogo `tipo_experiencias`, que administra el
            // rol Administrador. Se guarda el nombre, no el ID, por eso la validación es por nombre.
            'institucion_experiencia'      => 'sometimes|required|string|min:3|max:100|' . TextoLibre::SIN_EMOJIS,
              // Valida que `institucion_experiencia` sea opcional (`sometimes`), requerido si está presente, de tipo `string`,
            // con un mínimo de 3 caracteres, un máximo de 100 caracteres y que coincida con el patrón de letras, números, espacios y guiones.
            'es_uniautonoma'               => 'sometimes|boolean',
            'cargo'                        => 'sometimes|required|string|min:3|max:100|' . TextoLibre::SIN_EMOJIS,
              // Valida que `cargo` sea opcional (`sometimes`), requerido si está presente, de tipo `string`,
            // con un mínimo de 3 caracteres, un máximo de 100 caracteres y que coincida con el patrón de letras, números, espacios y guiones.
            'trabajo_actual'               => ['sometimes','required','string', Rule::in(TrabajoActual::all())],
             // Valida que `trabajo_actual` sea opcional (`sometimes`), requerido si está presente y que su valor esté
            // dentro de los valores definidos en `TrabajoActual`.
            'intensidad_horaria'           => 'sometimes|nullable|integer|min:1|max:127',
             // Valida que `intensidad_horaria` sea opcional (`sometimes`), puede ser nulo (`nullable`), de tipo `integer`,
            // con un valor mínimo de 1 y un máximo de 168 (horas en una semana).
            // Mismo criterio que en `CrearExperienciaRequest`: los meses declarados pueden no
            // coincidir con el cálculo por fechas y eso es legítimo.
            'meses_trabajados'             => 'sometimes|nullable|integer|min:1|max:1200',
            'fecha_inicio'                 => 'sometimes|required|date',
            // Valida que `fecha_inicio` sea opcional (`sometimes`), requerido si está presente y de tipo `date`.
            'fecha_finalizacion'           => 'sometimes|nullable|date|after_or_equal:fecha_inicio',
            // Valida que `fecha_finalizacion` sea opcional (`sometimes`), puede ser nulo (`nullable`), de tipo `date`,
            // y que sea igual o posterior a `fecha_inicio`. Que sea obligatoria cuando el cargo ya
            // terminó lo decide `withValidator()`, que sí puede mirar lo que hay guardado.
            'fecha_expedicion_certificado' => 'sometimes|nullable|date',
            // Valida que `fecha_expedicion_certificado` sea opcional (`sometimes`), puede ser nulo (`nullable`) y de tipo `date`.
            'archivo'                      => 'sometimes|nullable|file|mimes:pdf|max:2048',
             // Valida que `archivo` sea opcional (`sometimes`), puede ser nulo (`nullable`), de tipo `file`,
            // con extensiones permitidas `pdf`, `jpg`, `png` y un tamaño máximo de 2048 KB.
    
        ];
    }
    /**
     * Una experiencia que ya terminó tiene que decir cuándo.
     *
     * No se puede resolver con un `required_if` porque la actualización es parcial: el docente
     * puede mandar solo `trabajo_actual` y tener la fecha ya guardada, o al revés. La coherencia
     * se evalúa entonces sobre el estado que quedaría después de guardar —lo enviado sobre lo
     * almacenado—, no sobre lo enviado.
     *
     * Sin esta regla, cambiar el cargo de 'Si' a 'No' borraría la fecha de fin (la limpia
     * `prepareForValidation()` mientras es actual) y dejaría el registro contando antigüedad
     * hasta hoy para siempre.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $trabajoActual = $this->input(
                'trabajo_actual',
                $this->valorGuardado(Experiencia::class, 'id_experiencia', 'trabajo_actual')
            );

            if ($trabajoActual !== TrabajoActual::NO) {
                return;
            }

            $fechaFinalizacion = $this->has('fecha_finalizacion')
                ? $this->input('fecha_finalizacion')
                : $this->valorGuardado(Experiencia::class, 'id_experiencia', 'fecha_finalizacion');

            if (empty($fechaFinalizacion)) {
                $validator->errors()->add(
                    'fecha_finalizacion',
                    'La fecha de finalización es obligatoria cuando el cargo ya no es el trabajo actual.'
                );
            }
        });
    }

    protected function failedValidation(Validator $validator)
    // Método que se ejecuta cuando la validación falla.
    {
        throw new HttpResponseException(
        // Lanza una excepción de respuesta HTTP personalizada.
           response()->json([
                'success' => false,
                // Indica que la solicitud no fue exitosa.
                'message' => 'Error en el formulario',
                // Mensaje de error general.
                'errors' => $validator->errors(),
                // Incluye los errores de validación específicos.
            ], 422)
                // Retorna una respuesta JSON con un código de estado HTTP 422 (Unprocessable Entity).
        );
    }
}
