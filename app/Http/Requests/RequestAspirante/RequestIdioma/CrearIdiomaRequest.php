<?php

namespace App\Http\Requests\RequestAspirante\RequestIdioma;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\ClavePrimaria;
use App\Constants\ConstAgregarIdioma\NivelIdioma;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CrearIdiomaRequest extends FormRequest
{
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
            // Ya no es texto libre sin validar: debe existir en el catálogo `catalogo_idiomas`
            // (el controlador sobreescribe este valor con el del catálogo cuando se manda el id).
            'idioma'             => ['required','string','max:255', Rule::exists('catalogo_idiomas', 'nombre_idioma')->where('activo', true)],
            'idioma_catalogo_id' => 'bail|nullable|integer|min:1|max:' . ClavePrimaria::SMALLINT_MAXIMO . '|exists:catalogo_idiomas,id_idioma_catalogo',
            // `institucion_idioma` capturaba siempre el examen/entidad certificadora (IELTS,
            // TOEFL, Cambridge...) como texto libre. Se relaja el regex (nombres de examen traen
            // siglas/paréntesis) y se agrega el id del catálogo `examenes_idioma`, opcional: no
            // todo examen del mundo va a estar catalogado, se permite el fallback de texto libre.
            'institucion_idioma' => 'required|string|max:255',
            'examen_idioma_id'   => 'bail|nullable|integer|min:1|max:' . ClavePrimaria::SMALLINT_MAXIMO . '|exists:examenes_idioma,id_examen_idioma',
            // Puntaje bruto del certificado. Si el examen del catálogo tiene rangos cargados es
            // obligatorio y debe caer en uno de ellos — eso lo verifica `ValidaPuntajeYNivel`,
            // porque depende de datos del catálogo y no se puede expresar aquí.
            'puntaje_obtenido'   => 'nullable|numeric|min:0|max:9999',
            'fecha_certificado'  => 'required|date',//poner este campo otra ves a requerido
            // El campo `fecha_certificado` es obligatorio y debe ser una fecha válida.
            // `nivel` ya no es obligatorio siempre: cuando el examen tiene rangos, lo calcula el
            // servidor desde el puntaje. `ValidaPuntajeYNivel` lo exige solo cuando toca ponerlo
            // a mano (examen sin rangos o escrito a mano).
            'nivel'              => ['nullable','string' , Rule::in(NivelIdioma::all())],
            'archivo'            => 'required|file|mimes:pdf|max:2048', // Validación de archivo
            // El campo `archivo` es obligatorio, debe ser un archivo (`file`) con extensiones permitidas (`pdf`, `jpg`, `png`)
            // y su tamaño no debe exceder los 2048 KB.
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
                // Mensaje general de error.
                'errors' => $validator->errors(),
                // Incluye los errores específicos de validación generados por el validador.
            ], 422)
                // Devuelve un código de estado HTTP 422 (Unprocessable Entity) para indicar errores de validación.
        );
    }
}
