<?php

namespace App\Http\Requests\RequestApoyoProfesoral\RequestEscalafon;

use App\Models\PeriodoAscenso;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Fija una nueva fecha de cierre de ascensos.
 *
 * No se pide fecha de apertura: un periodo corre implícitamente desde el cierre del anterior y los
 * docentes suben sus documentos cuando quieran (ver la migración de `periodos_ascenso`).
 */
class CrearPeriodoAscensoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Apoyo Profesoral` del grupo de rutas.
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:100',

            // `after:today` porque el cierre es el corte contra el que se congelan los requisitos:
            // crear uno con fecha pasada permitiría fabricar un corte a la medida de un expediente
            // que ya se conoce.
            'fecha_cierre' => ['required', 'date', 'after:today', $this->posteriorAlUltimo()],
        ];
    }

    /**
     * La fecha de cierre tiene que ser posterior a la del último periodo.
     *
     * Intercalar un corte hacia atrás partiría en dos la ventana de producción de todos los
     * docentes que ya ascendieron con el periodo anterior.
     */
    private function posteriorAlUltimo(): callable
    {
        return function (string $atributo, $valor, callable $fallar) {
            $ultimo = PeriodoAscenso::ultimo();

            if ($ultimo && $ultimo->fecha_cierre->greaterThanOrEqualTo($valor)) {
                $fallar("La fecha de cierre debe ser posterior a la del último periodo ({$ultimo->nombre}, {$ultimo->fecha_cierre->toDateString()}).");
            }
        };
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
