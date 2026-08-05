<?php

namespace App\Models\TiposProductoAcademico;
// Define el namespace del modelo, organizando el código y evitando conflictos de nombres.

use Illuminate\Database\Eloquent\Model;
// Importa la clase base `Model` de Eloquent, que permite interactuar con la base de datos.

use Illuminate\Database\Eloquent\Factories\HasFactory;
// Importa el trait `HasFactory` para habilitar la generación de fábricas para este modelo.

use Illuminate\Database\Eloquent\Relations\HasMany;
// Importa la clase `HasMany` para definir relaciones de tipo "tiene muchos".

use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
// Importa el modelo `AmbitoDivulgacion` para establecer relaciones con este modelo.

class ProductoAcademico extends Model
// Define la clase `ProductoAcademico`, que extiende la funcionalidad del modelo base de Eloquent.

{
    use HasFactory;
    // Usa el trait `HasFactory` para habilitar la generación de fábricas para este modelo.

    protected $table = 'producto_academicos';
    // Especifica el nombre de la tabla asociada en la base de datos.

    protected $primaryKey = 'id_producto_academico';
    // Define la clave primaria de la tabla.

    protected $fillable = [
        'nombre_producto_academico',
    ];
    // Define los campos que se pueden asignar masivamente (mass assignment).

    public function ambitoDivulgacionsProductoAcademico(): HasMany
    // Define una relación "tiene muchos" con el modelo `AmbitoDivulgacion`.
    {
        return $this->hasMany(AmbitoDivulgacion::class, 'producto_academico_id', 'id_producto_academico');
        // Especifica que esta relación se basa en la clave foránea `producto_academico_id` y la clave primaria `id_producto_academico` del modelo `ProductoAcademico`.

    }
}
