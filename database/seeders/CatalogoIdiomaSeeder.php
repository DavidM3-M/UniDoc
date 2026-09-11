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
            [
                // Escala total 0-990. Correspondencia publicada por ETS en «Mapping the TOEIC
                // Tests on the CEFR»: por debajo de 120 no se certifica ningún nivel.
                'nombre' => 'TOEIC Listening & Reading',
                'vigencia_meses' => 24,
                'rangos' => [
                    [120, 224, 'A1'],
                    [225, 549, 'A2'],
                    [550, 784, 'B1'],
                    [785, 944, 'B2'],
                    [945, 990, 'C1'],
                ],
            ],
            [
                'nombre' => 'Cambridge C2 Proficiency (CPE)',
                'vigencia_meses' => null,
                'rangos' => [
                    [180, 199, 'C1'],
                    [200, 230, 'C2'],
                ],
            ],
            [
                // Escala 10-160 en incrementos de 5, con la correspondencia que publica Duolingo.
                'nombre' => 'Duolingo English Test',
                'vigencia_meses' => 24,
                'rangos' => [
                    [10, 65, 'A1'],
                    [70, 90, 'A2'],
                    [95, 115, 'B1'],
                    [120, 135, 'B2'],
                    [140, 155, 'C1'],
                    [160, 160, 'C2'],
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
                'nombre' => 'DELF A1',
                'vigencia_meses' => null,
                'rangos' => [[50, 100, 'A1']],
            ],
            [
                'nombre' => 'DELF A2',
                'vigencia_meses' => null,
                'rangos' => [[50, 100, 'A2']],
            ],
            [
                'nombre' => 'DALF C1',
                'vigencia_meses' => null,
                'rangos' => [[50, 100, 'C1']],
            ],
            [
                'nombre' => 'DALF C2',
                'vigencia_meses' => null,
                'rangos' => [[50, 100, 'C2']],
            ],
            [
                // A diferencia del DELF, el TCF sí caduca: France Éducation International lo
                // certifica por dos años. Escala 100-699 sobre el total.
                'nombre' => 'TCF',
                'vigencia_meses' => 24,
                'rangos' => [
                    [100, 199, 'A1'],
                    [200, 299, 'A2'],
                    [300, 399, 'B1'],
                    [400, 499, 'B2'],
                    [500, 599, 'C1'],
                    [600, 699, 'C2'],
                ],
            ],
            [
                'nombre' => 'TEF',
                'vigencia_meses' => 24,
                'rangos' => [
                    [69, 203, 'A1'],
                    [204, 360, 'A2'],
                    [361, 540, 'B1'],
                    [541, 698, 'B2'],
                    [699, 833, 'C1'],
                    [834, 900, 'C2'],
                ],
            ],
        ],

        'Alemán' => [
            // Los Goethe-Zertifikat son exámenes por nivel: se aprueban con 60 sobre 100 y el
            // nivel lo fija el diploma, no el puntaje. No caducan.
            [
                'nombre' => 'Goethe-Zertifikat A1',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'A1']],
            ],
            [
                'nombre' => 'Goethe-Zertifikat A2',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'A2']],
            ],
            [
                'nombre' => 'Goethe-Zertifikat B1',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'B1']],
            ],
            [
                'nombre' => 'Goethe-Zertifikat B2',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'B2']],
            ],
            [
                'nombre' => 'Goethe-Zertifikat C1',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'C1']],
            ],
            [
                'nombre' => 'Goethe-Zertifikat C2 (GDS)',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'C2']],
            ],
            [
                // El TestDaF no da una nota sino un nivel propio, el TestDaF-Niveaustufe (TDN),
                // del 3 al 5. Es el que piden las universidades alemanas para admitir estudiantes.
                'nombre' => 'TestDaF',
                'vigencia_meses' => null,
                'rangos' => [
                    [3, 3, 'B2'],
                    [4, 4, 'B2'],
                    [5, 5, 'C1'],
                ],
            ],
        ],

        'Italiano' => [
            // CILS y PLIDA certifican un nivel concreto del MCER y se aprueban con 60 sobre 100.
            [
                'nombre' => 'CILS A1',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'A1']],
            ],
            [
                'nombre' => 'CILS A2',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'A2']],
            ],
            [
                'nombre' => 'CILS Uno B1',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'B1']],
            ],
            [
                'nombre' => 'CILS Due B2',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'B2']],
            ],
            [
                'nombre' => 'CILS Tre C1',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'C1']],
            ],
            [
                'nombre' => 'CILS Quattro C2',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'C2']],
            ],
            [
                'nombre' => 'PLIDA B2',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'B2']],
            ],
            [
                'nombre' => 'PLIDA C1',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'C1']],
            ],
        ],

        // Español como lengua extranjera: hace falta para un docente cuya lengua materna es otra.
        'Español' => [
            [
                'nombre' => 'DELE B1',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'B1']],
            ],
            [
                'nombre' => 'DELE B2',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'B2']],
            ],
            [
                'nombre' => 'DELE C1',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'C1']],
            ],
            [
                'nombre' => 'DELE C2',
                'vigencia_meses' => null,
                'rangos' => [[60, 100, 'C2']],
            ],
            [
                // El SIELE sí caduca —el Instituto Cervantes lo certifica por cinco años— y da un
                // puntaje global de 0 a 1000 en vez de un diploma por nivel.
                'nombre' => 'SIELE Global',
                'vigencia_meses' => 60,
                'rangos' => [
                    [0, 249, 'A1'],
                    [250, 499, 'A2'],
                    [500, 749, 'B1'],
                    [750, 899, 'B2'],
                    [900, 1000, 'C1'],
                ],
            ],
        ],

        // Los tres idiomas que siguen no tienen equivalencia MCER publicada por su organismo
        // emisor: las que van aquí son las correspondencias de uso común. Por eso conviene más que
        // en ningún otro caso que el Administrador las confirme antes de que decidan un ascenso.
        // Se incluyen porque sin ellas un docente con JLPT N2 no tendría dónde registrarlo.
        'Mandarín' => [
            [
                'nombre' => 'HSK 3',
                'vigencia_meses' => 24,
                'rangos' => [[180, 300, 'A2']],
            ],
            [
                'nombre' => 'HSK 4',
                'vigencia_meses' => 24,
                'rangos' => [[180, 300, 'B1']],
            ],
            [
                'nombre' => 'HSK 5',
                'vigencia_meses' => 24,
                'rangos' => [[180, 300, 'B2']],
            ],
            [
                'nombre' => 'HSK 6',
                'vigencia_meses' => 24,
                'rangos' => [[180, 300, 'C1']],
            ],
        ],

        'Japonés' => [
            [
                'nombre' => 'JLPT N3',
                'vigencia_meses' => null,
                'rangos' => [[95, 180, 'B1']],
            ],
            [
                'nombre' => 'JLPT N2',
                'vigencia_meses' => null,
                'rangos' => [[90, 180, 'B2']],
            ],
            [
                'nombre' => 'JLPT N1',
                'vigencia_meses' => null,
                'rangos' => [[100, 180, 'C1']],
            ],
        ],

        'Coreano' => [
            [
                'nombre' => 'TOPIK II (nivel 3)',
                'vigencia_meses' => 24,
                'rangos' => [[120, 300, 'B1']],
            ],
            [
                'nombre' => 'TOPIK II (nivel 4)',
                'vigencia_meses' => 24,
                'rangos' => [[150, 300, 'B2']],
            ],
            [
                'nombre' => 'TOPIK II (nivel 5)',
                'vigencia_meses' => 24,
                'rangos' => [[190, 300, 'C1']],
            ],
            [
                'nombre' => 'TOPIK II (nivel 6)',
                'vigencia_meses' => 24,
                'rangos' => [[230, 300, 'C2']],
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
