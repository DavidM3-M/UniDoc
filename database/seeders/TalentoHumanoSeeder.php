<?php

namespace Database\Seeders;

use App\Models\Usuario\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class TalentoHumanoSeeder extends Seeder
{
   
    public function run(): void
    {
        // Crear usuario administrador por defecto si no existe
        $talentoHumano= User::firstOrCreate([
            'email' => 'talentoHumano@universidad.com'
        ], [
            'municipio_id'           => 703, // Cambia este valor según el municipio en tu DB
            'tipo_identificacion'    => 'Cédula de ciudadanía', // Cambia según los valores en TipoIdentificacion::all()
            'numero_identificacion'  => '156784231', // Cambia según necesidad
            'genero'                 => 'Femenino', // Cambia según los valores en Genero::all()
            'primer_nombre'          => 'Talento',
            'segundo_nombre'         => 'Humano',
            'primer_apellido'        => 'Uni',
            'segundo_apellido'       => 'Vercida',
            'fecha_nacimiento'       => '1996-03-6', // Ajusta según necesidad
            'estado_civil'           => 'Casado',//Cambia según los valores en EstadoCivil::all()
            'email'                  => 'talentoHumano@universidad.com',
            'password'               => Hash::make('talento123'), // Cambia la contraseña si lo deseas
        ]);

        // Asignar el rol de admin
        $talentoHumano->assignRole('Talento Humano');

        echo "Talento Humano creado con email: talentoHumano@universidad.com y contraseña: talento123\n";
    }
}
