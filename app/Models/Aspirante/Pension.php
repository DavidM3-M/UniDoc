<?php

namespace App\Models\Aspirante;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Aspirante\Documento;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Usuario\User;

class Pension extends Model
{
    use HasFactory;
    protected $table = 'pensions';
    protected $primaryKey = 'id_pension';

    protected $fillable = [
        'user_id',
        'regimen_pensional',
        'entidad_pensional',
        'nit_entidad',
    ];

    public function documentosPension(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    public function usuarioPension(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }



}
