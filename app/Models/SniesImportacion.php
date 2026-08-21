<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de una corrida del importador de programas SNIES. `ImportarSniesJob` la va
 * actualizando mientras procesa el archivo; el frontend hace polling de esta fila para mostrar
 * el progreso.
 */
class SniesImportacion extends Model
{
    protected $table = 'snies_importaciones';

    protected $primaryKey = 'id_importacion';

    protected $fillable = [
        'nombre_archivo',
        'ruta_archivo',
        'estado',
        'total_filas',
        'filas_procesadas',
        'programas_creados',
        'programas_actualizados',
        'niveles_creados',
        'mensaje_error',
        'iniciado_en',
        'finalizado_en',
    ];

    protected $casts = [
        'iniciado_en' => 'datetime',
        'finalizado_en' => 'datetime',
    ];
}
