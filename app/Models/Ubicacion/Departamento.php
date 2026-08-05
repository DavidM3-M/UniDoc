<?php

namespace App\Models\Ubicacion;

// creamos un modelo llamado Departamento para mediar entre la tabla departamentos y la base de datos
// en este modelo se definen las relaciones con otras tablas
// en este caso la tabla departamentos tiene una relacion de uno a muchos con la tabla municipios
// y una relacion de muchos a uno con la tabla paises
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Ubicacion\Pais;
use App\Models\Ubicacion\Municipio;

class Departamento extends Model
{
    // usamos el trait HasFactory para definir un metodo factory en el modelo
     use HasFactory;
     // definimos el nombre de la tabla
     protected $table = 'departamentos';
     // definimos la clave primaria de la tabla
     protected $primaryKey = 'id_departamento';
 
    // definimos los campos de la tabla departamentos que se pueden llenar
     protected $fillable = [
        'nombre',
        'pais_id',
    ];

    // relacion de muchos a uno con la tabla paises
     public function paisDepartamento(): BelongsTo
     {
            return $this->belongsTo(Pais::class,'pais_id','id_pais');
     }

     
     // relacion de uno a muchos con la tabla municipios
     public function municipiosDepartamento(): HasMany
     {
         return $this->hasMany(Municipio::class,'departamento_id','id_departamento');
     }

}
