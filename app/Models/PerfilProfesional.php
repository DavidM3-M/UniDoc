<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerfilProfesional extends Model
{
    protected $table = 'perfil_profesional';
    protected $primaryKey = 'id_perfil_profesional';
    public $timestamps = false;
    protected $fillable = [];
}
