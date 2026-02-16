<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCargo extends Model
{
    protected $table = 'tipo_cargo';
    protected $primaryKey = 'id_tipo_cargo';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $fillable = [];
}
