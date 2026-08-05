<?php

namespace App\Models\TiposProductoAcademico;
// Especifica que la relación se basa en la clave foránea `convocatoria_id` y la clave primaria `id_convocatoria` del modelo `Convocatoria`.
use App\Models\Aspirante\ProduccionAcademica;
// Especifica que la relación se basa en la clave foránea `convocatoria_id` y la clave primaria `id_convocatoria` del modelo `Convocatoria`.

use Illuminate\Database\Eloquent\Factories\HasFactory;
// Importa el trait `HasFactory` para habilitar la generación de fábricas para este modelo.

use Illuminate\Database\Eloquent\Model;
// Importa la clase base `Model` de Eloquent, que permite interactuar con la base de datos.

use App\Models\TiposProductoAcademico\ProductoAcademico;
// Importa el modelo `ProductoAcademico` para establecer relaciones con este modelo.

use Illuminate\Database\Eloquent\Relations\BelongsTo;
// Importa la clase `BelongsTo` para definir relaciones de tipo "pertenece a".

use Illuminate\Database\Eloquent\Relations\HasMany;
// Importa la clase `HasMany` para definir relaciones de tipo "tiene muchos".

class AmbitoDivulgacion extends Model
// Define la clase `AmbitoDivulgacion`, que extiende la funcionalidad del modelo base de Eloquent.

{
    use HasFactory;
    // Usa el trait `HasFactory` para habilitar la generación de fábricas para este modelo.

    protected $table = 'ambito_divulgacions';
    // Especifica el nombre de la tabla asociada en la base de datos.

    protected $primaryKey = 'id_ambito_divulgacion';
    // Define la clave primaria de la tabla.

    protected $fillable = [

        'nombre_ambito_divulgacion',
        'producto_academico_id',
        
    ];
// Define los campos que se pueden asignar masivamente (mass assignment).

    // Relación uno a muchos inversa con ProductoAcademico.
    public function productoAcademicoAmbitoDivulgacion():BelongsTo
    {
        return $this->belongsTo(ProductoAcademico::class, 'producto_academico_id', 'id_producto_academico');
        // Especifica que esta relación se basa en la clave foránea `producto_academico_id` y la clave primaria `id_producto_academico` del modelo `ProductoAcademico`.

    }

   // Relación uno a muchos con ProduccionAcademica.
   public function produccionAcademicasAmbitoDivulgacion(): HasMany
   {
       return $this->hasMany(ProduccionAcademica::class, 'ambito_divulgacion_id', 'id_ambito_divulgacion');
       // Especifica que esta relación se basa en la clave foránea `ambito_divulgacion_id` y la clave primaria `id_ambito_divulgacion` del modelo `AmbitoDivulgacion`.
    }
}
