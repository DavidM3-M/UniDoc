<?php

namespace App\Models\Ubicacion;

//creamos un modelo llamado Pais para mediar entre la tabla paises y la base de datos
//en este modelo se definen las relaciones con otras tablas
//en este caso la tabla paises tiene una relacion de uno a muchos con la tabla departamentos

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pais extends Model
{
    use hasFactory;
    //definimos el nombre de la tabla
    protected $table = 'paises';
    //definimos la clave primaria de la tabla
    protected $primaryKey = 'id_pais';
    //definimos los campos de la tabla paises que se pueden llenar
    protected $fillable = [
        'nombre'
    ];

    //relacion de uno a muchos con la tabla departamentos
    public function departamentosPais(): HasMany
    {
        return $this->hasMany(Departamento::class,'pais_id','id_pais');
    }
}
