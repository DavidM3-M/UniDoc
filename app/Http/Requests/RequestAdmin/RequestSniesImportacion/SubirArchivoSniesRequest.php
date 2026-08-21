<?php

namespace App\Http\Requests\RequestAdmin\RequestSniesImportacion;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SubirArchivoSniesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Administrador` del grupo de rutas.
    }

    public function rules(): array
    {
        return [
            // 51200 KB = 50MB. El archivo de "Oferta y Programas" del SNIES puede pesar varios MB.
            'archivo' => ['required', 'file', 'mimes:xlsx', 'max:51200'],
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
