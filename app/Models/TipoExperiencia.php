<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de tipos de experiencia profesional.
 *
 * Ojo con la relación con `experiencias`: no hay FK. `experiencias.tipo_experiencia`
 * guarda el **nombre** como string, igual que `convocatorias.tipo_experiencia_requerida`.
 * Esta tabla es la fuente de verdad contra la que se validan esos strings, y por eso
 * renombrar un tipo obliga a propagar el cambio a ambas tablas
 * (ver `TipoExperienciaController::actualizar`).
 */
class TipoExperiencia extends Model
{
    protected $table = 'tipo_experiencias';

    protected $primaryKey = 'id_tipo_experiencia';

    protected $fillable = [
        'nombre_tipo_experiencia',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Filtra solo los tipos vigentes, los que deben aparecer en los desplegables.
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
