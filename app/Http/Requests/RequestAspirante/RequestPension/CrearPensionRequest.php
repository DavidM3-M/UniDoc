<?php

namespace  App\Http\Requests\RequestAspirante\RequestPension;

use App\Constants\ConstPension\RegimenPensional;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CrearPensionRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'regimen_pensional'     => ['required','string',Rule::in(RegimenPensional::all())],
            'entidad_pensional'     => 'required|string|min:5|max:50|regex:/^[\pL\pN\s\-]+$/u',
            'nit_entidad'           => 'required|string|min:5|max:20|regex:/^[\pL\pN\s\-]+$/u',
            'archivo'               => 'required|file|mimes:pdf|max:2048',
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
