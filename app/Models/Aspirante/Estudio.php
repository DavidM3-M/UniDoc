<?php

namespace App\Models\Aspirante;
use Illuminate\Database\Eloquent\Model;
use App\Models\Aspirante\Documento;
use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Estudio extends Model
{
    // Definimos el nombre de la tabla
    protected $table = 'estudios';
    // Definimos la clave primaria de la tabla
    protected $primaryKey = 'id_estudio';

    protected $fillable = [
        'user_id',
        'tipo_estudio',
        'graduado',
        'institucion',
        'fecha_graduacion',
        'titulo_convalidado',
        'fecha_convalidacion',
        'resolucion_convalidacion',
        'posible_fecha_graduacion',
        'titulo_estudio',
        'fecha_inicio',
        'fecha_fin'
    ];

     // Relación polimórfica con documentos
     public function documentosEstudio():MorphMany
     {
         return $this->morphMany(Documento::class, 'documentable');
     }

    // relacion de uno a muchos con  usuarios
    public function usuarioEstudio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }


    


}
