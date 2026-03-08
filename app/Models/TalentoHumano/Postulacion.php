<?php
//Inicio del archivo PHP
namespace App\Models\TalentoHumano;
// Define el namespace del modelo, lo que organiza el código y evita conflictos de nombres.
use App\Models\Usuario\User;
//Importa el modelo 'user' para establecer relaciones con este modelo
use App\Models\TalentoHumano\Convocatoria;
// Importa el modelo `Convocatoria` para establecer relaciones con este modelo.

use Illuminate\Database\Eloquent\Model;
// Importa la clase base `Model` de Eloquent, que permite interactuar con la base de datos.

use Illuminate\Database\Eloquent\Relations\BelongsTo;
// Importa la clase `BelongsTo` para definir relaciones de tipo "pertenece a".


class Postulacion extends Model
// Define la clase `Postulacion`, que extiende la funcionalidad del modelo base de Eloquent.

{
    protected $table = 'postulacions';
    protected $primaryKey = 'id_postulacion';

    protected $fillable = [
        'user_id',
        'convocatoria_id',
        'estado_postulacion',
        'motivo_rechazo',
        'rechazado_por',
    ];
    // Define los campos que se pueden asignar masivamente (mass assignment).

    public function usuarioPostulacion(): BelongsTo
    // Define una relación "pertenece a" con el modelo `User`.

    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    // Especifica que la relación se basa en la clave foránea `user_id` y la clave primaria `id` del modelo `User`.

    }

    public function convocatoriaPostulacion(): BelongsTo
    // Define una relación "pertenece a" con el modelo `Convocatoria`.

    {
        return $this->belongsTo(Convocatoria::class, 'convocatoria_id', 'id_convocatoria');
    // Especifica que la relación se basa en la clave foránea `convocatoria_id` y la clave primaria `id_convocatoria` del modelo `Convocatoria`.

    }
}
