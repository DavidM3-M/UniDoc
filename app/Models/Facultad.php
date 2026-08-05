<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facultad extends Model
{
    protected $table = 'facultades';
    protected $primaryKey = 'id_facultad';
    public $timestamps = false;
    protected $fillable = [];
}
