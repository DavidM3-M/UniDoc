<?php

namespace App\Http\Requests\RequestAspirante\RequestArl;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class CrearArlRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre_arl'            => 'required|string|min:3|max:100|regex:/^[\pL\pN\s\-]+$/u',
            'fecha_afiliacion'      => 'required|date',
            'fecha_retiro'          => 'nullable|date|after:fecha_afiliacion',
            'estado_afiliacion'     => 'required|string|in:Activo,Inactivo',
            'clase_riesgo'          => 'required|integer|min:1|max:5',
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
