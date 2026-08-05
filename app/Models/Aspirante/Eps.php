<?php

namespace App\Models\Aspirante;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Aspirante\Documento;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Eps extends Model
{
    use HasFactory;
    // definimos el nombre de la tabla
    protected $table = 'eps';
    // definimos la clave primaria de la tabla
    protected $primaryKey = 'id_eps';

    protected $fillable = [
        'user_id',
        'nombre_eps',
        'tipo_afiliacion',
        'estado_afiliacion',
        'fecha_afiliacion_efectiva',
        'fecha_finalizacion_afiliacion',
        'tipo_afiliado',
        'numero_afiliado'
    ];
    

    // relacion polimorfia con documentos
    public function documentosEps():MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    //relacion uno a uno con la tabla usuarios
    public function usuarioEps(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
 



}
