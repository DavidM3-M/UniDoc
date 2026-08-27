<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BancoSeeder extends Seeder
{
    /**
     * Carga el catálogo de entidades financieras vigiladas en Colombia
     * (database/data/entidades_financieras_colombia.json, curado con datos de Fogafín).
     */
    public function run(): void
    {
        $contenido = json_decode(file_get_contents(base_path('database/data/entidades_financieras_colombia.json')), true);
        $entidades = $contenido['entidades'] ?? [];

        $ahora = Carbon::now();

        $filas = array_map(function ($entidad) use ($ahora) {
            return [
                'nombre'     => $entidad['nombre'],
                'slug'       => $entidad['slug'],
                'tipo'       => $entidad['tipo'],
                'sitio_web'  => $entidad['sitio_web'] ?? null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }, $entidades);

        DB::table('bancos')->upsert(
            $filas,
            ['slug'],
            ['nombre', 'tipo', 'sitio_web', 'updated_at']
        );
    }
}
