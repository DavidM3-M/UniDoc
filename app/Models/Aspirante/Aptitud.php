<?php

namespace App\Models\Aspirante;

use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Model;

class Aptitud extends Model
// Define la clase `Aptitud`, que extiende `Model` para representar una tabla en la base de datos.
{
    protected $table = 'aptitudes';
    // Especifica el nombre de la tabla asociada con este modelo en la base de datos.
    protected $primaryKey = 'id_aptitud';
    // Define la clave primaria de la tabla como `id_aptitud`.
    protected $fillable = [
        'user_id',
        'nombre_aptitud',
        'descripcion_aptitud',
    ];
// Define los campos que se pueden asignar masivamente (mass assignment) en este modelo.
    public function usuarioAptitud()
    // Define una relación entre el modelo `Aptitud` y el modelo `User`.
    {
        return $this->belongsToMany(User::class, 'user_id','id');
         // Establece una relación de muchos a muchos entre `Aptitud` y `User`.
        // Usa la tabla intermedia `user_id` y la clave primaria `id` del modelo `User`.
    }

}
