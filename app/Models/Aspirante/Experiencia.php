<?php

namespace App\Models\Aspirante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Aspirante\Documento;
use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Experiencia extends Model
{
    // Definimos el nombre de la tabla
    protected $table = 'experiencias';
    // Definimos la clave primaria de la tabla
    protected $primaryKey = 'id_experiencia';

    protected $fillable = [
        'user_id',
        'tipo_experiencia',
        'institucion_experiencia',
        'es_uniautonoma',
        'cargo',
        'trabajo_actual',
        'intensidad_horaria',
        'meses_trabajados',
        'fecha_inicio',
        'fecha_finalizacion',
        'fecha_expedicion_certificado',
    ];

    protected $casts = [
        'es_uniautonoma' => 'boolean',
        'intensidad_horaria' => 'integer',
        // Meses declarados por el docente y respaldados por el certificado. No confundir con el
        // cálculo por fechas de `MotorEscalafonDocenteService::calcularMesesUniautonoma()`, que
        // sigue siendo el que alimenta el escalafón.
        'meses_trabajados' => 'integer',
    ];

    // Relación polimórfica con documentos
    public function documentosExperiencia():MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }
    // Relación uno a uno con la tabla usuarios
    public function usuarioExperiencia(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    




   
}
