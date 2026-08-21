<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de idiomas administrable por el rol Administrador (ej. Inglés, Francés).
 *
 * No confundir con `App\Models\Aspirante\Idioma`: ese modelo es el registro de un idioma
 * certificado por un aspirante/docente (tabla `idiomas`), conectado a este catálogo vía
 * `idiomas.idioma_catalogo_id`.
 */
class Idioma extends Model
{
    protected $table = 'catalogo_idiomas';

    protected $primaryKey = 'id_idioma_catalogo';

    protected $fillable = [
        'nombre_idioma',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function examenes(): HasMany
    {
        return $this->hasMany(ExamenIdioma::class, 'idioma_catalogo_id', 'id_idioma_catalogo');
    }
}
