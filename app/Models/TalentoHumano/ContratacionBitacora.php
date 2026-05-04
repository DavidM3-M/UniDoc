<?php

namespace App\Models\TalentoHumano;

use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro inmutable de auditoría legal sobre modificaciones a contratos.
 *
 * Cada fila captura: quién modificó, cuándo, qué tipo de operación,
 * snapshot antes y después del contrato, y el motivo declarado.
 * No se usa updated_at para garantizar la inmutabilidad del registro.
 */
class ContratacionBitacora extends Model
{
    protected $table      = 'contratacion_bitacoras';
    protected $primaryKey = 'id_bitacora';

    // Registro inmutable: solo tiene created_at, nunca se actualiza
    public $timestamps = false;
    const CREATED_AT   = 'created_at';

    protected $fillable = [
        'contratacion_id',
        'user_modifico_id',
        'tipo_modificacion',
        'datos_anteriores',
        'datos_nuevos',
        'motivo',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos'     => 'array',
        'created_at'       => 'datetime',
    ];

    public function contratacion(): BelongsTo
    {
        return $this->belongsTo(Contratacion::class, 'contratacion_id', 'id_contratacion');
    }

    public function usuarioQueModifico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_modifico_id', 'id');
    }
}
