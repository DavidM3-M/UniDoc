<?php

namespace App\Models\TalentoHumano;

use Illuminate\Database\Eloquent\Model;

class Convocatoria extends Model
{
    protected $table = 'convocatorias';
    protected $primaryKey = 'id_convocatoria';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        // Campos originales
        'nombre_convocatoria',
        'tipo',
        'fecha_publicacion',
        'fecha_cierre',
        'descripcion',
        'estado_convocatoria',

        // Nuevos campos
        'numero_convocatoria',
        'periodo_academico',
        'cargo_solicitado',
        'facultad',
        'cursos',
        'tipo_vinculacion',
        'personas_requeridas',
        'fecha_inicio_contrato',
        'perfil_profesional',
        'experiencia_requerida',
        'solicitante',
        'aprobaciones',
    ];

    protected $casts = [
        'fecha_publicacion' => 'date',
        'fecha_cierre' => 'date',
        'fecha_inicio_contrato' => 'date',
        'personas_requeridas' => 'integer',
    ];

    /**
     * Relación con los documentos de la convocatoria
     */
    public function documentosConvocatoria()
    {
        return $this->morphMany(
            'App\Models\Archivos\DocumentoConvocatoria',
            'documentable',
            'documentable_type',
            'documentable_id',
            'id_convocatoria'
        );
    }

    /**
     * Relación con las postulaciones de la convocatoria
     */
    public function postulacionesConvocatoria()
    {
        return $this->hasMany(
            \App\Models\TalentoHumano\Postulacion::class,
            'id_convocatoria',
            'id_convocatoria'
        );
    }
}
