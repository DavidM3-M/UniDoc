<?php

namespace App\Http\Requests\RequestApoyoProfesoral\RequestEscalafon;

use App\Constants\ClavePrimaria;
use App\Models\PeriodoAscenso;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Corrige un periodo de ascenso que todavía no ha cerrado.
 *
 * Un periodo ya cerrado no se toca: su `fecha_cierre` es el corte con el que se evaluaron —y
 * posiblemente se otorgaron— ascensos reales. Moverla después cambiaría retroactivamente el
 * expediente con el que se tomaron esas decisiones. Esa validación vive en el controlador, que es
 * quien tiene el modelo a la mano.
 */
class ActualizarPeriodoAscensoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Apoyo Profesoral` del grupo de rutas.
    }

    public function rules(): array
    {
        return [
            'nombre' => 'sometimes|required|string|max:100',
            'fecha_cierre' => ['sometimes', 'required', 'date', 'after:today', $this->posteriorAlAnterior()],
        ];
    }

    /** Igual que al crear, pero ignorando el propio periodo que se está editando. */
    private function posteriorAlAnterior(): callable
    {
        return function (string $atributo, $valor, callable $fallar) {
            $enEdicion = $this->route('id');

            // El `!=` se omite cuando el ID de la ruta no puede corresponder a ningún periodo. La
            // regla se evalúa antes que el 404 del controlador, y meter un ID fuera del rango de la
            // clave `smallint` en la consulta la aborta con SQLSTATE 22003: la API devolvía 500
            // donde correspondía 404. Ver `ClavePrimaria::fueraDeRango()`. Omitirlo no cambia el
            // resultado: no hay ninguna fila con ese ID que excluir.
            $anterior = PeriodoAscenso::when(
                !ClavePrimaria::fueraDeRango($enEdicion),
                fn ($consulta) => $consulta->where('id_periodo_ascenso', '!=', $enEdicion)
            )
                ->orderByDesc('fecha_cierre')
                ->first();

            if ($anterior && $anterior->fecha_cierre->greaterThanOrEqualTo($valor)) {
                $fallar("La fecha de cierre debe ser posterior a la del periodo anterior ({$anterior->nombre}, {$anterior->fecha_cierre->toDateString()}).");
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
