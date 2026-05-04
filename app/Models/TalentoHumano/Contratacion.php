<?php

namespace App\Models\TalentoHumano;

use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contratacion extends Model
{
    protected $table = 'contratacions';
    protected $primaryKey = 'id_contratacion';

    protected $fillable = [
        'user_id',
        'convocatoria_id',
        'tipo_proceso',
        'tipo_vinculacion',
        'tipo_contrato',
        'area',
        'fecha_inicio',
        'fecha_fin',
        'valor_contrato',
        'observaciones',
    ];

    public function usuarioContratacion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(ContratacionBitacora::class, 'contratacion_id', 'id_contratacion')
            ->orderBy('created_at', 'desc');
    }
}
