<?php

namespace App\Models;

use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una intervención manual del Administrador sobre el historial de escalafón.
 *
 * Registro inmutable: quién tocó qué tramo, cuándo, con qué motivo y con el expediente antes y
 * después. Ver la migración `2026_09_02_000001_create_historial_escalon_bitacoras_table` para el
 * porqué de que exista una tabla aparte en vez de un par de columnas en el tramo.
 *
 * Solo escriben aquí `AscensoEscalafonService::ingresarManual()` y `::corregirTramo()`, dentro de la
 * misma transacción que la escritura que registran. El ascenso y la reversión no pasan por aquí:
 * quedan firmados en el propio tramo.
 */
class HistorialEscalonBitacora extends Model
{
    protected $table = 'historial_escalon_bitacoras';

    protected $primaryKey = 'id_bitacora';

    /** Vocabulario controlado por código, igual que `HistorialEscalonDocente::VIA_*`. */
    public const TIPO_CREACION = 'creacion';
    public const TIPO_ACTUALIZACION = 'actualizacion';

    // Registro inmutable: solo tiene created_at, nunca se actualiza.
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'historial_escalon_id',
        'docente_id',
        'user_modifico_id',
        'tipo_modificacion',
        'datos_anteriores',
        'datos_nuevos',
        'motivo',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array',
        'created_at' => 'datetime',
    ];

    public function tramo(): BelongsTo
    {
        return $this->belongsTo(HistorialEscalonDocente::class, 'historial_escalon_id', 'id_historial_escalon');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id', 'id');
    }

    public function usuarioQueModifico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_modifico_id', 'id');
    }
}
