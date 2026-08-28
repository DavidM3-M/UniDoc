<?php

namespace App\Http\Requests\RequestApoyoProfesoral\RequestEscalafon;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Deshace un acto del escalafón.
 *
 * El motivo es obligatorio, no opcional como en el ascenso: revertir le quita una categoría a
 * alguien que ya la tenía y el docente recibe una notificación con esta explicación. Mismo criterio
 * que el `motivo_rechazo` obligatorio de `VerificacionDocumentosController`.
 */
class RevertirEscalonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Apoyo Profesoral` del grupo de rutas.
    }

    public function rules(): array
    {
        return [
            'motivo' => 'required|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo es obligatorio al revertir un cambio de escalafón.',
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
