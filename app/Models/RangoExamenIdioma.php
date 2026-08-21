<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rango de puntaje de un examen y su nivel MCER equivalente (ej. IELTS 5.5–6.5 = B2).
 *
 * Es la tabla de equivalencia que el Administrador define y mantiene para cada examen.
 */
class RangoExamenIdioma extends Model
{
    protected $table = 'examenes_idioma_rangos';

    protected $primaryKey = 'id_rango_examen_idioma';

    protected $fillable = [
        'examen_idioma_id',
        'puntaje_min',
        'puntaje_max',
        'nivel_mcer',
    ];

    protected $casts = [
        'puntaje_min' => 'float',
        'puntaje_max' => 'float',
    ];

    public function examen(): BelongsTo
    {
        return $this->belongsTo(ExamenIdioma::class, 'examen_idioma_id', 'id_examen_idioma');
    }
}
