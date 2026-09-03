<?php

namespace App\Models\Aspirante;
use App\Constants\ConstAgregarExperiencia\TrabajoActual;
use Carbon\Carbon;
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
        // cálculo por fechas de `MotorEscalafonDocenteService::mesesEnEscalon()`, que sigue siendo
        // el que alimenta el escalafón: este campo todavía no lo consume nadie.
        'meses_trabajados' => 'integer',
    ];

    /**
     * ¿El docente sigue en este cargo?
     *
     * `trabajo_actual` guarda la cadena 'Si'/'No' de `TrabajoActual`, no un booleano: escrito
     * como `if ($exp->trabajo_actual)` el 'No' también entra, porque en PHP toda cadena no vacía
     * es verdadera. Por eso la comparación vive aquí y nadie la vuelve a escribir a mano.
     */
    public function esTrabajoActual(): bool
    {
        return $this->trabajo_actual === TrabajoActual::SI;
    }

    /**
     * Fecha en la que esta experiencia deja de contar, acotada al corte.
     *
     * Un trabajo actual no tiene fin: su cierre es el corte —hoy, o la
     * `periodo_ascenso.fecha_cierre` contra la que se esté evaluando—, así que la antigüedad
     * avanza sola con el calendario sin que el docente tenga que volver a editar el registro.
     * Ahí está la diferencia con leer `fecha_finalizacion` directamente: si quedó guardada una
     * fecha en un registro marcado como actual, esa fecha no congela el conteo.
     *
     * Sin marca de trabajo actual y sin fecha de finalización se sigue asumiendo abierta, que es
     * como se han venido contando los registros que nunca la informaron.
     */
    public function fechaFinEfectiva(?Carbon $corte = null): Carbon
    {
        $corte = $corte ? $corte->copy() : Carbon::now();

        if ($this->esTrabajoActual() || empty($this->fecha_finalizacion)) {
            return $corte;
        }

        $fin = Carbon::parse($this->fecha_finalizacion)->endOfDay();

        return $fin->greaterThan($corte) ? $corte : $fin;
    }

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
