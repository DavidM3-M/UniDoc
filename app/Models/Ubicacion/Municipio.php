<?php

namespace App\Models\Ubicacion;

// creamos un modelo llamado Municipio para mediar entre la tabla municipios y la base de datos
// en este modelo se definen las relaciones con otras tablas
// en este caso la tabla municipios tiene una relacion de muchos a uno con la tabla departamentos
// y una relacion de uno a muchos con la tabla informacion_contactos

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Ubicacion\Departamento;
use App\Models\Aspirante\InformacionContacto;
use App\Models\Usuario\User;

class Municipio extends Model
{
    use HasFactory;
    // definimos el nombre de la tabla
    protected $table = 'municipios';
    // definimos el nombre de la clave primaria
    protected $primaryKey = 'id_municipio';
    // definimos los campos de la tabla municipios que se pueden llenar
    protected $fillable = [
        'nombre',
        'departamento_id',
    ];



    // relacion de muchos a uno con la tabla departamentos
    public function departamentoMunicipio():BelongsTo
    {
        return $this->belongsTo(Departamento::class,'departamento_id','id_departamento');
    }


    // relacion de uno a muchos con la tabla informacion_contactos
    public function informacionContactosMunicipio():HasMany
    {
        return $this->hasMany(InformacionContacto::class,'municipio_id','id_municipio');
    }


    
    //  relacion de uno a muchos con la tabla Users
    public function usuariosMunicipio():HasMany
    {
        return $this->hasMany(User::class,'municipio_id', 'id_municipio');
    }


}
