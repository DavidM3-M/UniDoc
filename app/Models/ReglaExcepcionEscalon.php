<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regla de excepción del escalafón docente: si se cumple `tipo_condicion` + `valor_condicion`, el
 * docente queda elegible para `escalon_otorgado_id` sin cumplir ninguno de sus requisitos, la
 * antigüedad incluida. Lo que la excepción no salta es el calendario: el ascenso sigue esperando a
 * un periodo de ascenso y al acto de Apoyo Profesoral. Ver `MotorEscalafonDocenteService`.
 */
class ReglaExcepcionEscalon extends Model
{
    protected $table = 'reglas_excepcion_escalon';

    protected $primaryKey = 'id_regla_excepcion';

    protected $fillable = [
        'tipo_condicion',
        'valor_condicion',
        'escalon_otorgado_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function escalonOtorgado(): BelongsTo
    {
        return $this->belongsTo(EscalonDocente::class, 'escalon_otorgado_id', 'id_escalon');
    }
}
