<?php

namespace App\Models\Aspirante;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Aspirante\Documento;
use App\Models\Rut\ResponsabilidadTributaria;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;


class Rut extends Model
{
    // creamos un modelo llamado Rut para mediar entre la tabla ruts y la base de datos
    use HasFactory;
    // definimos el nombre de la tabla
    protected $table = 'ruts';
    // definimos la clave primaria de la tabla
    protected $primaryKey = 'id_rut';

    protected $fillable = [
        'user_id',
        'numero_rut',
        'razon_social',
        'tipo_persona',
        'codigo_ciiu',
    ];


    //relacion polimorfica con la tabla documentos
    public function documentosRut():MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    //relacion uno a uno con la tabla usuarios
    public function usuarioRut(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // relacion muchos a muchos con la tabla responsabilidades_tributarias
    public function responsabilidadesTributarias(): BelongsToMany
    {
        return $this->belongsToMany(
            ResponsabilidadTributaria::class,
            'rut_responsabilidad_tributaria',
            'rut_id',
            'responsabilidad_tributaria_id'
        );
    }

}
