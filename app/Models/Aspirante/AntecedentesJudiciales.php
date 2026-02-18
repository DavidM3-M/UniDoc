<?php


namespace App\Models\Aspirante;

use App\Models\Usuario\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AntecedentesJudiciales extends Model
{
    use HasFactory;

    protected $table = 'antecedentes_judiciales';
    protected $primaryKey = 'id_antecedente';

    protected $fillable = [
        'user_id',
        'fecha_validacion',
        'estado_antecedentes',
    ];

    public function documentosAntecedentesJudiciales()
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    public function usuarioAntecedentesJudiciales()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }


}
