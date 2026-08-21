<?php

namespace Database\Seeders;

use App\Models\ExamenIdioma;
use App\Models\Idioma;
use App\Models\RangoExamenIdioma;
use Illuminate\Database\Seeder;

/**
 * Datos de arranque del catálogo de idiomas: idiomas, sus exámenes de certificación y la
 * equivalencia puntaje → nivel MCER de cada uno.
 *
 * `catalogo_idiomas`, `examenes_idioma` y `examenes_idioma_rangos` nacían vacías y no había
 * seeder. En una base nueva eso dejaba el desplegable "Idioma" sin opciones y el docente no
 * podía registrar ningún idioma hasta que el Administrador cargara todo a mano.
 *
 * **Estas equivalencias son un punto de partida, no una decisión institucional.** Son las
 * tablas de conversión publicadas por cada examinador, pero la Universidad puede fijar las
 * suyas: el Administrador edita, agrega o retira todo esto desde Catálogos → Idiomas. Antes de
 * usarlo en producción conviene que el Administrador las confirme.
 *
 * Es estrictamente aditivo (`firstOrCreate` sobre las claves únicas): volver a ejecutarlo no
 * duplica nada y nunca pisa lo que el Administrador haya ajustado. Los rangos solo se crean si
 * el examen todavía no tiene ninguno.
 */
class CatalogoIdiomaSeeder extends Seeder
{
    /**
     * Estructura: idioma → exámenes → rangos [mínimo, máximo, nivel MCER].
     *
     * `vigencia_meses` nulo significa que el certificado no vence.
     */
    private const CATALOGO = [
        'Inglés' => [
            [
                'nombre' => 'IELTS Academic',
                'vigencia_meses' => 24,
                'rangos' => [
                    [4.0, 4.5, 'A2'],
                    [5.0, 5.5, 'B1'],
                    [6.0, 6.5, 'B2'],
                    [7.0, 8.0, 'C1'],
                    [8.5, 9.0, 'C2'],
                ],
            ],
            [
                'nombre' => 'TOEFL iBT',
                'vigencia_meses' => 24,
                'rangos' => [
                    [0, 41, 'A2'],
                    [42, 71, 'B1'],
                    [72, 94, 'B2'],
                    [95, 114, 'C1'],
                    [115, 120, 'C2'],
                ],
            ],
            [
                // Cambridge no vence: por eso `vigencia_meses` va nulo y la tarjeta del docente
                // no dibuja ninguna píldora de vigencia.
                'nombre' => 'Cambridge B2 First (FCE)',
                'vigencia_meses' => null,
                'rangos' => [
                    [140, 159, 'B1'],
                    [160, 179, 'B2'],
                    [180, 190, 'C1'],
                ],
            ],
            [
                'nombre' => 'Cambridge C1 Advanced (CAE)',
                'vigencia_meses' => null,
                'rangos' => [
                    [160, 179, 'B2'],
                    [180, 199, 'C1'],
                    [200, 210, 'C2'],
                ],
            ],
        ],

        'Francés' => [
            [
                // Los DELF/DALF son diplomas por nivel: se aprueban con 50 sobre 100 y el nivel
                // lo fija el diploma, no el puntaje. Se cargan como exámenes distintos.
                'nombre' => 'DELF B1',
                'vigencia_meses' => null,
                'rangos' => [[50, 100, 'B1']],
            ],
            [
                'nombre' => 'DELF B2',
                'vigencia_meses' => null,
                'rangos' => [[50, 100, 'B2']],
            ],
            [
                'nombre' => 'DALF C1',
                'vigencia_meses' => null,
                'rangos' => [[50, 100, 'C1']],
            ],
        ],

        'Portugués' => [
            [
                'nombre' => 'Celpe-Bras',
                'vigencia_meses' => null,
                'rangos' => [
                    [2.0, 2.75, 'B1'],
                    [3.0, 3.75, 'B2'],
                    [4.0, 4.75, 'C1'],
                    [5.0, 5.0, 'C2'],
                ],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::CATALOGO as $nombreIdioma => $examenes) {
            $idioma = Idioma::firstOrCreate(
                ['nombre_idioma' => $nombreIdioma],
                ['activo' => true]
            );

            foreach ($examenes as $datosExamen) {
                // `firstOrCreate` y no `updateOrCreate`: si el examen ya existe, su vigencia y
                // su bandera `activo` son las que puso el Administrador y no se tocan.
                $examen = ExamenIdioma::firstOrCreate(
                    [
                        'idioma_catalogo_id' => $idioma->id_idioma_catalogo,
                        'nombre_examen' => $datosExamen['nombre'],
                    ],
                    [
                        'vigencia_meses' => $datosExamen['vigencia_meses'],
                        'activo' => true,
                    ]
                );

                // Si el examen ya tiene rangos, se respetan: pueden ser los que el Administrador
                // ajustó y volver a sembrarlos los pisaría.
                if ($examen->rangos()->exists()) {
                    continue;
                }

                foreach ($datosExamen['rangos'] as [$minimo, $maximo, $nivel]) {
                    RangoExamenIdioma::create([
                        'examen_idioma_id' => $examen->id_examen_idioma,
                        'puntaje_min' => $minimo,
                        'puntaje_max' => $maximo,
                        'nivel_mcer' => $nivel,
                    ]);
                }
            }
        }
    }
}
