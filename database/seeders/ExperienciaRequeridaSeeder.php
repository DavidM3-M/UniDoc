<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExperienciaRequeridaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $experiencias = [
            // Experiencias docentes
            [
                'descripcion_experiencia' => 'Sin experiencia requerida',
                'horas_minimas' => 0,
                'anos_equivalentes' => 0,
                'es_administrativo' => false,
            ],
            [
                'descripcion_experiencia' => '1 año de experiencia docente',
                'horas_minimas' => 1600, // Aproximadamente 1 año a tiempo completo
                'anos_equivalentes' => 1,
                'es_administrativo' => false,
            ],
            [
                'descripcion_experiencia' => '2 años de experiencia docente',
                'horas_minimas' => 3200,
                'anos_equivalentes' => 2,
                'es_administrativo' => false,
            ],
            [
                'descripcion_experiencia' => '3 años de experiencia docente',
                'horas_minimas' => 4800,
                'anos_equivalentes' => 3,
                'es_administrativo' => false,
            ],
            [
                'descripcion_experiencia' => '5 años de experiencia docente',
                'horas_minimas' => 8000,
                'anos_equivalentes' => 5,
                'es_administrativo' => false,
            ],
            [
                'descripcion_experiencia' => '10 años de experiencia docente',
                'horas_minimas' => 16000,
                'anos_equivalentes' => 10,
                'es_administrativo' => false,
            ],
            // Experiencias administrativas
            [
                'descripcion_experiencia' => 'Sin experiencia administrativa requerida',
                'horas_minimas' => 0,
                'anos_equivalentes' => 0,
                'es_administrativo' => true,
            ],
            [
                'descripcion_experiencia' => '1 año de experiencia administrativa',
                'horas_minimas' => 2080, // Horas laborales al año
                'anos_equivalentes' => 1,
                'es_administrativo' => true,
            ],
            [
                'descripcion_experiencia' => '2 años de experiencia administrativa',
                'horas_minimas' => 4160,
                'anos_equivalentes' => 2,
                'es_administrativo' => true,
            ],
            [
                'descripcion_experiencia' => '3 años de experiencia administrativa',
                'horas_minimas' => 6240,
                'anos_equivalentes' => 3,
                'es_administrativo' => true,
            ],
            [
                'descripcion_experiencia' => '5 años de experiencia administrativa',
                'horas_minimas' => 10400,
                'anos_equivalentes' => 5,
                'es_administrativo' => true,
            ],
        ];

        foreach ($experiencias as $experiencia) {
            \App\Models\ExperienciaRequerida::create($experiencia);
        }
    }
}
