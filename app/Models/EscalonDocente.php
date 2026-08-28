<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Escalón del escalafón docente (Auxiliar, Asistente, Asociado, Titular...), con sus requisitos
 * de ascenso. Ver `MotorEscalafonDocenteService` para cómo se evalúan y `ReglaExcepcionEscalon`
 * para los casos que otorgan un escalón sin pasar por sus requisitos (ej. tener Doctorado).
 *
 * `idioma_catalogo_id` + `nivel_mcer_minimo` van siempre juntos: el nivel es ambiguo sin decir
 * de qué idioma (ver `Idioma`, catálogo administrable de idiomas).
 *
 * `meses_minimos_escalon_anterior` son meses **en el escalón inmediatamente inferior**, no meses
 * totales en la Universidad: para llegar a Asistente hay que haber sido Auxiliar 48 meses. Se
 * miden contra `historial_escalon_docente`, no contra la suma de experiencias.
 */
class EscalonDocente extends Model
{
    protected $table = 'escalones_docente';

    protected $primaryKey = 'id_escalon';

    protected $fillable = [
        'nombre',
        'orden',
        'formacion_minima',
        'idioma_catalogo_id',
        'nivel_mcer_minimo',
        'puntaje_minimo',
        'meses_minimos_escalon_anterior',
        'evaluacion_minima',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
        'puntaje_minimo' => 'integer',
        'meses_minimos_escalon_anterior' => 'integer',
        'evaluacion_minima' => 'float',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenados($query)
    {
        return $query->orderBy('orden');
    }

    public function excepciones(): HasMany
    {
        return $this->hasMany(ReglaExcepcionEscalon::class, 'escalon_otorgado_id', 'id_escalon');
    }

    public function idioma(): BelongsTo
    {
        return $this->belongsTo(Idioma::class, 'idioma_catalogo_id', 'id_idioma_catalogo');
    }
}
