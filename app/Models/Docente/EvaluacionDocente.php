<?php

namespace App\Models\Docente;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario\User;

class EvaluacionDocente extends Model
{
   protected $table = 'evaluacion_docentes';
    // Especifica el nombre de la tabla asociada con este modelo en la base de datos.
   protected $primaryKey = 'id_evaluacion_docente';
   // Define la clave primaria de la tabla como `id_evaluacion_docente`.
   protected $fillable = [
       'user_id',
       'promedio_evaluacion_docente',
       'estado_evaluacion_docente',
       'asignado_por',
       'fecha_asignacion',
   ];
    // Define los campos que se pueden asignar masivamente (mass assignment) en este modelo.

   protected $casts = [
       'fecha_asignacion' => 'datetime',
   ];
   // Convierte `fecha_asignacion` a instancia de Carbon al leerla.

   public function usuarioEvaluacionDocente()
    // Define una relación entre el modelo `EvaluacionDocente` y el modelo `User`.
   {
       return $this->belongsTo(User::class, 'user_id', 'id');
       // Establece una relación de uno a muchos inversa entre `EvaluacionDocente` y `User`.
       // Usa la clave foránea `user_id` en la tabla `evaluacion_docentes` y la clave primaria `id` del modelo `User`.
   }

   public function asignadaPor()
    // Relación con el usuario de Apoyo Profesoral que asignó la evaluación (auditoría).
   {
       return $this->belongsTo(User::class, 'asignado_por', 'id');
   }

}
