<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExperienciaRequerida extends Model
{
    protected $table = 'experiencia_requerida';
    protected $primaryKey = 'id_experiencia_requerida';
    public $timestamps = false;
    protected $fillable = [
        'descripcion_experiencia',
        'horas_minimas',
        'anos_equivalentes',
        'es_administrativo',
    ];
}
