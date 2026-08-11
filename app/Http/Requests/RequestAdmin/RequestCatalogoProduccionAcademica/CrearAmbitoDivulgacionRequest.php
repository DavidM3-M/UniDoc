<?php

namespace App\Http\Requests\RequestAdmin\RequestCatalogoProduccionAcademica;

use App\Constants\ClavePrimaria;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CrearAmbitoDivulgacionRequest extends FormRequest
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
            // `bail` + `max` antes del `exists` a propósito: `id_producto_academico` es un
            // smallint, y consultar un valor fuera de ese rango hace que PostgreSQL lance un
            // error en vez de simplemente no encontrar la fila. Acotarlo primero convierte un
            // 500 en el 422 que corresponde.
            'producto_academico_id' => 'bail|required|integer|min:1|max:' . ClavePrimaria::SMALLINT_MAXIMO
                . '|exists:producto_academicos,id_producto_academico',

            // El nombre solo tiene que ser único dentro del mismo producto académico:
            // "Difusión internacional" es un ámbito válido para varios productos a la vez.
            'nombre_ambito_divulgacion' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ambito_divulgacions', 'nombre_ambito_divulgacion')
                    ->where('producto_academico_id', $this->productoAcademicoParaUnicidad()),
            ],

            'activo' => 'sometimes|boolean',
        ];
    }

    /**
     * Producto contra el que se mide la unicidad del nombre, acotado al rango de la columna.
     *
     * La regla `unique` corre su propia consulta con este valor en el `WHERE`. Si llega un ID
     * fuera del rango de `smallint`, PostgreSQL aborta la consulta y la petición termina en 500.
     * Devolver `null` en ese caso hace inofensivo el filtro (no encuentra nada) y deja que el
     * error real lo reporte la regla de `producto_academico_id`, como un 422.
     */
    private function productoAcademicoParaUnicidad(): ?int
    {
        $id = $this->input('producto_academico_id');

        return ClavePrimaria::fueraDeRango($id) ? null : (int) $id;
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
