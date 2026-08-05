<?php

namespace App\Models\Aspirante;

use App\Models\Aspirante\Documento;
use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;


class FotoPerfil extends Model
 // Establece una relación de muchos a muchos entre `Aptitud` y `User`.
        // Usa la tabla intermedia `user_id` y la clave primaria `id` del modelo `User`.
{
    protected $table = 'foto_perfils';
     // Establece una relación de muchos a muchos entre `Aptitud` y `User`.
        // Usa la tabla intermedia `user_id` y la clave primaria `id` del modelo `User`.
    protected $primaryKey = 'id_foto_perfil';
    // Define la clave primaria de la tabla como `id_foto_perfil`.
    protected $fillable = [
        'user_id',
    ];
    // Define los campos que se pueden asignar masivamente (mass assignment) en este modelo.

    public function usuarioFotoPerfil()
    // Define una relación entre el modelo `FotoPerfil` y el modelo `User`.
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
        // Establece una relación de uno a muchos inversa entre `FotoPerfil` y `User`.
        // Usa la clave foránea `user_id` en la tabla `foto_perfils` y la clave primaria `id` del modelo `User`.
    }

    public function documentosFotoPerfil():MorphMany
    // Define una relación polimórfica de uno a muchos entre `FotoPerfil` y `Documento`.
    {
        return $this->morphMany(Documento::class, 'documentable');
         // Establece una relación polimórfica donde `FotoPerfil` puede tener múltiples documentos asociados.
        // Usa el campo `documentable` para identificar la relación polimórfica.
    }

  
}
