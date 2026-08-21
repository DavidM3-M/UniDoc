<?php

namespace App\Http\Requests\RequestAdmin\RequestExamenIdioma;

use App\Constants\ClavePrimaria;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CrearExamenIdiomaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Administrador` del grupo de rutas.
    }

    public function rules(): array
    {
        return [
            // `bail` + `max` antes del `exists`: `id_idioma_catalogo` es un smallint y un valor
            // fuera de rango haría fallar la consulta en vez de no encontrar la fila.
            'idioma_catalogo_id' => 'bail|required|integer|min:1|max:' . ClavePrimaria::SMALLINT_MAXIMO
                . '|exists:catalogo_idiomas,id_idioma_catalogo',

            'nombre_examen' => [
                'required',
                'string',
                'max:150',
                Rule::unique('examenes_idioma', 'nombre_examen')
                    ->where('idioma_catalogo_id', $this->idiomaParaUnicidad()),
            ],

            // Vacío = el certificado no vence (ej. Cambridge).
            'vigencia_meses' => 'nullable|integer|min:1|max:240',

            'activo' => 'sometimes|boolean',
        ];
    }

    private function idiomaParaUnicidad(): ?int
    {
        $id = $this->input('idioma_catalogo_id');

        return ClavePrimaria::fueraDeRango($id) ? null : (int) $id;
    }

    public function messages(): array
    {
        return [
            'nombre_examen.unique' => 'Ese idioma ya tiene un examen con ese nombre.',
        ];
    }

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
