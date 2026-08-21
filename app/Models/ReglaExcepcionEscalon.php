<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regla de excepción del escalafón docente: si se cumple `tipo_condicion` + `valor_condicion`,
 * el docente queda como mínimo en `escalon_otorgado_id`. Ver `EscalafonDocenteService`.
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
