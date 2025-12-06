<?php

namespace Database\Seeders;

use App\Models\Usuario\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AspiranteSeeder extends Seeder
{

    public function run(): void
    {
        $aspirante = User::firstOrCreate([
            'email' => 'aspirante@universidad.com'
        ], [
            'municipio_id'           => 703, // Cambia este valor según el municipio en tu DB
            'tipo_identificacion'    => 'Cédula de ciudadanía', // Cambia según los valores en TipoIdentificacion::all()
            'numero_identificacion'  => '1058962101', // Cambia según necesidad
            'genero'                 => 'Masculino', // Cambia según los valores en Genero::all()
            'primer_nombre'          => 'Aspirante',
            'segundo_nombre'         => 'Aspirante',
            'primer_apellido'        => 'Universidad',
            'segundo_apellido'       => 'Gestión',
            'fecha_nacimiento'       => '1950-01-01', // Ajusta según necesidad
            'estado_civil'           => 'Soltero', // Cambia según los valores en EstadoCivil::all()
            'email'                  => 'aspirante@universidad.com',
            'password'               => Hash::make('aspirante123'), // Cambia la contraseña si lo deseas
        ]);
        $aspirante->assignRole('Aspirante');

        echo "Aspirante creado con email: aspirante@universidad.com y contraseña: aspirante123\n";
    }
}
