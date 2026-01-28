<?php

namespace Database\Seeders;

use App\Models\Usuario\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CoordinadorSeeder extends Seeder
{
    public function run(): void
    {
        $coordinador = User::firstOrCreate([
            'email' => 'coordinador@universidad.com'
        ], [
            'municipio_id'           => 703,
            'tipo_identificacion'    => 'Cédula de ciudadanía',
            'numero_identificacion'  => '987654321',
            'genero'                 => 'Masculino',
            'primer_nombre'          => 'Coordinador',
            'segundo_nombre'         => 'Academico',
            'primer_apellido'        => 'Uni',
            'segundo_apellido'       => 'Doc',
            'fecha_nacimiento'       => '1985-01-01',
            'estado_civil'           => 'Soltero',
            'email'                  => 'coordinador@universidad.com',
            'password'               => Hash::make('coordinador123'),
        ]);

        $coordinador->assignRole('Coordinador');

        echo "Coordinador creado con email: coordinador@universidad.com y contraseña: coordinador123\n";
    }
}
