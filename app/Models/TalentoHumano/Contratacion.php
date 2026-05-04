<?php

namespace App\Models\TalentoHumano;

use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Model;

class Contratacion extends Model
{
    protected $table = 'contratacions';
    // Especifica el nombre de la tabla asociada con este modelo en la base de datos.
    protected $primaryKey = 'id_contratacion';
    // Define la clave primaria de la tabla como `id_contratacion`.
    protected $fillable = [
        'user_id',
        'tipo_contrato',
        'area',
        'fecha_inicio',
        'fecha_fin',
        'valor_contrato',
        'observaciones'
    ];
    // Define los campos que se pueden asignar masivamente (mass assignment) en este modelo.

    public function usuarioContratacion()
    // Define una relación entre el modelo `Contratacion` y el modelo `User`.
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
        // Establece una relación de uno a muchos inversa entre `Contratacion` y `User`.
        // Usa la clave foránea `user_id` en la tabla `contratacions` y la clave primaria `id` del modelo `User`.
    }



}
