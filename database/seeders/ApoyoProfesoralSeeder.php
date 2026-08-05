<?php

namespace Database\Seeders;

use App\Models\Usuario\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ApoyoProfesoralSeeder extends Seeder
{
    
    public function run(): void
    {
        //Crear usuario ayudaProfesoral por defecto si no existe
        $apoyoProfesoral = User::firstOrCreate([
            'email' => 'apoyoprofesoral@universidad.com'
        ], [
            'municipio_id'           => 703, // Cambia este valor según el municipio en tu DB
            'tipo_identificacion'    => 'Cédula de ciudadanía', // Cambia según los valores en TipoIdentificacion::all()
            'numero_identificacion'  => '910111213', // Cambia según necesidad
            'genero'                 => 'Femenino', // Cambia según los valores en Genero::all()
            'primer_nombre'          => 'Apoyo',
            'segundo_nombre'         => 'Profesoral',
            'primer_apellido'        => 'Universidad',
            'segundo_apellido'       => 'Gestión',
            'fecha_nacimiento'       => '1950-01-01', // Ajusta según necesidad
            'estado_civil'           => 'Soltero', // Cambia según los valores en EstadoCivil::all()
            'email'                  => 'apoyoprofesoral@universidad.com',
            'password'               => Hash::make('apoyoprofesoral123'), // Cambia la contraseña si lo deseas
        ]);
        $apoyoProfesoral->assignRole('Apoyo Profesoral');

        echo "Apoyo Profesoral creado con email: apoyoprofesoral@universidad.com y contraseña: apoyoprofesoral123\n";
    }
}
