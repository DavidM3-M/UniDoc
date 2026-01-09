<?php

namespace App\Models\Aspirante;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Aspirante\Documento;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Usuario\User;

class CertificacionBancaria extends Model
{
    use HasFactory;
    protected $table = 'certificaciones_bancarias';
    protected $primaryKey = 'id_certificacion_bancaria';

    protected $fillable = [
        'user_id',
        'nombre_banco',
        'tipo_cuenta',
        'numero_cuenta',
        'fecha_emision',
    ];

    public function documentosCertificacionBancaria(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    public function usuarioCertificacionBancaria(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

}
