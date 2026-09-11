<?php

namespace App\Models;

use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una notificación que el sistema ya se comprometió a enviar.
 *
 * La fila se crea **antes** del envío, no después. Así el índice único sobre `clave` actúa como
 * cerrojo: si dos peticiones concurrentes intentan notificar el mismo hecho, la segunda choca
 * contra la restricción y se descarta sin llegar al servidor de correo.
 */
class NotificacionEnviada extends Model
{
    protected $table = 'notificaciones_enviadas';

    protected $primaryKey = 'id_notificacion_enviada';

    protected $fillable = [
        'clave',
        'tipo',
        'user_id',
        'enviado_en',
        'intentos',
        'ultimo_error',
    ];

    protected $casts = [
        'enviado_en' => 'datetime',
        'intentos'   => 'integer',
    ];

    public function marcarEnviada(): void
    {
        $this->update([
            'enviado_en'   => now(),
            'intentos'     => $this->intentos + 1,
            'ultimo_error' => null,
        ]);
    }

    /**
     * Deja constancia del fallo sin borrar la reserva.
     *
     * La fila se conserva con `enviado_en` en null: es lo que permite responder «esto se intentó
     * enviar y no salió» en vez de no tener ni rastro, que es la situación de hoy.
     */
    public function marcarFallida(string $error): void
    {
        $this->update([
            'intentos'     => $this->intentos + 1,
            // El mensaje de un fallo de SMTP puede traer la traza entera; con el motivo basta.
            'ultimo_error' => mb_substr($error, 0, 500),
        ]);
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /** Notificaciones reservadas que nunca llegaron a salir. */
    public function scopeSinEnviar($query)
    {
        return $query->whereNull('enviado_en');
    }
}
