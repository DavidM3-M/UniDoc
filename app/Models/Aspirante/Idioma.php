<?php

namespace App\Models\Aspirante;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Aspirante\Documento;
use App\Models\ExamenIdioma;
use App\Models\Idioma as IdiomaCatalogo;
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
        'idioma_catalogo_id',
        'institucion_idioma',
        'examen_idioma_id',
        'puntaje_obtenido',
        'fecha_certificado',
        'nivel'
    ];

    protected $casts = [
        'puntaje_obtenido' => 'float',
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

    // Catálogo administrable de idiomas (App\Models\Idioma, no confundir con este modelo).
    public function idiomaCatalogo(): BelongsTo
    {
        return $this->belongsTo(IdiomaCatalogo::class, 'idioma_catalogo_id', 'id_idioma_catalogo');
    }

    public function examenIdioma(): BelongsTo
    {
        return $this->belongsTo(ExamenIdioma::class, 'examen_idioma_id', 'id_examen_idioma');
    }



}
