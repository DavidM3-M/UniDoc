<?php

namespace App\Models;

use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un tramo del docente en un escalón: desde cuándo lo tiene y, si ya ascendió, hasta cuándo.
 *
 * Fuente de verdad de la categoría vigente y del tiempo acumulado en ella. Solo escribe aquí
 * `AscensoEscalafonService`. Ver la migración
 * `2026_08_27_000002_create_historial_escalon_docente_table`.
 *
 * Los tramos de un mismo escalón se acumulan aunque haya interrupciones: quien fue Auxiliar dos
 * años, se retiró y volvió a serlo tres más, tiene cinco años como Auxiliar.
 */
class HistorialEscalonDocente extends Model
{
    protected $table = 'historial_escalon_docente';

    protected $primaryKey = 'id_historial_escalon';

    /** Vías por las que se puede otorgar un escalón. Vocabulario controlado por código. */
    public const VIA_INGRESO = 'ingreso';
    public const VIA_REQUISITOS = 'requisitos';
    public const VIA_EXCEPCION = 'excepcion';

    protected $fillable = [
        'user_id',
        'escalon_id',
        'periodo_ascenso_id',
        'desde',
        'hasta',
        'via',
        'motivo',
        'otorgado_por',
        'revertido_en',
        'revertido_por',
        'motivo_reversion',
    ];

    protected $casts = [
        'desde' => 'date',
        'hasta' => 'date',
        'revertido_en' => 'datetime',
    ];

    /**
     * Tramos que cuentan. Un tramo revertido sigue en la tabla —es su razón de ser: dejar rastro
     * del ascenso que se deshizo— pero no suma antigüedad ni define la categoría.
     */
    public function scopeNoRevertidos(Builder $query): Builder
    {
        return $query->whereNull('revertido_en');
    }

    /** El tramo abierto: el escalón que el docente tiene hoy. */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('revertido_en')->whereNull('hasta');
    }

    public function estaRevertido(): bool
    {
        return $this->revertido_en !== null;
    }

    public function escalon(): BelongsTo
    {
        return $this->belongsTo(EscalonDocente::class, 'escalon_id', 'id_escalon');
    }

    public function periodoAscenso(): BelongsTo
    {
        return $this->belongsTo(PeriodoAscenso::class, 'periodo_ascenso_id', 'id_periodo_ascenso');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function otorgante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'otorgado_por', 'id');
    }

    public function revisorReversion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revertido_por', 'id');
    }
}
