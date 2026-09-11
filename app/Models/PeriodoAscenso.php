<?php

namespace App\Models;

use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fecha de cierre de ascenso del escalafón docente. Ver la migración
 * `2026_08_27_000001_create_periodos_ascenso_table` para el porqué de que no exista una fecha de
 * apertura.
 *
 * `fecha_cierre` es el corte contra el que se congelan **todos** los requisitos de ascenso, no solo
 * la producción académica: la antigüedad se mide hasta ahí y los documentos tienen que estar
 * subidos antes (su aval puede llegar después). Ver `MotorEscalafonDocenteService::evaluarAscenso()`.
 */
class PeriodoAscenso extends Model
{
    protected $table = 'periodos_ascenso';

    protected $primaryKey = 'id_periodo_ascenso';

    protected $fillable = [
        'nombre',
        'fecha_cierre',
        'cerrado_en',
        'creado_por',
    ];

    protected $casts = [
        'fecha_cierre' => 'date',
        'cerrado_en' => 'datetime',
    ];

    /**
     * El periodo contra el que se evalúa hoy: el más próximo que todavía no ha cerrado.
     *
     * Un periodo se considera cerrado cuando alguien lo cerró a mano (`cerrado_en`) o cuando ya
     * pasó su `fecha_cierre`. Devuelve null si no hay ninguno vigente, que es lo normal entre un
     * periodo y el siguiente: ahí el docente sigue subiendo documentos y consultando su estado,
     * simplemente no hay ascensos que ejecutar.
     */
    public static function vigente(): ?self
    {
        return static::whereNull('cerrado_en')
            ->whereDate('fecha_cierre', '>=', now()->toDateString())
            ->orderBy('fecha_cierre')
            ->first();
    }

    /** El último periodo que cerró, vigente o no. Es el piso de la siguiente `fecha_cierre`. */
    public static function ultimo(): ?self
    {
        return static::orderByDesc('fecha_cierre')->first();
    }

    public function estaCerrado(): bool
    {
        return $this->cerrado_en !== null || $this->fecha_cierre->copy()->endOfDay()->isPast();
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por', 'id');
    }
}
