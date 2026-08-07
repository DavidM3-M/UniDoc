<?php

namespace App\Models\Docente;

use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Umbral mínimo de evaluación docente exigido para ascender de categoría.
 *
 * Los registros no se editan ni se borran: cada cambio cierra el anterior con
 * `vigencia_hasta` y crea uno nuevo. El vigente es el que tiene `vigencia_hasta` en NULL.
 */
class UmbralEvaluacionDocente extends Model
{
    protected $table = 'umbrales_evaluacion_docente';

    protected $primaryKey = 'id_umbral_evaluacion';

    protected $fillable = [
        'valor_minimo',
        'vigencia_desde',
        'vigencia_hasta',
        'creado_por',
        'observaciones',
    ];

    protected $casts = [
        'valor_minimo'   => 'float',
        'vigencia_desde' => 'date',
        'vigencia_hasta' => 'date',
    ];

    /** Administrador que registró este umbral. */
    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por', 'id');
    }

    /** Restringe la consulta al umbral vigente (el que no tiene cierre). */
    public function scopeVigente($query)
    {
        return $query->whereNull('vigencia_hasta');
    }
}
