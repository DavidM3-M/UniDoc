<?php

namespace App\Models\Aspirante;

// creamos un modelo llamado InformacionContacto para mediar entre la tabla informacion_contactos y la base de datos
// en este modelo se definen las relaciones con otras tablas
// en este caso la tabla informacion_contactos tiene una relacion de muchos a uno con la tabla municipios
// y una relacion de muchos a uno con la tabla usuarios

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Ubicacion\Municipio;
use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;


class InformacionContacto extends Model
{
    use HasFactory;
    // definimos el nombre de la tabla
    protected $table = 'informacion_contactos';
    // definimos la clave primaria de la tabla
    protected $primaryKey = 'id_informacion_contacto';
    // definimos los campos de la tabla informacion_contactos que se pueden llenar
    protected $fillable = [
        'user_id',
        'municipio_id',
        'categoria_libreta_militar',
        'numero_libreta_militar',
        'numero_distrito_militar',
        'direccion_residencia',
        'barrio',
        'telefono_movil',
        'celular_alternativo',
        'correo_alterno',
    ];

    // relacion de muchos a uno con la tabla municipios
    // en este caso la tabla informacion_contactos tiene una relacion de muchos a uno con la tabla municipios

    public function municipioInformacionContacto(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_id','id_municipio');
    }
    // relacion polimorfica con documentos
    public function documentosInformacionContacto():MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    //relacion uno a uno con la tabla usuarios
    public function usuarioInformacionContacto(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    



    



   
    
    







}
