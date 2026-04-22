<?php

namespace App\Models\TalentoHumano;
use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Model;

class Ascenso extends Model{
    protected $table = 'ascensos';

    protected $primaryKey = 'id_ascenso';

    protected $fillable = [
        'user_id',
        'id_convocatoria',
        'nuevo_cargo',
        'area',
        'fecha_ascenso',
        'observaciones'
    ];

    public function convocatoria()
    {
        return $this -> belongsTo(Convocatoria::class ,'id_convocatoria' ,'id_convocatoria' );  
    }

    public function usuarioAscenso()
    {
        return $this -> belongsTo(User::class, 'user_id', 'id');
    }
}