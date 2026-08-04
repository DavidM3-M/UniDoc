<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CodigoCiiuSeeder extends Seeder
{
    /**
     * Carga el catálogo oficial de códigos CIIU (database/data/ciiu_flat.json).
     */
    public function run(): void
    {
        $registros = json_decode(file_get_contents(base_path('database/data/ciiu_flat.json')), true);

        $ahora = Carbon::now();

        $filas = array_map(function ($registro) use ($ahora) {
            return [
                'codigo'         => $registro['codigo'],
                'descripcion'    => $registro['descripcion'],
                'grupo'          => $registro['grupo'] ?? null,
                'division'       => $registro['division'] ?? null,
                'seccion'        => $registro['seccion'] ?? null,
                'seccion_titulo' => $registro['seccion_titulo'] ?? null,
                'created_at'     => $ahora,
                'updated_at'     => $ahora,
            ];
        }, $registros);

        collect($filas)->chunk(200)->each(function ($chunk) {
            DB::table('codigos_ciiu')->upsert(
                $chunk->all(),
                ['codigo'],
                ['descripcion', 'grupo', 'division', 'seccion', 'seccion_titulo', 'updated_at']
            );
        });
    }
}
