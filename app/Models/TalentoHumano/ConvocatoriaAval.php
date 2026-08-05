<?php

namespace App\Models\TalentoHumano;

use Illuminate\Database\Eloquent\Model;

class ConvocatoriaAval extends Model
{
    protected $table = 'convocatoria_avales';
    protected $fillable = [
        'convocatoria_id',
        'user_id',
        'aval',
        'estado',
        'aprobador_id',
        'comentario',
        'fecha_aprobacion',
    ];

    public function convocatoria()
    {
        return $this->belongsTo(Convocatoria::class, 'convocatoria_id', 'id_convocatoria');
    }

    public function usuario()
    {
        return $this->belongsTo(\App\Models\Usuario\User::class, 'user_id', 'id');
    }

    public function aprobador()
    {
        return $this->belongsTo(\App\Models\Usuario\User::class, 'aprobador_id', 'id');
    }
}
