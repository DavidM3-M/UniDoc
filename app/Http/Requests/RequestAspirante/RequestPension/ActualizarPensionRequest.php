<?php

namespace App\Http\Requests\RequestAspirante\RequestPension;

use App\Constants\ConstPension\RegimenPensional;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarPensionRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'regimen_pensional'     => ['sometimes','required','string',Rule::in(RegimenPensional::all())],
            'entidad_pensional'     => 'sometimes|required|string|min:5|max:50|regex:/^[\pL\pN\s\-]+$/u',
            'nit_entidad'           => 'sometimes|required|string|min:5|max:20|regex:/^[\pL\pN\s\-]+$/u',
            'archivo'               => 'sometimes|nullable|required|file|mimes:pdf|max:2048',
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

