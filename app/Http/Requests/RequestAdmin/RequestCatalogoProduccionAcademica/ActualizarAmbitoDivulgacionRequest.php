<?php

namespace App\Http\Requests\RequestAdmin\RequestCatalogoProduccionAcademica;

use App\Constants\ClavePrimaria;
use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ActualizarAmbitoDivulgacionRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
        // La autorización real la aplica el middleware `role:Administrador` del grupo de rutas.
    }

    /**
     * Reglas de validación que se aplican a la solicitud.
     */
    public function rules(): array
    {
        return [
            // `bail` + `max` antes del `exists`: `id_producto_academico` es un smallint y un
            // valor fuera de rango haría fallar la consulta en vez de no encontrar la fila.
            'producto_academico_id' => 'bail|sometimes|required|integer|min:1|max:' . ClavePrimaria::SMALLINT_MAXIMO
                . '|exists:producto_academicos,id_producto_academico',

            'nombre_ambito_divulgacion' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('ambito_divulgacions', 'nombre_ambito_divulgacion')
                    ->where('producto_academico_id', $this->productoAcademicoEfectivo())
                    ->ignore($this->route('id'), 'id_ambito_divulgacion'),
            ],

            'activo' => 'sometimes|boolean',

            'puntaje' => 'sometimes|integer|min:0|max:9999',
        ];
    }

    /**
     * Producto contra el que se mide la unicidad del nombre.
     *
     * Si la petición no trae `producto_academico_id` (solo se está renombrando), hay que
     * comparar contra el producto que el ámbito ya tiene; de lo contrario la regla `unique`
     * se evaluaría contra `null` y nunca encontraría el duplicado real.
     *
     * Un ID fuera del rango de `smallint` se descarta devolviendo `null`: la regla `unique`
     * corre su propia consulta con este valor y PostgreSQL abortaría con un 500. El error real
     * lo reporta la regla de `producto_academico_id`, como un 422.
     */
    private function productoAcademicoEfectivo(): ?int
    {
        if ($this->filled('producto_academico_id')) {
            $id = $this->input('producto_academico_id');

            return ClavePrimaria::fueraDeRango($id) ? null : (int) $id;
        }

        if (ClavePrimaria::fueraDeRango($this->route('id'))) {
            // El controlador responderá 404; aquí basta con no consultar y no romper.
            return null;
        }

        return AmbitoDivulgacion::find($this->route('id'))?->producto_academico_id;
    }

    public function messages(): array
    {
        return [
            'nombre_ambito_divulgacion.unique' => 'Ese tipo de producto académico ya tiene un ámbito de divulgación con ese nombre.',
        ];
    }

    /**
     * Manejo de errores de validación.
     */
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
