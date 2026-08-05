<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TipoCargoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiposCargo = [
            // Cargos docentes
            [
                'nombre_tipo_cargo' => 'Profesor Titular',
                'descripcion' => 'Profesor con título doctoral y experiencia docente',
                'es_administrativo' => false,
            ],
            [
                'nombre_tipo_cargo' => 'Profesor Asociado',
                'descripcion' => 'Profesor con maestría y experiencia docente',
                'es_administrativo' => false,
            ],
            [
                'nombre_tipo_cargo' => 'Profesor Asistente',
                'descripcion' => 'Profesor con título profesional y experiencia docente',
                'es_administrativo' => false,
            ],
            [
                'nombre_tipo_cargo' => 'Instructor',
                'descripcion' => 'Profesor con título profesional en formación',
                'es_administrativo' => false,
            ],
            [
                'nombre_tipo_cargo' => 'Profesor Cátedra',
                'descripcion' => 'Profesor contratado por horas de cátedra',
                'es_administrativo' => false,
            ],
            // Cargos administrativos
            [
                'nombre_tipo_cargo' => 'Secretario Académico',
                'descripcion' => 'Responsable de asuntos académicos administrativos',
                'es_administrativo' => true,
            ],
            [
                'nombre_tipo_cargo' => 'Coordinador de Programa',
                'descripcion' => 'Coordinador administrativo de programas académicos',
                'es_administrativo' => true,
            ],
            [
                'nombre_tipo_cargo' => 'Asistente Administrativo',
                'descripcion' => 'Apoyo administrativo general',
                'es_administrativo' => true,
            ],
            [
                'nombre_tipo_cargo' => 'Jefe de Departamento',
                'descripcion' => 'Jefe administrativo de departamento académico',
                'es_administrativo' => true,
            ],
            [
                'nombre_tipo_cargo' => 'Decano',
                'descripcion' => 'Director administrativo de facultad',
                'es_administrativo' => true,
            ],
        ];

        foreach ($tiposCargo as $tipo) {
            \App\Models\TipoCargo::create($tipo);
        }
    }
}
