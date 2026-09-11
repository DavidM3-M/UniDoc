<?php

namespace Database\Seeders;

use App\Models\EscalonDocente;
use App\Models\Idioma;
use Illuminate\Database\Seeder;

/**
 * Siembra los cuatro escalones que ya regían hardcodeados en
 * `CalculoPuntajeDocenteService::CATEGORIAS`, con los mismos valores en meses.
 *
 * Los números no cambiaron con el nuevo reglamento, pero **sí lo que significan**: 48, 120 y 216 ya
 * no son meses totales en la Universidad sino meses en el escalón inmediatamente inferior (4 años
 * como Auxiliar para llegar a Asistente, 10 como Asistente para Asociado, 18 como Asociado para
 * Titular). De ahí el nombre de la columna, `meses_minimos_escalon_anterior`.
 *
 * El requisito de idioma exigía "inglés" en el código viejo (el propio nombre de la constante lo
 * decía: NIVELES_INGLES), aunque en la práctica no filtraba por idioma. Aquí sí queda explícito:
 * se crea "Inglés" en el catálogo de idiomas si todavía no existe, y los escalones apuntan a él.
 *
 * `evaluacion_minima` = 4.0 en los tres escalones con requisitos: el mismo valor por defecto que
 * tenía el umbral único y global antes de eliminarse (`UmbralEvaluacionDocenteService::VALOR_POR_DEFECTO`).
 */
class EscalonDocenteSeeder extends Seeder
{
    public function run(): void
    {
        $ingles = Idioma::firstOrCreate(['nombre_idioma' => 'Inglés'], ['activo' => true]);

        $escalones = [
            [
                'nombre' => 'Auxiliar',
                'orden' => 1,
                'formacion_minima' => null,
                'idioma_catalogo_id' => null,
                'nivel_mcer_minimo' => null,
                'puntaje_minimo' => null,
                'meses_minimos_escalon_anterior' => null,
            ],
            [
                'nombre' => 'Asistente',
                'orden' => 2,
                'formacion_minima' => 'Maestría',
                'idioma_catalogo_id' => $ingles->id_idioma_catalogo,
                'nivel_mcer_minimo' => 'B1',
                'puntaje_minimo' => 20,
                'meses_minimos_escalon_anterior' => 48,
                'evaluacion_minima' => 4.0,
            ],
            [
                'nombre' => 'Asociado',
                'orden' => 3,
                'formacion_minima' => 'Doctorado',
                'idioma_catalogo_id' => $ingles->id_idioma_catalogo,
                'nivel_mcer_minimo' => 'B2',
                'puntaje_minimo' => 30,
                'meses_minimos_escalon_anterior' => 120,
                'evaluacion_minima' => 4.0,
            ],
            [
                'nombre' => 'Titular',
                'orden' => 4,
                'formacion_minima' => 'Doctorado',
                'idioma_catalogo_id' => $ingles->id_idioma_catalogo,
                'nivel_mcer_minimo' => 'B2',
                'puntaje_minimo' => 60,
                'meses_minimos_escalon_anterior' => 216,
                'evaluacion_minima' => 4.0,
            ],
        ];

        foreach ($escalones as $datos) {
            EscalonDocente::updateOrCreate(['nombre' => $datos['nombre']], $datos);
        }
    }
}
