<?php

namespace App\Models\Aspirante;
use Illuminate\Database\Eloquent\Model;
use App\Models\Aspirante\Documento;
use App\Models\NivelFormacionAcademica;
use App\Models\ProgramaFormacionEducativa;
use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Estudio extends Model
{
    // Definimos el nombre de la tabla
    protected $table = 'estudios';
    // Definimos la clave primaria de la tabla
    protected $primaryKey = 'id_estudio';

    protected $fillable = [
        'user_id',
        'tipo_estudio',
        'nivel_formacion_academica_id',
        'graduado',
        'institucion',
        'fecha_graduacion',
        'titulo_convalidado',
        'fecha_convalidacion',
        'resolucion_convalidacion',
        'posible_fecha_graduacion',
        'titulo_estudio',
        'programa_formacion_educativa_id',
        'fecha_inicio',
        'fecha_fin',
        'es_certificado'
    ];

    protected $casts = [
        'es_certificado' => 'boolean',
    ];

     // Relación polimórfica con documentos
     public function documentosEstudio():MorphMany
     {
         return $this->morphMany(Documento::class, 'documentable');
     }

    // relacion de uno a muchos con  usuarios
    public function usuarioEstudio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function nivelFormacionAcademica(): BelongsTo
    {
        return $this->belongsTo(NivelFormacionAcademica::class, 'nivel_formacion_academica_id', 'id_nivel_formacion_academica');
    }

    public function programaFormacionEducativa(): BelongsTo
    {
        return $this->belongsTo(ProgramaFormacionEducativa::class, 'programa_formacion_educativa_id', 'id_programa');
    }


    


}
