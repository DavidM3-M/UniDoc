<?php

namespace App\Http\Requests\RequestAspirante\RequestProduccionAcademica;

use App\Constants\ClavePrimaria;
use App\Http\Requests\Concerns\NormalizaIdentificadoresProduccion;
use Illuminate\Foundation\Http\FormRequest;
use App\Constants\TextoLibre;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;


class CrearProduccionAcademicaRequest extends FormRequest
{
    use NormalizaIdentificadoresProduccion;

    /**
     * Deja el DOI sin el resolvedor y los campos vacíos en null antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizarIdentificadores());
    }

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
        return array_merge($this->reglasIdentificadores(), [
         'ambito_divulgacion_id' => [
             // `bail` + `max` antes del `exists`: `id_ambito_divulgacion` es un smallint y
             // consultar un valor fuera de ese rango hace fallar la consulta en vez de no
             // encontrar la fila, lo que devolvería un 500 en lugar de un 422.
             'bail',
             'required',
             'integer',
             'min:1',
             'max:' . ClavePrimaria::SMALLINT_MAXIMO,
             Rule::exists('ambito_divulgacions', 'id_ambito_divulgacion')->where('activo', true),
         ],
          // El campo `ambito_divulgacion_id` es obligatorio (`required`), debe ser un número entero (`integer`)
            // y debe existir en la tabla `ambito_divulgacions` como ámbito **activo**. Un ámbito que el
            // Administrador retiró del catálogo no se puede usar en producciones nuevas, aunque las
            // producciones históricas que ya lo referencian siguen intactas.
         'titulo' => 'required|string|max:255|' . TextoLibre::SIN_EMOJIS,
          // El campo `titulo` es obligatorio, debe ser una cadena (`string`) con un máximo de 255 caracteres
            // y cumplir con un patrón regex que permite letras, números, espacios y guiones.
         'numero_autores' => 'required|integer|min:1|max:127',
        // El campo `numero_autores` es obligatorio y debe ser un número entero (`integer`).
         'medio_divulgacion' => 'required|string|max:255|' . TextoLibre::SIN_EMOJIS,
         // El campo `medio_divulgacion` es obligatorio, debe ser una cadena con un máximo de 255 caracteres
            // y cumplir con un patrón regex que permite letras, números, espacios y guiones.

         'fecha_divulgacion' => 'required|date',
        // El campo `fecha_divulgacion` es obligatorio y debe ser una fecha válida (`date`).
         'archivo' => 'required|file|mimes:pdf|max:2048',
          // El campo `archivo` es obligatorio, debe ser un archivo (`file`) con extensiones permitidas
            // (`pdf`, `doc`, `docx`) y su tamaño no debe exceder los 2048 KB.

         // `doi`, `issn_isbn` y `url_publicacion` los aporta reglasIdentificadores(): son los
         // datos con los que el Evaluador de Producción verifica la publicación. Opcionales.
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mensajesIdentificadores();
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
