<?php

namespace App\Http\Requests\RequestAdmin\RequestEscalonDocente;

use App\Constants\ClavePrimaria;
use App\Constants\ConstAgregarIdioma\NivelIdioma;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarEscalonDocenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Administrador` del grupo de rutas.
    }

    public function rules(): array
    {
        return [
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('escalones_docente', 'nombre')->ignore($this->route('id'), 'id_escalon'),
            ],
            'orden' => 'sometimes|required|integer|min:1|max:100',

            // `sometimes|nullable` para formacion_minima/nivel_mcer_minimo/puntaje_minimo/
            // meses_minimos: si el campo viene en el body debe poder ser null explícito (así se
            // quita un requisito sin tener que enviar los demás), pero si no viene no se toca.
            'formacion_minima' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                // El motor del escalafón compara este texto literalmente contra
                // `niveles_formacion_academica.nivel_formacion`. Sin este `exists`, la API aceptaba
                // cualquier cadena y el escalón resultante no lo cumplía nadie, sin dar ningún error.
                Rule::exists('niveles_formacion_academica', 'nivel_formacion'),
            ],

            'idioma_catalogo_id' => 'bail|sometimes|nullable|required_with:nivel_mcer_minimo|integer|min:1|max:'
                . ClavePrimaria::SMALLINT_MAXIMO . '|exists:catalogo_idiomas,id_idioma_catalogo',
            'nivel_mcer_minimo' => [
                'sometimes',
                'nullable',
                'required_with:idioma_catalogo_id',
                'string',
                Rule::in(NivelIdioma::all()),
            ],

            'puntaje_minimo' => 'sometimes|nullable|integer|min:0|max:9999',
            'meses_minimos' => 'sometimes|nullable|integer|min:0|max:960',
            'evaluacion_minima' => 'sometimes|nullable|numeric|min:0|max:5',

            'activo' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un escalón con ese nombre.',
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
