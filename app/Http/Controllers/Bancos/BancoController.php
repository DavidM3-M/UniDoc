<?php

namespace App\Http\Controllers\Bancos;

use App\Models\Banco;

class BancoController
{
    // Catálogo de entidades financieras vigiladas en Colombia (bancos, compañías de
    // financiamiento, corporaciones financieras y SEDPES), cargado localmente vía
    // BancoSeeder desde database/data/entidades_financieras_colombia.json (Fogafín).
    public function obtenerBancos()
    {
        $bancos = Banco::orderBy('nombre')->get(['nombre']);

        return response()->json(['bancos' => $bancos]);
    }
}
