<?php

namespace App\Http\Requests\RequestTalentoHumano\RequestContratacion;

use App\Constants\ConstTalentoHumano\TipoContratacion;
use App\Constants\ConstTalentoHumano\AreasContratacion;
use App\Constants\ConstTalentoHumano\TipoProceso;
use App\Constants\ConstTalentoHumano\TipoVinculacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
class ActualizarContratacionRequest extends FormRequest
// Define la clase `ActualizarContratacionRequest`, que extiende `FormRequest` para personalizar la validación de solicitudes HTTP.

{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    // Método que determina si el usuario está autorizado para realizar esta solicitud.
    {
        return true;
    // Retorna `true`, lo que significa que cualquier usuario está autorizado para usar esta solicitud.
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    // Método que define las reglas de validación para los datos enviados en la solicitud.
    {
        return [
            // Motivo del cambio — requerido para trazabilidad legal
            'motivo'          => 'required|string|min:5|max:500',
            'tipo_proceso'    => ['sometimes', 'string', Rule::in(TipoProceso::all())],
            'tipo_vinculacion' => ['sometimes', 'string', Rule::in(TipoVinculacion::all())],
            'tipo_contrato'   => ['sometimes', 'required', 'string', Rule::in(TipoContratacion::all())],
            'area'            => ['sometimes', 'required', 'string', Rule::in(AreasContratacion::all())],
            'fecha_inicio'    => 'sometimes|required|date',
            'fecha_fin'       => 'sometimes|required|date',
            'valor_contrato'  => 'sometimes|required|numeric',
            'observaciones'   => 'sometimes|nullable|string|regex:/^[\pL\pN\s\-,.;:()]+$/u',
        ];
    }
    protected function failedValidation(Validator $validator)
    // Método que se ejecuta cuando la validación falla.
    {
        throw new HttpResponseException(
        // Lanza una excepción `HttpResponseException` para devolver una respuesta JSON personalizada.
           response()->json([
                'success' => false,
                // Indica que la solicitud no fue exitosa.
                'message' => 'Error en el formulario',
                // Mensaje general de error.
                'errors' => $validator->errors(),
                // Incluye los errores específicos de validación generados por el validador.
            ], 422)
                // Devuelve un código de estado HTTP 422 (Unprocessable Entity) para indicar errores de validación.
        );
    }
}
