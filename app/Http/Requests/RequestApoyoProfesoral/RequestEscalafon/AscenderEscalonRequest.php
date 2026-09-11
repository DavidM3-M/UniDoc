<?php

namespace App\Http\Requests\RequestApoyoProfesoral\RequestEscalafon;

use App\Constants\ClavePrimaria;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Ejecuta el ascenso de un docente contra un periodo de ascenso concreto.
 *
 * El periodo se pide explícitamente y no se deduce: es el que fija la fecha de corte con la que se
 * evaluó el expediente, y queda escrito en `historial_escalon_docente.periodo_ascenso_id`. Que
 * conste en el acto es lo que permite auditar después con qué reglas y con qué corte se otorgó.
 *
 * Que el periodo esté cerrado lo comprueba `AscensoEscalafonService::ascender()`, junto con la
 * elegibilidad y dentro de la misma transacción.
 */
class AscenderEscalonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Apoyo Profesoral` del grupo de rutas.
    }

    public function rules(): array
    {
        return [
            'periodo_ascenso_id' => 'bail|required|integer|min:1|max:' . ClavePrimaria::SMALLINT_MAXIMO
                . '|exists:periodos_ascenso,id_periodo_ascenso',
            'motivo' => 'nullable|string|max:1000',
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
