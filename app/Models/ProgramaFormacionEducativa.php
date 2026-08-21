<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Programa académico (Formación educativa, Fase 2 del catálogo de Formación académica).
 *
 * `codigo_snies_programa` presente = viene del importador (upsert por ese código).
 * `codigo_snies_programa` null = creado a mano desde el admin.
 */
class ProgramaFormacionEducativa extends Model
{
    protected $table = 'programas_formacion_educativa';

    protected $primaryKey = 'id_programa';

    protected $fillable = [
        'codigo_snies_programa',
        'institucion_id',
        'nivel_formacion_academica_id',
        'nombre_programa',
        'titulo_otorgado',
        'estado_programa',
        'modalidad',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(InstitucionSnies::class, 'institucion_id', 'id_institucion');
    }

    public function nivelFormacionAcademica(): BelongsTo
    {
        return $this->belongsTo(NivelFormacionAcademica::class, 'nivel_formacion_academica_id', 'id_nivel_formacion_academica');
    }
}
