<?php

namespace App\Models\TalentoHumano;

use App\Models\Aspirante\Documento;
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
        'tipo_cargo_id',
        'tipo_cargo_otro',
        'facultad_id',
        'facultad_otro',
        'cursos',
        'tipo_vinculacion',
        'personas_requeridas',
        'fecha_inicio_contrato',
        'perfil_profesional_id',
        'perfil_profesional_otro',
        'experiencia_requerida_fecha',
        'experiencia_requerida_id',
        'solicitante',
        'avales_establecidos',

        // Nuevos campos para requerimientos detallados
        'requisitos_experiencia',
        'requisitos_idiomas',
        'requisitos_adicionales',
        
        // Campos de experiencia años y tipo
        'anos_experiencia_requerida',
        'tipo_experiencia_requerida',
        'cantidad_experiencia',
        'unidad_experiencia',
        'referencia_experiencia',
    ];

    protected $casts = [
        'fecha_publicacion' => 'date',
        'fecha_cierre' => 'date',
        'fecha_inicio_contrato' => 'date',
        'personas_requeridas' => 'integer',
        'requisitos_experiencia' => 'array',
        'requisitos_idiomas' => 'array',
        'requisitos_adicionales' => 'array',
        'perfil_profesional_otro' => 'string',
        'facultad_otro' => 'string',
        'tipo_cargo_otro' => 'string',
        'experiencia_requerida_fecha' => 'date',
        'avales_establecidos' => 'array',
        'anos_experiencia_requerida' => 'integer',
        'tipo_experiencia_requerida' => 'string',
        'cantidad_experiencia' => 'integer',
        'unidad_experiencia' => 'string',
        'referencia_experiencia' => 'string',
    ];

    /**
     * Relación con los documentos de la convocatoria
     */
    public function documentosConvocatoria()
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    /**
     * Relación con las postulaciones de la convocatoria
     */
    public function postulacionesConvocatoria()
    {
        return $this->hasMany(
            \App\Models\TalentoHumano\Postulacion::class,
            'convocatoria_id',
            'id_convocatoria'
        );
    }

    /**
     * Relación con tipo de cargo
     */
    public function tipoCargo()
    {
        return $this->belongsTo(\App\Models\TipoCargo::class, 'tipo_cargo_id', 'id_tipo_cargo');
    }

    /**
     * Relación con facultad
     */
    public function facultad()
    {
        return $this->belongsTo(\App\Models\Facultad::class, 'facultad_id', 'id_facultad');
    }

    /**
     * Relación con perfil profesional
     */
    public function perfilProfesional()
    {
        return $this->belongsTo(\App\Models\PerfilProfesional::class, 'perfil_profesional_id', 'id_perfil_profesional');
    }

    /**
     * Relación con experiencia requerida
     */
    public function experienciaRequerida()
    {
        return $this->belongsTo(\App\Models\ExperienciaRequerida::class, 'experiencia_requerida_id', 'id_experiencia_requerida');
    }
}
