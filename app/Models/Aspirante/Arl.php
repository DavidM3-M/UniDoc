<?php

namespace App\Models\Aspirante;

use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Arl extends Model
{
    use HasFactory;

    protected $table = 'arl';
    protected $primaryKey = 'id_arl';

    protected $fillable = [
        'user_id',
        'nombre_arl',
        'fecha_afiliacion',
        'fecha_retiro',
        'estado_afiliacion',
        'clase_riesgo',
    ];

    public function documentosArl()
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    public function usuarioArl()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
