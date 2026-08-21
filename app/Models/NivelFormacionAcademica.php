<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de niveles de formación académica.
 *
 * `nivel_academico` y `nivel_formacion` son texto libre a propósito: no hay lista fija ni
 * validación cruzada entre ambos. Lo referencian `estudios.nivel_formacion_academica_id` y
 * `programas_formacion_educativa.nivel_formacion_academica_id`.
 *
 * `orden` es la jerarquía que usa el escalafón para exigir "al menos este nivel": mayor número,
 * nivel más alto (ver `MotorEscalafonDocenteService::tieneFormacionAprobada()`). Nulo significa
 * que el nivel no participa en el escalafón — formación complementaria como un diplomado, que
 * se registra en la hoja de vida pero no asciende a nadie.
 */
class NivelFormacionAcademica extends Model
{
    protected $table = 'niveles_formacion_academica';

    protected $primaryKey = 'id_nivel_formacion_academica';

    protected $fillable = [
        'nivel_academico',
        'nivel_formacion',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    // Filtra solo los niveles vigentes, los que deben aparecer en los desplegables.
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
