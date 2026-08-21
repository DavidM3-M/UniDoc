<?php

namespace Database\Seeders;

use App\Models\NivelFormacionAcademica;
use Illuminate\Database\Seeder;

/**
 * Siembra los niveles de formación con su `orden` (jerarquía del escalafón).
 *
 * Cubre tres orígenes que antes estaban separados y dejaban huecos:
 *
 * 1. Los niveles del SNIES, que son los que traen los programas importados.
 * 2. Los que solo existían en la constante vieja `TiposEstudio` (Pregrado, Postdoctorado,
 *    Técnico...). Sin ellos, los estudios ya registrados con esos valores quedaban huérfanos:
 *    el docente no podía ni editarlos, porque la validación contra el catálogo los rechazaba.
 * 3. La formación complementaria (Diplomado, Certificación, Curso), que el SNIES no cataloga
 *    pero que sí es parte legítima de la hoja de vida.
 *
 * Sobre `orden`: mayor número, nivel más alto. `null` = no participa en el escalafón (se
 * registra en la hoja de vida pero no asciende a nadie).
 *
 * Dos detalles deliberados:
 * - "Pregrado" y "Universitario" comparten orden 30, y "Técnico Profesional" con "Formación
 *   técnica profesional" comparten el 10: son sinónimos con dos nombres distintos según el
 *   origen (constante vieja vs. SNIES). Al compartir orden, un estudio viejo que dice
 *   "Pregrado" cumple cualquier requisito de "Universitario" sin tener que migrar el dato.
 * - Las especializaciones técnica y tecnológica van por debajo del pregrado universitario
 *   (25 y 27) porque son posteriores al técnico/tecnólogo, no al pregrado.
 *
 * Todo es administrable desde Catálogos → Niveles de formación: esto es solo el punto de
 * partida, el Administrador ajusta el orden cuando quiera.
 */
class NivelFormacionAcademicaSeeder extends Seeder
{
    public function run(): void
    {
        $niveles = [
            // Pregrado
            ['nivel_academico' => 'Pregrado', 'nivel_formacion' => 'Técnico Profesional', 'orden' => 10],
            ['nivel_academico' => 'Pregrado', 'nivel_formacion' => 'Formación técnica profesional', 'orden' => 10],
            ['nivel_academico' => 'Pregrado', 'nivel_formacion' => 'Técnico', 'orden' => 10],
            ['nivel_academico' => 'Pregrado', 'nivel_formacion' => 'Tecnológico', 'orden' => 20],
            ['nivel_academico' => 'Pregrado', 'nivel_formacion' => 'Universitario', 'orden' => 30],
            ['nivel_academico' => 'Pregrado', 'nivel_formacion' => 'Pregrado', 'orden' => 30],

            // Posgrado
            ['nivel_academico' => 'Posgrado', 'nivel_formacion' => 'Especialización técnico profesional', 'orden' => 25],
            ['nivel_academico' => 'Posgrado', 'nivel_formacion' => 'Especialización tecnológica', 'orden' => 27],
            ['nivel_academico' => 'Posgrado', 'nivel_formacion' => 'Especialización', 'orden' => 40],
            ['nivel_academico' => 'Posgrado', 'nivel_formacion' => 'Especialización universitaria', 'orden' => 40],
            ['nivel_academico' => 'Posgrado', 'nivel_formacion' => 'Especialización médico quirúrgica', 'orden' => 40],
            ['nivel_academico' => 'Posgrado', 'nivel_formacion' => 'Maestría', 'orden' => 50],
            ['nivel_academico' => 'Posgrado', 'nivel_formacion' => 'Doctorado', 'orden' => 60],
            ['nivel_academico' => 'Posgrado', 'nivel_formacion' => 'Postdoctorado', 'orden' => 70],

            // Formación complementaria: se registra en la hoja de vida, no cuenta para escalafón.
            ['nivel_academico' => 'Formación complementaria', 'nivel_formacion' => 'Diplomado', 'orden' => null],
            ['nivel_academico' => 'Formación complementaria', 'nivel_formacion' => 'Certificación', 'orden' => null],
            ['nivel_academico' => 'Formación complementaria', 'nivel_formacion' => 'Curso programado o capacitación', 'orden' => null],
        ];

        foreach ($niveles as $nivel) {
            // La clave única del catálogo es (nivel_academico, nivel_formacion); `orden` y
            // `activo` son los valores a crear/completar. `firstOrCreate` no pisa el orden que
            // el Administrador haya ajustado a mano en un nivel que ya existe.
            NivelFormacionAcademica::firstOrCreate(
                [
                    'nivel_academico' => $nivel['nivel_academico'],
                    'nivel_formacion' => $nivel['nivel_formacion'],
                ],
                [
                    'orden' => $nivel['orden'],
                    'activo' => true,
                ]
            );
        }
    }
}
