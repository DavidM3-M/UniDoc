<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Examen de certificación de idioma con puntaje numérico (IELTS, TOEFL iBT, Cambridge FCE...).
 *
 * Cada examen pertenece a un idioma del catálogo y define sus propios rangos de puntaje con su
 * equivalencia en nivel MCER (ver `RangoExamenIdioma`). `vigencia_meses` nulo significa que el
 * certificado no vence (ej. Cambridge); con valor, vence esa cantidad de meses después de
 * emitido (ej. IELTS = 24).
 */
class ExamenIdioma extends Model
{
    protected $table = 'examenes_idioma';

    protected $primaryKey = 'id_examen_idioma';

    protected $fillable = [
        'idioma_catalogo_id',
        'nombre_examen',
        'vigencia_meses',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'vigencia_meses' => 'integer',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function idioma(): BelongsTo
    {
        return $this->belongsTo(Idioma::class, 'idioma_catalogo_id', 'id_idioma_catalogo');
    }

    public function rangos(): HasMany
    {
        return $this->hasMany(RangoExamenIdioma::class, 'examen_idioma_id', 'id_examen_idioma')
            ->orderBy('puntaje_min');
    }
}
