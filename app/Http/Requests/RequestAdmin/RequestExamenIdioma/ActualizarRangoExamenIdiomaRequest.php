<?php

namespace App\Http\Requests\RequestAdmin\RequestExamenIdioma;

use App\Constants\ConstAgregarIdioma\NivelIdioma;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarRangoExamenIdiomaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Administrador` del grupo de rutas.
    }

    public function rules(): array
    {
        // No se permite mover el rango a otro examen: solo se edita su propio rango de puntaje
        // y su nivel MCER equivalente. El formulario siempre envía los tres juntos.
        return [
            'puntaje_min' => 'required|numeric|min:0|max:9999.99',
            'puntaje_max' => 'required|numeric|gte:puntaje_min|max:9999.99',

            'nivel_mcer' => ['required', 'string', Rule::in(NivelIdioma::all())],
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
