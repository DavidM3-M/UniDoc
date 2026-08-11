<?php

namespace Database\Seeders;

use App\Constants\ConstAgregarExperiencia\TiposExperiencia;
use App\Models\TipoExperiencia;
use Illuminate\Database\Seeder;

/**
 * Siembra el catálogo inicial de tipos de experiencia.
 *
 * Se alimenta de la constante `TiposExperiencia` a propósito: los registros que ya existen
 * en `experiencias` guardan esos strings exactos, así que copiarlos desde la misma fuente
 * garantiza que coincidan carácter por carácter (tildes incluidas) y que la validación
 * por nombre no rechace datos históricos.
 */
class TipoExperienciaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (TiposExperiencia::all() as $nombre) {
            TipoExperiencia::firstOrCreate(
                ['nombre_tipo_experiencia' => $nombre],
                ['activo' => true]
            );
        }
    }
}
