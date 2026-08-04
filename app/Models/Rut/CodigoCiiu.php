<?php

namespace App\Models\Rut;

use Illuminate\Database\Eloquent\Model;

class CodigoCiiu extends Model
{
    protected $table = 'codigos_ciiu';

    protected $fillable = [
        'codigo',
        'descripcion',
        'grupo',
        'division',
        'seccion',
        'seccion_titulo',
    ];
}
