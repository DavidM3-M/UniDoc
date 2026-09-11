<?php

namespace App\Models;

use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un movimiento en el expediente de un docente: algo suyo se aprobó o se rechazó.
 *
 * Se registra en lugar de enviar un correo por cada uno. El comando `expediente:resumen-diario`
 * los agrupa y manda uno solo con todo lo del día.
 */
class MovimientoExpediente extends Model
{
    protected $table = 'movimientos_expediente';

    protected $primaryKey = 'id_movimiento';

    public const APROBADO  = 'aprobado';
    public const ACTUALIZADO = 'actualizado';
    public const RECHAZADO = 'rechazado';

    protected $fillable = [
        'user_id',
        'accion',
        'categoria',
        'descripcion',
        'motivo',
        'revisado_por_rol',
        'notificado_en',
        'lote_notificacion',
    ];

    protected $casts = [
        'notificado_en' => 'datetime',
    ];

    /**
     * Deja constancia de una revisión sin enviar nada.
     *
     * Es best-effort a propósito: si esto falla, la revisión ya está escrita y confirmada, y perder
     * el aviso no puede tumbarla. El mismo criterio que ya usan las notificaciones del escalafón.
     */
    public static function registrar(
        int $userId,
        string $accion,
        string $categoria,
        string $descripcion,
        ?string $motivo = null,
        ?string $rol = null,
        // Los documentos avisan al instante, así que su movimiento nace ya notificado: sigue
        // sirviendo de historial, pero `expediente:resumen-diario` lo salta y el docente no
        // recibe a las 18:00 un segundo aviso de lo que ya leyó por la mañana.
        ?\DateTimeInterface $notificadoEn = null
    ): void {
        try {
            static::create([
                'user_id'          => $userId,
                'accion'           => $accion,
                'categoria'        => $categoria,
                // El título de una producción o de un estudio puede exceder la columna; se recorta
                // en vez de reventar el insert y perder el movimiento entero.
                'descripcion'      => mb_substr($descripcion, 0, 255),
                'motivo'           => $motivo,
                'revisado_por_rol' => $rol,
                'notificado_en'    => $notificadoEn,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error(
                "No se pudo registrar el movimiento de expediente del usuario {$userId}: " . $e->getMessage()
            );
        }
    }

    public function scopeSinNotificar($query)
    {
        return $query->whereNull('notificado_en');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
