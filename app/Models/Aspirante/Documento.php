<?php

namespace App\Models\Aspirante;

use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;


class Documento extends Model
{
    // definimos el nombre de la tabla
    protected $table = 'documentos';
    // definimos la clave primaria de la tabla
    protected $primaryKey = 'id_documento';

    protected $fillable = [
        'archivo',
        'estado',
        'documentable_id',
        'documentable_type',
        'motivo_rechazo',
    ];

    // Definimos las relaciones
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
