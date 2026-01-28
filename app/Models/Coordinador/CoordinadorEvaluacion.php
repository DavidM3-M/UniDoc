<?php

namespace App\Models\Coordinador;

use Illuminate\Database\Eloquent\Model;

class CoordinadorEvaluacion extends Model
{
    // Relación con el usuario aspirante
    public function aspirante()
    {
        return $this->belongsTo(\App\Models\Usuario\User::class, 'aspirante_user_id', 'id');
    }

    // Relación con el usuario coordinador
    public function coordinador()
    {
        return $this->belongsTo(\App\Models\Usuario\User::class, 'coordinador_user_id', 'id');
    }

    protected $table = 'coordinador_evaluaciones';

    protected $fillable = [
        'aspirante_user_id',
        'coordinador_user_id',
        'plantilla_id',
        'prueba_psicotecnica',
        'validacion_archivos',
        'clase_organizada',
        'aprobado',
        'formulario',
        'observaciones',
    ];

    protected $casts = [
        'validacion_archivos' => 'boolean',
        'clase_organizada' => 'boolean',
        'aprobado' => 'boolean',
        'formulario' => 'array',
    ];
}
