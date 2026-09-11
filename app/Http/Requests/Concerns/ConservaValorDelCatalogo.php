<?php

namespace App\Http\Requests\Concerns;

use App\Constants\ClavePrimaria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Evita que desactivar una entrada del catálogo deje atrapado un registro que ya la usaba.
 *
 * Las reglas de creación exigen que el valor elegido esté activo, y así debe ser: nadie debería
 * poder estrenar una opción retirada. Pero al **editar** esa misma exigencia bloqueaba al docente:
 * si el Administrador desactivaba "Docencia hora cátedra" después de que alguien la usara, ese
 * docente ya no podía volver a guardar su propio registro —ni para corregir una fecha ni para
 * reemplazar el documento que Apoyo Profesoral le había rechazado— y tampoco podía cambiar el
 * valor, porque el select ya no ofrecía la opción.
 *
 * La regla que arma este trait acepta el valor si está activo **o** si es exactamente el que el
 * registro ya tenía guardado. Estrenar una opción inactiva sigue estando prohibido.
 */
trait ConservaValorDelCatalogo
{
    /**
     * Valor que el registro ya tiene guardado en `$campo`, o null si no se puede determinar.
     *
     * Se busca acotado al usuario autenticado, igual que hace el controlador al actualizar: un id
     * ajeno no debe servir para averiguar datos de otro, ni para relajar la validación.
     *
     * @param  class-string<Model>  $modelo
     */
    protected function valorGuardado(string $modelo, string $columnaId, string $campo)
    {
        $id = $this->route('id');
        $usuario = $this->user();

        if ($id === null || $usuario === null || ClavePrimaria::fueraDeRango($id)) {
            return null;
        }

        return $modelo::query()
            ->where($columnaId, $id)
            ->where('user_id', $usuario->id)
            ->value($campo);
    }

    /**
     * `exists` contra el catálogo que además tolera el valor ya guardado.
     *
     * El SQL resultante es `where <columna> = <enviado> and (activo = true or <columna> = <guardado>)`,
     * así que un valor inactivo distinto del guardado se sigue rechazando.
     */
    protected function reglaCatalogoVigente(string $tabla, string $columna, $valorGuardado)
    {
        return Rule::exists($tabla, $columna)->where(function ($query) use ($columna, $valorGuardado) {
            $query->where('activo', true);

            if ($valorGuardado !== null && $valorGuardado !== '') {
                $query->orWhere($columna, $valorGuardado);
            }
        });
    }
}
