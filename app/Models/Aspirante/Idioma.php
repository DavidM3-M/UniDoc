<?php

namespace App\Models\Aspirante;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Aspirante\Documento;
use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Idioma extends Model
{
    // definimos el nombre de la tabla
    protected $table = 'idiomas';
    // definimos la clave primaria de la tabla|
    protected $primaryKey = 'id_idioma';

    protected $fillable = [
        'user_id',
        'idioma',
        'institucion_idioma',
        'fecha_certificado',
        'nivel'
    ];
    
    // Relación polimórfica con documentos
    public function documentosIdioma():MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }
    // Relación uno a uno con la tabla usuarios
    public function usuarioIdioma(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }



}
