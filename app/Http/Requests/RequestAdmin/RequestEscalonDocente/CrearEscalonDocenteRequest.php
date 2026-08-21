<?php

namespace App\Http\Requests\RequestAdmin\RequestEscalonDocente;

use App\Constants\ClavePrimaria;
use App\Constants\ConstAgregarIdioma\NivelIdioma;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CrearEscalonDocenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Administrador` del grupo de rutas.
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:50', 'unique:escalones_docente,nombre'],
            'orden' => 'required|integer|min:1|max:100',

            // Todos nullable a propósito: un escalón sin requisito en ese campo no lo exige
            // (así se modela el escalón base, ej. Auxiliar, sin ningún requisito propio).
            'formacion_minima' => [
                'nullable',
                'string',
                'max:100',
                // El motor del escalafón compara este texto literalmente contra
                // `niveles_formacion_academica.nivel_formacion`. Sin este `exists`, la API aceptaba
                // cualquier cadena y el escalón resultante no lo cumplía nadie, sin dar ningún error.
                Rule::exists('niveles_formacion_academica', 'nivel_formacion'),
            ],

            // Van siempre juntos: un nivel MCER sin decir de qué idioma es ambiguo.
            'idioma_catalogo_id' => 'bail|nullable|required_with:nivel_mcer_minimo|integer|min:1|max:'
                . ClavePrimaria::SMALLINT_MAXIMO . '|exists:catalogo_idiomas,id_idioma_catalogo',
            'nivel_mcer_minimo' => [
                'nullable',
                'required_with:idioma_catalogo_id',
                'string',
                Rule::in(NivelIdioma::all()),
            ],

            'puntaje_minimo' => 'nullable|integer|min:0|max:9999',
            'meses_minimos' => 'nullable|integer|min:0|max:960',

            // Reemplaza el umbral único y global (eliminado): cada escalón exige la suya, y se
            // compara contra el mismo evaluacion_docentes.promedio_evaluacion_docente de siempre.
            'evaluacion_minima' => 'nullable|numeric|min:0|max:5',

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
