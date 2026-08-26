<?php

namespace App\Models\Aspirante;

use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;


class Documento extends Model
{
    // definimos el nombre de la tabla
    protected $table = 'documentos';
    // definimos la clave primaria de la tabla
    protected $primaryKey = 'id_documento';

    protected $fillable = [
        'archivo',
        'estado',
        'documentable_id',
        'documentable_type',
        'motivo_rechazo',
        // Quién decidió el estado y cuándo. Ver la migración
        // 2026_08_26_000002_add_trazabilidad_revision_to_documentos_table.
        'revisado_por',
        'revisado_en',
    ];

    protected $casts = [
        'revisado_en' => 'datetime',
    ];

    // Definimos las relaciones
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Usuario que aprobó o rechazó el documento.
     *
     * Null en los documentos que nunca se han revisado y también en los que se decidieron antes
     * de que existiera esta columna: en esos, `estado` ya no es `pendiente` pero no hay autor.
     */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por', 'id');
    }

    /**
     * ¿El estado del documento viene de una decisión anterior a la trazabilidad?
     *
     * Distingue «nadie lo ha revisado» de «se revisó, pero no sabemos quién», que es lo que la
     * interfaz muestra como «aval anterior al cambio».
     */
    public function esDecisionHistorica(): bool
    {
        return $this->estado !== 'pendiente' && $this->revisado_por === null;
    }

    /**
     * Registra la decisión y su autor en una sola llamada.
     *
     * Centralizarlo evita que un controlador cambie `estado` y se olvide de la firma: es
     * exactamente lo que pasaba antes en `EvaluadorProduccionController`.
     */
    public function registrarRevision(string $estado, ?int $revisorId, ?string $motivo = null): void
    {
        $this->estado         = $estado;
        $this->motivo_rechazo = $estado === 'rechazado' ? $motivo : null;
        $this->revisado_por   = $revisorId;
        $this->revisado_en    = now();
        $this->save();
    }
}
