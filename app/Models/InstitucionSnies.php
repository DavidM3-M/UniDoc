<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Institución de educación superior. Viene del importador SNIES (con `codigo_institucion`) o se
 * crea a mano al agregar un programa manualmente (sin código).
 */
class InstitucionSnies extends Model
{
    protected $table = 'instituciones_snies';

    protected $primaryKey = 'id_institucion';

    protected $fillable = [
        'codigo_institucion',
        'codigo_institucion_padre',
        'nombre_institucion',
        'estado_institucion',
        'caracter_academico',
        'sector',
    ];

    public function programas(): HasMany
    {
        return $this->hasMany(ProgramaFormacionEducativa::class, 'institucion_id', 'id_institucion');
    }
}
