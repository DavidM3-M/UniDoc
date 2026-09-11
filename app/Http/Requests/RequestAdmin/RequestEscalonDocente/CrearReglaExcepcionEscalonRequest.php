<?php

namespace App\Http\Requests\RequestAdmin\RequestEscalonDocente;

use App\Constants\ClavePrimaria;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * `tipo_condicion` es un vocabulario controlado por código (ver
 * `MotorEscalafonDocenteService::cumpleCondicionExcepcion()`): hoy solo entiende 'formacion'.
 */
class CrearReglaExcepcionEscalonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Administrador` del grupo de rutas.
    }

    public function rules(): array
    {
        return [
            'tipo_condicion' => ['required', 'string', Rule::in(['formacion'])],
            'valor_condicion' => [
                'required',
                'string',
                'max:100',
                // El motor del escalafón compara este texto literalmente contra
                // `niveles_formacion_academica.nivel_formacion`. Sin este `exists`, la API aceptaba
                // cualquier cadena y el escalón resultante no lo cumplía nadie, sin dar ningún error.
                Rule::exists('niveles_formacion_academica', 'nivel_formacion'),
            ],

            'escalon_otorgado_id' => 'bail|required|integer|min:1|max:' . ClavePrimaria::SMALLINT_MAXIMO
                . '|exists:escalones_docente,id_escalon',

            'activo' => 'sometimes|boolean',
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
