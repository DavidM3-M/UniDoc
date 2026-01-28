<?php

namespace App\Models\Coordinador;

use Illuminate\Database\Eloquent\Model;

class CoordinadorPlantilla extends Model
{
    protected $table = 'coordinador_plantillas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'estructura',
        'creado_por',
    ];

    protected $casts = [
        'estructura' => 'array',
    ];
}
