<?php

namespace App\Models\Rut;

use App\Models\Aspirante\Rut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ResponsabilidadTributaria extends Model
{
    protected $table = 'responsabilidades_tributarias';

    protected $fillable = [
        'codigo',
        'descripcion',
    ];

    public function ruts(): BelongsToMany
    {
        return $this->belongsToMany(
            Rut::class,
            'rut_responsabilidad_tributaria',
            'responsabilidad_tributaria_id',
            'rut_id',
            'id',
            'id_rut'
        );
    }
}
