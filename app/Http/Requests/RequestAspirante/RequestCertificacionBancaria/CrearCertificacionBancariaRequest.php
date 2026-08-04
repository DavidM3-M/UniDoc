<?php

namespace App\Http\Requests\RequestAspirante\RequestCertificacionBancaria;

use App\Constants\ConstCertificacionBancaria\TipoCuenta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CrearCertificacionBancariaRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Las razones sociales oficiales de la Superfinanciera incluyen puntos, comas, "&", "/", ":" y paréntesis
            // (ej. "BANCO SANTANDER COLOMBIA S.A. (En adelante El Banco)")
            'nombre_banco'        => 'required|string|min:3|max:255|regex:/^[\pL\pN\s\-.,:()&\/]+$/u',
            'tipo_cuenta'         => ['required','string',Rule::in(TipoCuenta::all())],
            'numero_cuenta'       => 'required|string|min:5|max:50|regex:/^[\pL\pN\s\-]+$/u',
            'fecha_emision'       => 'nullable|date',
            'archivo'             => 'required|file|mimes:pdf|max:2048',
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
