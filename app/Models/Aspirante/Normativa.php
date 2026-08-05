<?php

namespace App\Models\Aspirante;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Normativa extends Model
{
    protected $table = 'normativas';
    // Especifica el nombre de la tabla asociada con este modelo en la base de datos.
    protected $primaryKey = 'id_normativa';
    // Define la clave primaria de la tabla como `id_normativa`.
    public $timestamps = false;
    // Indica que la tabla no tiene columnas `created_at` y `updated_at`.
    protected $fillable = [
        'id_normativa',
        'nombre',
        'descripcion',
        'tipo',
    ];
    // Define los campos que se pueden asignar masivamente (mass assignment) en este modelo.
    public function documentosNormativa():MorphMany
    // Define una relación polimórfica de uno a muchos entre `Normativa` y `Documento`.
    {
        return $this->morphMany(Documento::class, 'documentable');
         // Establece una relación polimórfica donde `Normativa` puede tener múltiples documentos asociados.
        // Usa el campo `documentable` para identificar la relación polimórfica.
    }
    

}
