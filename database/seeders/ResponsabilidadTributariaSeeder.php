<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ResponsabilidadTributariaSeeder extends Seeder
{
    /**
     * Carga el catálogo oficial de responsabilidades tributarias de la DIAN
     * (database/data/responsabilidades_tributarias.json).
     */
    public function run(): void
    {
        $registros = json_decode(file_get_contents(base_path('database/data/responsabilidades_tributarias.json')), true);

        $ahora = Carbon::now();

        $filas = array_map(function ($registro) use ($ahora) {
            return [
                'codigo'      => $registro['codigo'],
                'descripcion' => $registro['descripcion'],
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ];
        }, $registros);

        DB::table('responsabilidades_tributarias')->upsert(
            $filas,
            ['codigo'],
            ['descripcion', 'updated_at']
        );
    }
}
