<?php

namespace App\Models\Aspirante;

use Illuminate\Database\Eloquent\Model;
use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Aspirante\Documento;
use App\Models\Usuario\User;

class ProduccionAcademica extends Model
{
    protected $table = 'produccion_academicas';

    protected $primaryKey = 'id_produccion_academica';

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'ambito_divulgacion_id',
        'titulo',
        'numero_autores',
        'medio_divulgacion',
        'fecha_divulgacion',
    ];


     // Relación polimórfica con documentos
     public function documentosProduccionAcademica():MorphMany
     {
         return $this->morphMany(Documento::class, 'documentable');
     }

    // Relación con el modelo AmbitoDivulgacion
    public function ambitoDivulgacionProduccionAcademica():BelongsTo
    {
        return $this->belongsTo(AmbitoDivulgacion::class, 'medio_divulgacion', 'id_ambito_divulgacion');
    }

    // Relación uno a uno con la tabla usuarios
    public function usuarioProduccionAcademica(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

}
