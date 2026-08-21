<?php

namespace App\Http\Requests\RequestAspirante\RequestIdioma;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Concerns\ConservaValorDelCatalogo;
use App\Models\Aspirante\Idioma;
use App\Constants\ClavePrimaria;
use App\Constants\ConstAgregarIdioma\NivelIdioma;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarIdiomaRequest extends FormRequest
{
    use ConservaValorDelCatalogo;

    use ValidaPuntajeYNivel;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    // Método que determina si el usuario está autorizado para realizar esta solicitud.
    {
        return true;
    // Retorna `true`, lo que significa que cualquier usuario está autorizado para usar esta solicitud.
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    // Método que define las reglas de validación para los datos enviados en la solicitud.
    {
        return [
            'idioma'             => ['sometimes','required','string','max:255', $this->reglaCatalogoVigente(
                'catalogo_idiomas',
                'nombre_idioma',
                $this->valorGuardado(Idioma::class, 'id_idioma', 'idioma')
            )],
              // El campo `idioma` es opcional (`sometimes`), pero si está presente, debe existir en
            // el catálogo real `catalogo_idiomas` (activo).
            'idioma_catalogo_id' => 'bail|sometimes|nullable|integer|min:1|max:' . ClavePrimaria::SMALLINT_MAXIMO . '|exists:catalogo_idiomas,id_idioma_catalogo',
            'institucion_idioma' => 'sometimes|required|string|max:255',
             // El campo `institucion_idioma` es opcional, pero si está presente, es obligatorio, debe ser una cadena
            // con un máximo de 255 caracteres. Ya no exige el regex de solo letras/números: los
            // nombres de examen traen siglas y paréntesis (ej. "TOEFL iBT").
            'examen_idioma_id'   => 'bail|sometimes|nullable|integer|min:1|max:' . ClavePrimaria::SMALLINT_MAXIMO . '|exists:examenes_idioma,id_examen_idioma',
            // Ver `ValidaPuntajeYNivel`: si el examen tiene rangos cargados, el puntaje es
            // obligatorio y debe caer en uno de ellos.
            'puntaje_obtenido'   => 'sometimes|nullable|numeric|min:0|max:9999',
            'fecha_certificado'  => 'sometimes|nullable|date',//poner este campo otra ves a requerido
             // El campo `fecha_certificado` es opcional, pero si está presente, debe ser una fecha válida.
            // Nota: Se menciona que este campo debe volver a ser obligatorio.
            // `nivel` deja de ser obligatorio siempre: cuando el examen tiene rangos lo calcula
            // el servidor desde el puntaje (ver `IdiomaController::resolverCatalogos()`).
            'nivel'              => ['sometimes','nullable','string', Rule::in(NivelIdioma::all())],
            'archivo'            => 'sometimes|nullable|file|mimes:pdf|max:2048', // Validación de archivo
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validarPuntajeYNivel($validator);
    }
    protected function failedValidation(Validator $validator)
    // Método que se ejecuta cuando la validación falla.
    {
        throw new HttpResponseException(
        // Lanza una excepción `HttpResponseException` para devolver una respuesta JSON personalizada.
           response()->json([
                'success' => false,
        // Indica que la solicitud no fue exitosa.
                'message' => 'Error en el formulario',
        // Mensaje de error genérico.
                'errors' => $validator->errors(),
        // Incluye los errores específicos de validación generados por el validador.
            ], 422)
                        // Devuelve un código de estado HTTP 422 (Unprocessable Entity) para indicar errores de validación.
        );
    }
}
